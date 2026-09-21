<?php

namespace App\Enerjisa\Http;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use App\Enerjisa\Services\MdmClient;
use App\Enerjisa\Services\MdmException;
use App\Enerjisa\Services\MdmRecords;
use App\Enerjisa\Services\Reactive\Readings;
use App\Enerjisa\Services\ResultDownload;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PanelController
{
    private function account(): Account
    {
        $member = Auth::guard('enerjisa')->user();
        abort_unless($member instanceof Member, 403);

        return $member->account()->firstOrFail();
    }

    public function dashboard(): View
    {
        $account = $this->account();

        return view('enerjisa.dashboard', [
            'account' => $account,
            'queries' => $account->queries()->latest('id')->paginate(10),
            'total' => $account->queries()->count(),
            'failures' => $account->queries()->whereNotNull('error')->count(),
            'installationCount' => count($this->installationRows($account)),
        ]);
    }

    public function settings(): View
    {
        return view('enerjisa.settings', ['account' => $this->account()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $account = $this->account();
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'password' => [$account->client_secret ? 'nullable' : 'required', 'string', 'max:1024'],
        ]);
        if ($data['client_id'] !== $account->client_id && empty($data['password'])) {
            return back()->withErrors(['password' => 'Kullanıcı adı değiştiğinde Enerjisa parolasını da girin.']);
        }
        $account->client_id = $data['client_id'];
        if (! empty($data['password'])) {
            $account->client_secret = $data['password'];
        }
        $account->connected_at = null;
        $account->save();

        return back()->with('success', 'Erişim bilgileri şifrelenerek kaydedildi. Bağlantıyı test edebilirsiniz.');
    }

    public function testConnection(MdmClient $client): RedirectResponse
    {
        $account = $this->account();
        try {
            $client->token($account);
            $account->update(['connected_at' => now()]);
        } catch (MdmException $e) {
            $account->update(['connected_at' => null]);

            return back()->withErrors(['connection' => $e->getMessage()])->with('enerjisa_diagnostics', $e->diagnostics);
        }

        return back()->with('success', 'Başkent MDM bağlantısı başarılı. Erişim jetonu alındı.');
    }

    /** @return list<array<string, mixed>> */
    private function installationRows(Account $account): array
    {
        $query = $account->queries()->where('kind', 'installations')->whereNull('error')->latest('id')->first();

        return MdmRecords::rows($query->payload ?? [], true);
    }

    public function installations(): View
    {
        $account = $this->account();

        return view('enerjisa.installations', [
            'account' => $account,
            'rows' => $this->installationRows($account),
            'last' => $account->queries()->where('kind', 'installations')->whereNull('error')->latest('id')->first(),
        ]);
    }

    public function syncInstallations(MdmClient $client): RedirectResponse
    {
        return $this->execute($client, 'installations', []);
    }

    public function queryForm(): View
    {
        $account = $this->account();

        return view('enerjisa.query', ['account' => $account, 'installations' => $this->installationRows($account)]);
    }

    public function runQuery(QueryRequest $request, MdmClient $client): RedirectResponse
    {
        return $this->execute($client, $request->validated('kind'), $request->apiParameters());
    }

    /** @param array<string, string|int> $parameters */
    private function execute(MdmClient $client, string $kind, array $parameters): RedirectResponse
    {
        $account = $this->account();
        $query = $account->queries()->create(['kind' => $kind, 'parameters' => $parameters]);
        try {
            $payload = $client->fetch($account, $kind, $parameters);
            $query->update(['payload' => $payload]);
        } catch (MdmException $e) {
            $query->update(['error' => $e->getMessage()]);

            return redirect()->route('enerjisa.result', $query->id)->with('enerjisa_diagnostics', $e->diagnostics);
        }

        return redirect()->route('enerjisa.result', $query->id);
    }

    public function download(int $id, string $format, ResultDownload $download): StreamedResponse
    {
        $query = $this->account()->queries()->findOrFail($id);

        return $download->response($query, $format);
    }

    public function result(int $id): View
    {
        $account = $this->account();
        $query = $account->queries()->findOrFail($id);
        $allRows = MdmRecords::rows($query->payload ?? [], $query->kind === 'installations');
        $page = max(1, (int) request('page', 1));
        $rows = new LengthAwarePaginator(
            array_slice($allRows, ($page - 1) * 100, 100), count($allRows), 100, $page,
            ['path' => request()->url()],
        );

        $analysisFilters = ['installation' => $query->parameters['installationNumber'] ?? null];
        foreach (['startDate' => 'start', 'endDate' => 'end'] as $parameter => $filter) {
            $time = Readings::date($query->parameters[$parameter] ?? null);
            if ($time !== null) {
                $analysisFilters[$filter] = $time->format('Y-m-d');
            }
        }

        $owner = null;
        foreach ($this->installationRows($account) as $installation) {
            if ((string) ($installation['installationNumber'] ?? $installation['instalationNumber'] ?? '') === (string) ($analysisFilters['installation'] ?? $query->parameters['installationNumbers'] ?? '')) {
                $owner = $installation['customerName'] ?? null;
                break;
            }
        }

        return view('enerjisa.result', compact('account', 'query', 'rows', 'analysisFilters', 'owner'));
    }
}

<?php

namespace App\Enerjisa\Http;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use App\Enerjisa\Services\MdmException;
use App\Enerjisa\Services\MdmRecords;
use App\Enerjisa\Services\Reactive\Readings;
use App\Enerjisa\Services\Reactive\Report;
use App\Enerjisa\Services\Reactive\Sync;
use App\Enerjisa\Services\ResultDownload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReactiveController
{
    private function account(): Account
    {
        $member = Auth::guard('enerjisa')->user();
        abort_unless($member instanceof Member, 403);

        return $member->account()->firstOrFail();
    }

    public function calculate(ReactiveRequest $request, Sync $sync): RedirectResponse
    {
        $filters = $request->validated();
        unset($filters['page']);
        $redirect = redirect()->route('enerjisa.reactive', array_diff_key($filters, ['refresh' => true]));
        try {
            $count = $sync->run($this->account(), $filters);
        } catch (MdmException $exception) {
            return $redirect->withErrors(['connection' => $exception->getMessage()])
                ->with('enerjisa_diagnostics', $exception->diagnostics);
        }

        return $redirect->with('success', $count > 0
            ? 'Gerekli veriler Enerjisa’dan alındı. Hesaplama güncellendi.'
            : 'Kayıtlı verilerle hesaplama tamamlandı.');
    }

    public function download(ReactiveRequest $request, Readings $readings): StreamedResponse
    {
        $data = $this->index($request, $readings)->getData();

        return response()->streamDownload(function () use ($data): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('CSV çıktı akışı açılamadı.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Tesisat', 'Sayaç', 'Dönem', 'İlk ölçüm', 'Son ölçüm', 'Aktif fark (kWh)', 'Endüktif fark (kVArh)', 'Kapasitif fark (kVArh)', 'Endüktif (%)', 'Kapasitif (%)', 'Endüktif eşik aşımı', 'Kapasitif eşik aşımı', 'Durum', 'Kısmi dönem'], ';', '"', '', "\r\n");
            foreach ($data['exportRows'] as $row) {
                $values = [$data['filters']['installation'], $data['filters']['meter'], $row['label'],
                    $row['first']?->time->format('d.m.Y H:i:s'), $row['last']?->time->format('d.m.Y H:i:s'),
                    $row['activeDelta'], $row['inductiveDelta'], $row['capacitiveDelta'],
                    $row['inductiveRatio'], $row['capacitiveRatio'], $row['inductiveAlert'], $row['capacitiveAlert'], $row['message'], $row['partial']];
                fputcsv($stream, array_map(ResultDownload::cell(...), $values), ';', '"', '', "\r\n");
            }
            fclose($stream);
        }, 'enerjisa-reaktif-analiz.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function index(ReactiveRequest $request, Readings $readings): View
    {
        $account = $this->account();
        $filters = $request->validated();
        $now = CarbonImmutable::now('Europe/Istanbul');
        $from = CarbonImmutable::parse($filters['start'], 'Europe/Istanbul');
        $to = CarbonImmutable::parse($filters['end'], 'Europe/Istanbul');
        $snapshot = $account->queries()->where('kind', 'installations')->whereNull('error')->latest('id')->first();
        $installations = [];
        $owners = [];
        foreach (MdmRecords::rows($snapshot->payload ?? [], true) as $row) {
            $number = (string) ($row['installationNumber'] ?? $row['instalationNumber'] ?? '');
            if ($number !== '') {
                $owners[$number] = (string) ($row['customerName'] ?? '');
                $installations[$number] = $number.' · '.($owners[$number] ?: 'Tesisat');
            }
        }
        // Existing index snapshots remain usable even before refreshing installations.
        foreach ($account->queries()->where('kind', '1')->whereNull('error')->select('parameters')->lazy() as $query) {
            $number = (string) ($query->parameters['installationNumber'] ?? '');
            if ($number !== '' && ! isset($installations[$number])) {
                $installations[$number] = $number;
            }
        }
        $installation = $filters['installation'] ?? (string) (array_key_first($installations) ?? '');
        $filters['installation'] = $installation;
        if ($installation !== '' && ! isset($installations[$installation])) {
            $installations[$installation] = $installation;
        }
        $owner = $owners[$installation] ?? null;
        $loaded = $readings->load($account, $installation, $from->startOfMonth(), $to->endOfMonth()->addDay()->min($now));
        $meters = $loaded['meters'];
        $meter = $filters['meter'] ?? (string) (array_key_first($meters) ?? 'unknown');
        $filters['meter'] = $meter;
        $selected = $meters[$meter] ?? [];
        $results = app(Report::class)->build($selected, $filters['period'], $from, $to, $now);
        $summary = [
            'total' => count($results),
            'calculated' => count(array_filter($results, fn ($row) => in_array($row['status'], ['ok', 'alert'], true))),
            'alerts' => count(array_filter($results, fn ($row) => $row['status'] === 'alert')),
            'invalid' => count(array_filter($results, fn ($row) => ! in_array($row['status'], ['ok', 'alert'], true))),
        ];
        $visible = array_values(array_filter($results, fn ($row) => match ($filters['status']) {
            'alert' => $row['status'] === 'alert',
            'invalid' => ! in_array($row['status'], ['ok', 'alert'], true),
            default => true,
        }));
        $page = (int) ($filters['page'] ?? 1);
        $rows = new LengthAwarePaginator(array_slice($visible, ($page - 1) * 48, 48), count($visible), 48, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]);
        $lastReading = $selected === [] ? null : $selected[array_key_last($selected)];

        return view('enerjisa.reactive', compact('account', 'filters', 'installations', 'meters', 'rows', 'summary', 'lastReading', 'loaded', 'owner'))->with('exportRows', $visible);
    }
}

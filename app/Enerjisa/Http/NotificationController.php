<?php

namespace App\Enerjisa\Http;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use App\Enerjisa\Models\NotificationDelivery;
use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Services\MdmRecords;
use App\Enerjisa\Services\TelegramSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationController
{
    private function account(): Account
    {
        $member = Auth::guard('enerjisa')->user();
        abort_unless($member instanceof Member, 403);

        return $member->account()->firstOrFail();
    }

    /** @return array<string, string> */
    private function installations(Account $account): array
    {
        $snapshot = $account->queries()->where('kind', 'installations')->whereNull('error')->latest('id')->first();
        $result = [];
        foreach (MdmRecords::rows($snapshot->payload ?? [], true) as $row) {
            $number = (string) ($row['installationNumber'] ?? $row['instalationNumber'] ?? '');
            if ($number !== '') {
                $result[$number] = (string) ($row['customerName'] ?? '');
            }
        }

        return $result;
    }

    public function index(Request $request): View
    {
        $account = $this->account();
        $installations = $this->installations($account);
        $rules = NotificationRule::where('account_id', $account->id)->orderBy('id')->get();
        foreach ($rules as $rule) {
            $installations[$rule->installation] ??= '';
        }
        $selected = (string) $request->query('installation', array_key_first($installations) ?? '');
        $request->validate(['rule_id' => 'nullable|integer', 'new' => 'nullable|boolean']);
        $installationRules = $rules->where('installation', $selected)->values();
        $rule = $request->boolean('new') ? null : ($request->filled('rule_id')
            ? $installationRules->firstWhere('id', (int) $request->rule_id) : $installationRules->first());
        abort_if($request->filled('rule_id') && ! $rule, 404);
        $telegramReady = app(TelegramSettings::class)->ready();
        $history = NotificationDelivery::whereIn('rule_id', $rules->pluck('id'))->latest('id')->limit(30)->get();

        return view('enerjisa.notifications', compact('account', 'installations', 'rules', 'selected', 'rule', 'installationRules', 'telegramReady', 'history'));
    }

    public function connect(Request $request): RedirectResponse
    {
        $account = $this->account();
        $data = $request->validate(['installation' => ['required', 'string', Rule::in(array_merge(
            array_keys($this->installations($account)), NotificationRule::where('account_id', $account->id)->pluck('installation')->all(),
        ))]]);
        $telegram = app(TelegramSettings::class)->all();
        $username = (string) ($telegram['username'] ?? '');
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $username) || empty($telegram['token']) || empty($telegram['webhook_secret'])) {
            return back()->withErrors(['telegram' => 'Telegram bağlantısı şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.']);
        }
        $request->validate(['rule_id' => 'nullable|integer']);
        $rule = $request->filled('rule_id')
            ? NotificationRule::where('account_id', $account->id)->where('installation', $data['installation'])->findOrFail($request->rule_id)
            : NotificationRule::create(['account_id' => $account->id, 'installation' => $data['installation']]);
        $token = bin2hex(random_bytes(32));
        $rule->update(['telegram_link_hash' => hash('sha256', $token), 'telegram_link_expires_at' => now()->addMinutes(15)]);

        return redirect()->away('https://t.me/'.$username.'?start='.$token);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $data = $request->validate(['installation' => ['required', 'string'], 'rule_id' => 'nullable|integer']);
        $query = NotificationRule::where('account_id', $this->account()->id)->where('installation', $data['installation']);
        $rule = $request->filled('rule_id') ? $query->findOrFail($data['rule_id']) : $query->orderBy('id')->first();
        $rule?->update([
            'telegram_enabled' => false, 'chat_id' => null, 'telegram_connected_at' => null,
            'telegram_link_hash' => null, 'telegram_link_expires_at' => null,
        ]);

        return back()->with('success', 'Telegram bağlantısı kaldırıldı.');
    }

    public function save(Request $request): RedirectResponse
    {
        $account = $this->account();
        $allowed = array_keys($this->installations($account));
        $existing = NotificationRule::where('account_id', $account->id)->pluck('installation')->all();
        $data = $request->validate([
            'installation' => ['required', 'string', Rule::in(array_merge($allowed, $existing))],
            'rule_id' => ['nullable', 'integer'],
            'enabled' => ['required', 'boolean'], 'frequency' => ['required', Rule::in(['daily', 'weekly', 'previous_day'])],
            'send_time' => ['required', 'date_format:H:i'], 'weekday' => ['required', 'integer', 'between:1,7'],
            'send_healthy' => ['required', 'boolean'], 'email_enabled' => ['required', 'boolean'],
            'telegram_enabled' => ['required', 'boolean'],
            'email' => ['nullable', 'required_if:email_enabled,1', 'email', 'max:255'],
        ]);
        $rule = $request->filled('rule_id')
            ? NotificationRule::where('account_id', $account->id)->where('installation', $data['installation'])->findOrFail($data['rule_id'])
            : new NotificationRule(['account_id' => $account->id]);
        unset($data['rule_id']);
        if ($data['enabled']) {
            if (! $data['email_enabled'] && ! $data['telegram_enabled']) {
                return back()->withInput()->withErrors(['channels' => 'En az bir gönderim kanalı seçin.']);
            }
            if ($data['telegram_enabled'] && (! $rule?->telegram_connected_at || ! $rule->chat_id)) {
                return back()->withInput()->withErrors(['channels' => 'Önce Telegram hesabınızı bağlayın.']);
            }
        }
        $rule->fill($data)->save();

        return redirect()->route('enerjisa.notifications', ['installation' => $data['installation'], 'rule_id' => $rule->id])->with('success', 'Bildirim kaydedildi.');
    }
}

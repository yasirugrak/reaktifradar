<?php

namespace App\Enerjisa\Services;

use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TelegramSettings
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        return SystemSetting::find('telegram')->value ?? config('enerjisa.telegram', []);
    }

    public function ready(): bool
    {
        $settings = $this->all();

        return ! empty($settings['token']) && ! empty($settings['username']) && ! empty($settings['webhook_secret']);
    }

    public function connect(string $token, int $adminId): void
    {
        $url = rtrim((string) config('app.url'), '/').'/enerjisa/telegram/webhook';
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('Sunucunun APP_URL ayarı geçerli bir HTTPS adresi olmalıdır.');
        }
        $previous = $this->all();
        $secret = (string) ($previous['webhook_secret'] ?? '');
        if (! preg_match('/^[A-Za-z0-9_-]{32,256}$/', $secret)) {
            $secret = bin2hex(random_bytes(32));
        }
        try {
            $http = Http::connectTimeout(10)->timeout(20)->withoutRedirecting();
            $me = $http->post('https://api.telegram.org/bot'.$token.'/getMe');
            if (! $me->successful() || $me->json('ok') !== true || ! $me->json('result.is_bot') || ! preg_match('/^[A-Za-z0-9_]+$/', (string) $me->json('result.username'))) {
                throw new RuntimeException;
            }
            $response = $http->post('https://api.telegram.org/bot'.$token.'/setWebhook', [
                'url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message'],
            ]);
            if (! $response->successful() || $response->json('ok') !== true) {
                throw new RuntimeException;
            }
        } catch (Throwable) {
            throw new RuntimeException('Telegram kurulumu tamamlanamadı. Bot token ve HTTPS adresini kontrol edin. Mevcut ayarlar korunmuştur.');
        }
        // A different bot cannot reuse private conversations from the old bot.
        if (! empty($previous['username']) && $previous['username'] !== $me->json('result.username')) {
            NotificationRule::query()->update(['telegram_enabled' => false, 'chat_id' => null,
                'telegram_connected_at' => null, 'telegram_link_hash' => null, 'telegram_link_expires_at' => null]);
        }
        SystemSetting::updateOrCreate(['key' => 'telegram'], ['updated_by' => $adminId, 'value' => [
            'token' => $token, 'username' => $me->json('result.username'), 'webhook_secret' => $secret,
            'registered_at' => now()->toIso8601String(),
        ]]);
    }
}

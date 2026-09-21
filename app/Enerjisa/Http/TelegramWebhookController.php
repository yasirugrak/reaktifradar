<?php

namespace App\Enerjisa\Http;

use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Services\TelegramSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelegramWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) (app(TelegramSettings::class)->all()['webhook_secret'] ?? '');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);
        $message = $request->input('message', []);
        if (! is_array($message) || ($message['chat']['type'] ?? '') !== 'private') {
            return response()->json(['ok' => true]);
        }
        $chat = (string) ($message['chat']['id'] ?? '');
        if (! preg_match('/^[1-9][0-9]{0,19}$/', $chat) || $chat !== (string) ($message['from']['id'] ?? '')) {
            return response()->json(['ok' => true]);
        }
        $text = $message['text'] ?? '';
        if (! is_string($text)) {
            return response()->json(['ok' => true]);
        }
        if ($text === '/stop') {
            NotificationRule::where('chat_id', $chat)->update(['telegram_enabled' => false, 'chat_id' => null,
                'telegram_connected_at' => null, 'telegram_link_hash' => null, 'telegram_link_expires_at' => null]);

            return $this->reply($chat, 'Telegram bildirimleriniz durduruldu. Yeniden bağlamak için paneli kullanabilirsiniz.');
        }
        if (! preg_match('/^\/start(?:@[a-zA-Z0-9_]+)? ([a-f0-9]{64})$/', $text, $match)) {
            return $this->reply($chat, 'Tesisat bildirimlerini almak için panelde “Telegram’ı bağla” düğmesini kullanın. Durdurmak için /stop yazabilirsiniz.');
        }
        $installation = DB::transaction(function () use ($match, $chat): ?string {
            $rule = NotificationRule::whereHas('account', fn ($query) => $query->where('is_active', true))->where('telegram_link_hash', hash('sha256', $match[1]))->lockForUpdate()->first();
            if (! $rule || ! $rule->telegram_link_expires_at || $rule->telegram_link_expires_at->isPast()) {
                return null;
            }
            $rule->update(['chat_id' => $chat, 'telegram_connected_at' => now(),
                'telegram_link_hash' => null, 'telegram_link_expires_at' => null]);

            return $rule->installation;
        });
        if ($installation === null) {
            return $this->reply($chat, 'Bu bağlantı kullanılmış veya süresi dolmuş. Panelden yeni bağlantı oluşturun.');
        }

        return $this->reply($chat, 'Telegram bağlandı. Tesisat '.$installation.' için panelde Telegram gönderimini açıp ayarlarınızı kaydedin.', [
            'inline_keyboard' => [[['text' => 'Panele dön', 'url' => route('enerjisa.notifications', ['installation' => $installation])]]],
        ]);
    }

    /** @param array<string, mixed> $markup */
    private function reply(string $chat, string $text, array $markup = []): JsonResponse
    {
        return response()->json(array_filter(['method' => 'sendMessage', 'chat_id' => $chat, 'text' => $text, 'reply_markup' => $markup]));
    }
}

<?php

namespace App\Enerjisa\Services\Notifications;

use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Services\TelegramSettings;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class Sender
{
    /** @param array<string, mixed>|null $emailData */
    public function send(NotificationRule $rule, string $channel, string $body, ?array $emailData = null): void
    {
        $token = app(TelegramSettings::class)->all()['token'] ?? null;
        try {
            if ($channel === 'telegram') {
                if (! $token || ! $rule->chat_id || ! $rule->telegram_connected_at) {
                    throw new RuntimeException('Missing Telegram configuration');
                }
                $response = Http::connectTimeout(10)->timeout(20)->withoutRedirecting()->post(
                    'https://api.telegram.org/bot'.$token.'/sendMessage',
                    ['chat_id' => $rule->chat_id, 'text' => app(TelegramReport::class)->render($body, $emailData),
                        'parse_mode' => 'HTML'],
                );
                if (! $response->successful() || $response->json('ok') !== true) {
                    throw new RuntimeException('Telegram rejected delivery');
                }
            } else {
                if (! $rule->email || ! config('mail.mailers.smtp.host') || ! config('mail.from.address')) {
                    throw new RuntimeException('Missing central SMTP configuration');
                }
                $mailer = app(MailManager::class)->mailer('smtp');
                $report = $emailData ?? ['state' => 'legacy', 'installation' => $rule->installation];
                $url = rtrim((string) config('app.url'), '/').'/panel/reactive?'.http_build_query([
                    'installation' => $rule->installation, 'period' => 'daily',
                    'start' => $report['start'] ?? null, 'end' => $report['end'] ?? null,
                ]);
                $mailer->send(['html' => 'enerjisa.mail.report', 'text' => 'enerjisa.mail.report-text'],
                    compact('report', 'body', 'url'), function (Message $message) use ($rule): void {
                        $message->from(config('mail.from.address'), config('mail.from.name'))
                            ->to($rule->email)->subject('ReaktifRadar · Tesisat '.$rule->installation.' · Durum raporu');
                    });
            }
        } catch (Throwable) {
            // Transport exceptions can contain SMTP credentials or Telegram token URLs.
            throw new RuntimeException($channel === 'telegram'
                ? 'Telegram gönderilemedi. Telegram bağlantınızı kontrol edin veya sistem yöneticisine başvurun.'
                : 'E-posta gönderilemedi. Alıcı adresini kontrol edin veya sistem yöneticisine başvurun.');
        }
    }
}

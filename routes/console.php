<?php

use App\Enerjisa\Services\Notifications\Dispatcher;
use App\Enerjisa\Services\TelegramSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('enerjisa:notify', function (): void {
    if (! config('enerjisa.notifications_enabled')) {
        $this->info('Bildirimler kapalı.');

        return;
    }
    $sent = app(Dispatcher::class)->run();
    $this->info($sent.' bildirim gönderildi.');
})->purpose('Enerjisa tesisat bildirimlerini zamanı gelen kurallara göre gönderir');

Schedule::command('enerjisa:notify')->when(fn () => config('enerjisa.notifications_enabled'))->everyMinute()->withoutOverlapping(30)->runInBackground();

Artisan::command('enerjisa:telegram-webhook', function (): int {
    $settings = app(TelegramSettings::class)->all();
    $token = (string) ($settings['token'] ?? '');
    $secret = (string) ($settings['webhook_secret'] ?? '');
    $url = rtrim((string) config('app.url'), '/').'/panel/telegram/webhook';
    if ($token === '' || ! preg_match('/^[A-Za-z0-9_-]{32,256}$/', $secret) || ! str_starts_with($url, 'https://')) {
        $this->error('Merkezi bot token, en az 32 karakterlik webhook secret ve HTTPS APP_URL gereklidir.');

        return Command::FAILURE;
    }
    try {
        $response = Http::timeout(20)->withoutRedirecting()->post('https://api.telegram.org/bot'.$token.'/setWebhook', [
            'url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message'],
        ]);
        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException;
        }
    } catch (Throwable) {
        $this->error('Telegram bağlantı kaydı başarısız. Merkezi bot ayarlarını ve HTTPS adresini kontrol edin.');

        return Command::FAILURE;
    }
    $this->info('Telegram bağlantı adresi kaydedildi.');

    return Command::SUCCESS;
})->purpose('Merkezi Telegram botunun güvenli webhook adresini kaydeder');

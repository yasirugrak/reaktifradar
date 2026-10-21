<?php

namespace App\Filament\Pages;

use App\Mail\ProspectingIntroduction;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;
use UnitEnum;

class IntroductionMail extends Page
{
    protected string $view = 'filament.pages.introduction-mail';
    protected static ?string $title = 'Tanıtım E-postası';
    protected static string|UnitEnum|null $navigationGroup = 'ReaktifRadar';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public static function canAccess(): bool
    {
        return EnerjisaAdmin::canAccess();
    }

    public function sendTest(): void
    {
        abort_unless(static::canAccess(), 403);
        $recipient = Auth::guard('web')->user()->email;
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Notification::make()->title('Hesabınızdaki e-posta adresini kontrol edin.')->danger()->send();
            return;
        }
        $key = 'introduction-test:'.Auth::guard('web')->id();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            Notification::make()->title('Yeni test için bir dakika bekleyin.')->warning()->send();
            return;
        }
        RateLimiter::hit($key, 60);
        try {
            Mail::mailer('smtp')->to($recipient)->send(new ProspectingIntroduction);
            Notification::make()->title('Test e-postası posta sunucusuna iletildi.')->body('Gelen kutunuzu ve spam klasörünüzü kontrol edin.')->success()->send();
        } catch (Throwable) {
            Notification::make()->title('Test e-postası gönderilemedi.')->body('Sunucunun SMTP ayarlarını ve bağlantısını kontrol edin.')->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        return [
            'recipient' => Auth::guard('web')->user()->email,
            'subject' => '[TEST] '.ProspectingIntroduction::SUBJECT,
            'preview' => (new ProspectingIntroduction)->render(),
        ];
    }
}

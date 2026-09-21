<?php

namespace App\Filament\Pages;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use App\Enerjisa\Models\NotificationDelivery;
use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Models\Query;
use App\Enerjisa\Models\SystemSetting;
use App\Enerjisa\Services\TelegramSettings;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use UnitEnum;

class EnerjisaAdmin extends Page
{
    protected string $view = 'filament.pages.enerjisa-admin';

    protected static ?string $title = 'ReaktifRadar Yönetimi';

    protected static ?string $navigationLabel = 'Genel durum ve Telegram';

    protected static string|UnitEnum|null $navigationGroup = 'ReaktifRadar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = -1;

    public string $token = '';

    public static function canAccess(): bool
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User && $user->isSuperAdmin() && $user->email_verified_at !== null;
    }

    public function saveTelegram(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['token' => ['required', 'string', 'max:255', 'regex:/^[0-9]+:[A-Za-z0-9_-]+$/']]);
        $token = $this->token;
        $this->reset('token');
        try {
            app(TelegramSettings::class)->connect($token, (int) Auth::guard('web')->id());
            Notification::make()->title('Telegram doğrulandı ve bağlantı kuruldu.')->success()->send();
        } catch (RuntimeException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $telegram = app(TelegramSettings::class)->all();

        return [
            'counts' => ['Firmalar' => Account::count(), 'Aktif kullanıcılar' => Member::where('is_active', true)->whereHas('account', fn ($q) => $q->where('is_active', true))->count(),
                'Açık bildirim kuralları' => NotificationRule::where('enabled', true)->whereHas('account', fn ($q) => $q->where('is_active', true))->count(),
                'Son 24 saatte gönderilen' => NotificationDelivery::where('sent_at', '>=', now()->subDay())->count()],
            'telegramUsername' => $telegram['username'] ?? null,
            'telegramRegisteredAt' => $telegram['registered_at'] ?? null,
            'schedulerRun' => SystemSetting::find('scheduler')?->value['last_run'] ?? null,
            'smtpHost' => config('mail.mailers.smtp.host'), 'smtpFrom' => config('mail.from.address'),
            'failures' => Query::with('account')->whereNotNull('error')->latest('id')->limit(10)->get(),
            'deliveryFailures' => NotificationDelivery::with('rule.account')->whereIn('status', ['failed', 'sending'])->latest('id')->limit(10)->get(),
        ];
    }
}

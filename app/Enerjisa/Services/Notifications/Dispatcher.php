<?php

namespace App\Enerjisa\Services\Notifications;

use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Models\SystemSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class Dispatcher
{
    public function run(): int
    {
        $now = CarbonImmutable::now('Europe/Istanbul');
        SystemSetting::updateOrCreate(['key' => 'scheduler'], ['value' => ['last_run' => $now->toIso8601String()]]);
        $sent = 0;
        foreach (NotificationRule::where('enabled', true)->whereHas('account', fn ($query) => $query->where('is_active', true))->with('account')->lazyById() as $rule) {
            if ($now->format('H:i') < $rule->send_time || ($rule->frequency === 'weekly' && $now->dayOfWeekIso !== $rule->weekday)) {
                continue;
            }
            $lock = Cache::lock('enerjisa-notification-'.$rule->id, 600);
            if (! $lock->get()) {
                continue;
            }
            try {
                $summary = null;
                foreach (['email', 'telegram'] as $channel) {
                    if (! $rule->{$channel.'_enabled'}) {
                        continue;
                    }
                    $delivery = $rule->deliveries()->firstOrCreate(['scheduled_date' => $now->toDateString(), 'channel' => $channel]);
                    if (in_array($delivery->status, ['sent', 'skipped', 'sending'], true) || $delivery->attempts >= 3
                        || ($delivery->attempts > 0 && $delivery->updated_at->greaterThan($now->subMinutes(30)))) {
                        continue;
                    }
                    if (! $delivery->body) {
                        $summary ??= app(Summary::class)->build($rule, $now);
                        if ($summary['healthy'] && ! $rule->send_healthy) {
                            $delivery->update(['status' => 'skipped', 'body' => $summary['body']]);

                            continue;
                        }
                        $delivery->body = $summary['body'];
                        $delivery->email_data = $summary['email'];
                    }
                    // Persist before I/O: a crashed/ambiguous delivery is not blindly resent.
                    $delivery->fill(['status' => 'sending', 'attempts' => $delivery->attempts + 1, 'error' => null])->save();
                    try {
                        app(Sender::class)->send($rule, $channel, $delivery->body, $delivery->email_data);
                        $delivery->update(['status' => 'sent', 'sent_at' => now()]);
                        $sent++;
                    } catch (Throwable) {
                        $delivery->update(['status' => 'failed', 'error' => 'Gönderilemedi. Kanal ayarlarını kontrol edin. En fazla 3 deneme yapılır.']);
                    }
                }
            } finally {
                $lock->release();
            }
        }

        return $sent;
    }
}

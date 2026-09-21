<?php

namespace App\Enerjisa\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $telegram_link_expires_at
 * @property CarbonImmutable|null $telegram_connected_at
 */
class NotificationRule extends Model
{
    protected $table = 'enerjisa_notification_rules';

    protected $guarded = ['id'];

    protected $hidden = ['telegram_link_hash'];

    protected function casts(): array
    {
        return ['telegram_link_expires_at' => 'immutable_datetime', 'telegram_connected_at' => 'immutable_datetime', 'enabled' => 'boolean', 'send_healthy' => 'boolean', 'email_enabled' => 'boolean', 'telegram_enabled' => 'boolean', 'weekday' => 'integer'];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'rule_id');
    }
}

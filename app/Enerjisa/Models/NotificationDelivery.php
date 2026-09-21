<?php

namespace App\Enerjisa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property array<string, mixed>|null $email_data */
class NotificationDelivery extends Model
{
    protected $table = 'enerjisa_notification_deliveries';

    protected $guarded = ['id'];

    /** @return BelongsTo<NotificationRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class, 'rule_id');
    }

    protected function casts(): array
    {
        return ['email_data' => 'encrypted:array', 'body' => 'encrypted', 'sent_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}

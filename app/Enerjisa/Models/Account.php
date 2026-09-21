<?php

namespace App\Enerjisa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $notification_settings
 * @property bool $is_active
 */
class Account extends Model
{
    protected $table = 'enerjisa_accounts';

    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true];

    protected $hidden = ['client_id', 'client_secret', 'notification_settings'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'notification_settings' => 'encrypted:array', 'client_id' => 'encrypted', 'client_secret' => 'encrypted', 'connected_at' => 'datetime'];
    }

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'account_id');
    }

    /** @return HasMany<Query, $this> */
    public function queries(): HasMany
    {
        return $this->hasMany(Query::class, 'account_id');
    }
}

<?php

namespace App\Enerjisa\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Member extends Authenticatable
{
    protected $table = 'enerjisa_users';

    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_login_at' => 'datetime', 'password' => 'hashed'];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

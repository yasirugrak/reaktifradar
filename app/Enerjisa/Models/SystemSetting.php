<?php

namespace App\Enerjisa\Models;

use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $value */
class SystemSetting extends Model
{
    protected $table = 'enerjisa_system_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted:array'];
    }
}

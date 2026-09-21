<?php

namespace App\Enerjisa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $parameters
 * @property array<array-key, mixed>|null $payload Decrypted JSON snapshot.
 */
class Query extends Model
{
    protected $table = 'enerjisa_queries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'payload' => 'encrypted:array'];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function label(): string
    {
        return match ($this->kind) {
            'installations' => 'Tesisat listesi',
            'hourly' => 'Saatlik tüketim / üretim',
            '1' => 'Saatlik endeks',
            '2' => 'Günlük endeks',
            '3' => 'Reset verisi',
            default => 'Sorgu',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspectContact extends Model
{
    protected $guarded = ['id'];

    public static function pool(): \Illuminate\Database\Eloquent\Builder
    {
        // One representative per normalized email; exclusion always wins.
        $ids = static::query()->selectRaw("COALESCE(MIN(CASE WHEN status = 'excluded' THEN id END), MIN(CASE WHEN status = 'approved' THEN id END), MIN(id))")
            ->groupByRaw('LOWER(TRIM(email))');

        return static::query()->whereIn('id', $ids);
    }

    public function introductionDelivery(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProspectMailDelivery::class, 'email', 'email');
    }

}

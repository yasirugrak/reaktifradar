<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspectContact extends Model
{
    protected $guarded = ['id'];

    public function introductionDelivery(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProspectMailDelivery::class, 'email', 'email');
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['redemption_id', 'provider', 'provider_reference', 'amount_cop', 'status', 'raw_response', 'confirmed_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function redemption()
    {
        return $this->belongsTo(Redemption::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code', 'patient_id', 'reward_id', 'reward_title_snapshot', 'reward_price_cop_snapshot',
    'points_used', 'money_amount_cop', 'points_percentage_applied', 'payment_method',
    'status', 'approved_at',
])]
class Redemption extends Model
{
    protected function casts(): array
    {
        return [
            'points_percentage_applied' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function pointConsumptions()
    {
        return $this->hasMany(PointConsumption::class);
    }

    public function isFullyPaidByPoints(): bool
    {
        return $this->payment_method === 'points_only';
    }
}

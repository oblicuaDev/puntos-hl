<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['patient_id', 'points_original', 'points_remaining', 'earned_at', 'expires_at', 'status', 'sourceable_type', 'sourceable_id'])]
class PointBatch extends Model
{
    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function sourceable()
    {
        return $this->morphTo();
    }

    public function consumptions()
    {
        return $this->hasMany(PointConsumption::class);
    }

    public function scopeActiveAndValid($query)
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }
}

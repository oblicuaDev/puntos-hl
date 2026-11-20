<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'patient_id', 'admin_user_id', 'procedure_id', 'amount_cop',
    'exchange_rate_applied', 'points_awarded', 'sale_date', 'notes',
])]
class Sale extends Model
{
    protected function casts(): array
    {
        return [
            'exchange_rate_applied' => 'decimal:4',
            'sale_date' => 'date',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }

    public function pointBatch()
    {
        return $this->morphOne(PointBatch::class, 'sourceable');
    }
}

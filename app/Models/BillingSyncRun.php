<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'spreadsheet_id', 'cutoff_date', 'accrual_cop_per_point', 'started_at', 'finished_at',
    'rows_read', 'rows_processed', 'rows_pending', 'patients_created', 'points_total',
    'pending_rows', 'error',
])]
class BillingSyncRun extends Model
{
    protected function casts(): array
    {
        return [
            'cutoff_date' => 'date',
            'accrual_cop_per_point' => 'decimal:4',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'pending_rows' => 'array',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(BillingRecord::class);
    }
}

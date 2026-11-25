<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['patient_id', 'purpose', 'phone', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip'])]
class PatientLoginCode extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}

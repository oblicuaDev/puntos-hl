<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reward_id', 'path', 'order', 'alt_text'])]
class RewardImage extends Model
{
    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }
}

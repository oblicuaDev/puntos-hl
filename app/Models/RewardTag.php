<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug'])]
class RewardTag extends Model
{
    public function rewards()
    {
        return $this->belongsToMany(Reward::class, 'reward_reward_tag');
    }
}

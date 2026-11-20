<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'parent_id', 'order', 'is_active'])]
class RewardCategory extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(RewardCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(RewardCategory::class, 'parent_id');
    }

    public function rewards()
    {
        return $this->hasMany(Reward::class, 'category_id');
    }
}

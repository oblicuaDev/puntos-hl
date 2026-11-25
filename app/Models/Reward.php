<?php

namespace App\Models;

use App\Services\Points\PointRates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'slug', 'title', 'short_description', 'body_html',
    'price_cop', 'stock', 'is_active', 'is_featured', 'valid_from', 'valid_until',
])]
class Reward extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function category()
    {
        return $this->belongsTo(RewardCategory::class, 'category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(RewardTag::class, 'reward_reward_tag');
    }

    public function images()
    {
        return $this->hasMany(RewardImage::class)->orderBy('order');
    }

    public function redemptions()
    {
        return $this->hasMany(Redemption::class);
    }

    /**
     * Precio en puntos, derivado siempre de la tasa de REDENCIÓN vigente.
     * price_cop es la única fuente de verdad; esto nunca se persiste.
     */
    public function pointsEquivalent(): int
    {
        return PointRates::pointsNeededFor((int) $this->price_cop);
    }
}

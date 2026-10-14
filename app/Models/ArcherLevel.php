<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ArcherLevel extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'minimum_stars', 'minimum_rating',
        'icon', 'image_path', 'sort_order', 'is_active', 'created_by',
    ];

    protected $casts = [
        'minimum_stars' => 'integer',
        'minimum_rating' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

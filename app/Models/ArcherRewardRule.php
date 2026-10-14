<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ArcherRewardRule extends Model
{
    protected $fillable = [
        'code', 'event_type', 'name', 'description', 'stars', 'frequency',
        'daily_limit', 'weekly_limit', 'monthly_limit', 'criteria',
        'is_enabled', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'stars' => 'integer',
        'daily_limit' => 'integer',
        'weekly_limit' => 'integer',
        'monthly_limit' => 'integer',
        'criteria' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}

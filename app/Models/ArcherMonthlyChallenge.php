<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ArcherMonthlyChallenge extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'icon', 'month_start', 'month_end', 'criteria',
        'reward_stars', 'badge_id', 'applicable_level_id', 'applicable_country',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'month_start' => 'date',
        'month_end' => 'date',
        'criteria' => 'array',
        'reward_stars' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActiveNow(Builder $query): Builder
    {
        $today = now()->utc()->toDateString();

        return $query->where('is_active', true)
            ->whereDate('month_start', '<=', $today)
            ->whereDate('month_end', '>=', $today);
    }
}

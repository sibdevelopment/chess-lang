<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArcherMission extends Model
{
    protected $fillable = [
        'name', 'description', 'icon', 'starts_at', 'ends_at', 'criteria_event_type',
        'target', 'reward_stars', 'criteria', 'applicable_level_id', 'applicable_country',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'target' => 'integer',
        'reward_stars' => 'integer',
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActiveNow(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now()->utc())
            ->where('ends_at', '>=', now()->utc());
    }

    public function progressRows(): HasMany
    {
        return $this->hasMany(ArcherMissionProgress::class, 'mission_id');
    }
}

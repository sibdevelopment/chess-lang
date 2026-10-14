<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArcherBadge extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'icon', 'image_path', 'criteria_event_type',
        'criteria_target', 'criteria', 'is_automatic', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'criteria_target' => 'integer',
        'criteria' => 'array',
        'is_automatic' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(ArcherBadgeAward::class, 'badge_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcherAchievement extends Model
{
    protected $fillable = [
        'user_id', 'achievement_type', 'title', 'note', 'awarded_by', 'activity_event_id', 'awarded_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}

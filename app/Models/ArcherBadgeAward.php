<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcherBadgeAward extends Model
{
    protected $fillable = [
        'badge_id', 'user_id', 'activity_event_id', 'awarded_by', 'earned_at', 'note', 'metadata',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function badge(): BelongsTo
    {
        return $this->belongsTo(ArcherBadge::class, 'badge_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

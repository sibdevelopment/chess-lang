<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArcherActivityEvent extends Model
{
    protected $fillable = [
        'event_key', 'event_type', 'user_id', 'source_type', 'source_id',
        'actor_id', 'occurred_at', 'user_timezone', 'event_timezone',
        'context', 'processed_at', 'processing_attempts', 'failure_reason',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
        'processing_attempts' => 'integer',
        'context' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function starTransactions(): HasMany
    {
        return $this->hasMany(ArcherStarTransaction::class, 'activity_event_id');
    }
}

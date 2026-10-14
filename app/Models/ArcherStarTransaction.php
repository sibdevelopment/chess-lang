<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcherStarTransaction extends Model
{
    protected $fillable = [
        'user_id', 'activity_event_id', 'reward_rule_id', 'transaction_type',
        'stars', 'source_type', 'source_id', 'actor_id', 'reversal_of_id',
        'reason', 'occurred_at', 'event_timezone', 'metadata',
    ];

    protected $casts = [
        'stars' => 'integer',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ArcherActivityEvent::class, 'activity_event_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ArcherRewardRule::class, 'reward_rule_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

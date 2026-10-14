<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcherMonthlyChallengeProgress extends Model
{
    protected $table = 'archer_monthly_challenge_progress';

    protected $fillable = [
        'challenge_id', 'user_id', 'progress', 'completed_at', 'rewarded_at',
    ];

    protected $casts = [
        'progress' => 'array',
        'completed_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(ArcherMonthlyChallenge::class, 'challenge_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcherMissionProgress extends Model
{
    protected $table = 'archer_mission_progress';

    protected $fillable = [
        'mission_id', 'user_id', 'progress', 'status', 'completed_at', 'rewarded_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'completed_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(ArcherMission::class, 'mission_id');
    }
}

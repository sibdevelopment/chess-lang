<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcherDiscoverySnapshot extends Model
{
    protected $fillable = [
        'user_id', 'student_name', 'chess_experience', 'strength', 'focus',
        'recommended_level', 'next_step', 'assessed_by', 'assessed_at',
    ];

    protected $casts = ['assessed_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}

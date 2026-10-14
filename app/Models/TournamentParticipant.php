<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentParticipant extends Model
{
    protected $fillable = [
        'tournament_id', 'user_id', 'score', 'buchholz', 'withdrawn', 'bye_received',
    ];

    protected $casts = [
        'score'        => 'float',
        'buchholz'     => 'float',
        'withdrawn'    => 'boolean',
        'bye_received' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

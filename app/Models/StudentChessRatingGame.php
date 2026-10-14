<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentChessRatingGame extends Model
{
    protected $fillable = [
        'rating_period_id', 'pairing_id', 'opponent_id', 'opponent_rating',
        'score', 'expected_score', 'raw_delta',
    ];

    protected $casts = [
        'opponent_rating' => 'integer',
        'score' => 'float',
        'expected_score' => 'float',
        'raw_delta' => 'float',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(StudentChessRatingPeriod::class, 'rating_period_id');
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(TournamentPairing::class, 'pairing_id');
    }
}

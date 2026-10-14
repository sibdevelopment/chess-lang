<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentChessRatingPeriod extends Model
{
    protected $fillable = [
        'user_id', 'tournament_id', 'rating_type', 'rating_before', 'rating_change',
        'rating_after', 'k_factor', 'games_rated', 'score', 'expected_score',
        'calculated_at', 'calculation',
    ];

    protected $casts = [
        'rating_before' => 'integer',
        'rating_change' => 'integer',
        'rating_after' => 'integer',
        'k_factor' => 'integer',
        'games_rated' => 'integer',
        'score' => 'float',
        'expected_score' => 'float',
        'calculated_at' => 'datetime',
        'calculation' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(StudentChessRatingGame::class, 'rating_period_id');
    }
}

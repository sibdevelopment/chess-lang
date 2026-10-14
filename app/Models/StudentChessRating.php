<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentChessRating extends Model
{
    public const TYPES = ['classical', 'rapid', 'blitz'];

    protected $fillable = [
        'user_id', 'rating_type', 'initial_rating', 'rating', 'peak_rating', 'games_played',
        'is_provisional', 'ever_reached_2400', 'source', 'last_rated_at',
    ];

    protected $casts = [
        'initial_rating' => 'integer',
        'rating' => 'integer',
        'peak_rating' => 'integer',
        'games_played' => 'integer',
        'is_provisional' => 'boolean',
        'ever_reached_2400' => 'boolean',
        'last_rated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(StudentChessRatingPeriod::class, 'user_id', 'user_id')
            ->where('rating_type', $this->rating_type);
    }
}

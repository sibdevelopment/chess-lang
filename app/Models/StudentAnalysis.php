<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAnalysis extends Model
{
    use HasFactory;

    protected $table = 'student_analyses';

    protected $fillable = [
        'user_id',
        'name',
        'pgn',
        'reviews',
        'commentary',
        'tree',
        'opened_at',
    ];

    protected $casts = [
        'reviews'    => 'array',
        'commentary' => 'array',
        'tree'       => 'array',
        'opened_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cheap heuristic: count [Event] markers to estimate games-in-PGN.
     */
    public function getGameCountAttribute(): int
    {
        return max(1, substr_count((string) $this->pgn, '[Event '));
    }
}

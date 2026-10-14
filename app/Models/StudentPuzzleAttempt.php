<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPuzzleAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'puzzle_id',
        'status',
        'student_moves',
        'current_ply_index',
        'wrong_attempts',
        'hint_count',
        'current_hint_level',
        'hint_used',
        'current_fen',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'student_moves' => 'array',
        'hint_used' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'points',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function puzzle()
    {
        return $this->belongsTo(Puzzle::class);
    }

    public function getPointsAttribute(): int
    {
        return (count($this->student_moves ?? []) * 2) - (int) $this->wrong_attempts;
    }
}

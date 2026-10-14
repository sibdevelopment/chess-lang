<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Puzzle extends Model
{
    use HasFactory;

    protected $fillable = [
        'puzzle_set_id',
        'puzzle_order',
        'source_pgn',
        'original_fen',
        'side_to_move',
        'answer_pgn',
        'main_line',
        'solution_uci',
        'final_fen',
        'comments',
    ];

    protected $casts = [
        'main_line' => 'array',
        'solution_uci' => 'array',
    ];

    public function puzzleSet()
    {
        return $this->belongsTo(PuzzleSet::class);
    }

    public function attempts()
    {
        return $this->hasMany(StudentPuzzleAttempt::class);
    }
}

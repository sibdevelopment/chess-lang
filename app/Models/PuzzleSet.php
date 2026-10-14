<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PuzzleSet extends Model
{
    use HasFactory;

    public const LEVELS = ['beginner', 'intermediate', 'advanced', 'expert'];

    protected $fillable = [
        'name',
        'level',
        'category_id',
        'category_name',
        'source_pgn',
        'source_file_name',
        'source_file_path',
        'total_puzzles',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(PuzzleCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function puzzles()
    {
        return $this->hasMany(Puzzle::class)->orderBy('puzzle_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function progressForStudent(User $student): array
    {
        $attempts = StudentPuzzleAttempt::query()
            ->where('user_id', $student->id)
            ->whereHas('puzzle', fn (Builder $q) => $q->where('puzzle_set_id', $this->id))
            ->get();

        $completed = $attempts->where('status', 'completed')->count();
        $correctlySolved = $completed;
        $wrongAttempts = (int) $attempts->sum('wrong_attempts');
        $hintsUsed = (int) $attempts->sum('hint_count');
        $totalPoints = (int) $attempts->sum(fn (StudentPuzzleAttempt $attempt) => $attempt->points);
        $lastAttempt = $attempts->sortByDesc('updated_at')->first();
        $progress = $this->total_puzzles > 0
            ? (int) floor(($completed / $this->total_puzzles) * 100)
            : 0;

        return [
            'total_puzzles' => (int) $this->total_puzzles,
            'completed_puzzles' => $completed,
            'correctly_solved' => $correctlySolved,
            'wrong_attempts' => $wrongAttempts,
            'hints_used' => $hintsUsed,
            'total_points' => $totalPoints,
            'progress_percent' => min(100, $progress),
            'last_attempted_puzzle_id' => $lastAttempt?->puzzle_id,
        ];
    }

    public function nextPuzzleForStudent(User $student): ?Puzzle
    {
        $puzzles = $this->puzzles()->with([
            'attempts' => fn ($query) => $query->where('user_id', $student->id),
        ])->get();

        foreach ($puzzles as $puzzle) {
            $attempt = $puzzle->attempts->first();
            if ($attempt && $attempt->status === 'in_progress') {
                return $puzzle;
            }
        }

        foreach ($puzzles as $puzzle) {
            $attempt = $puzzle->attempts->first();
            if (!$attempt || $attempt->status !== 'completed') {
                return $puzzle;
            }
        }

        return $puzzles->first();
    }
}

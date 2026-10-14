<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPerformanceAssessment extends Model
{
    public const SCORE_FIELDS = [
        'opening_knowledge' => 'Opening Knowledge',
        'tactical_awareness' => 'Tactical Awareness',
        'calculation' => 'Calculation',
        'strategy' => 'Strategy',
        'positional_understanding' => 'Positional Understanding',
        'endgame_knowledge' => 'Endgame Knowledge',
        'board_vision' => 'Board Vision',
        'decision_making' => 'Decision Making',
        'time_management' => 'Time Management',
        'game_analysis' => 'Game Analysis',
    ];

    protected $fillable = [
        'student_id',
        'assessor_id',
        'assessor_role',
        'assessed_at',
        'period_start',
        'period_end',
        'opening_knowledge',
        'tactical_awareness',
        'calculation',
        'strategy',
        'positional_understanding',
        'endgame_knowledge',
        'board_vision',
        'decision_making',
        'time_management',
        'game_analysis',
        'total_marks',
        'percentage',
        'report_snapshot',
        'strengths',
        'areas_to_improve',
        'coach_remarks',
        'next_focus',
    ];

    protected $casts = [
        'assessed_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'percentage' => 'float',
        'report_snapshot' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function scopeSavedByCoach(Builder $query): Builder
    {
        return $query->where(function (Builder $coachQuery) {
            $coachQuery->where('assessor_role', 'coach')
                ->orWhere(function (Builder $legacyQuery) {
                    $legacyQuery->whereNull('assessor_role')
                        ->whereHas('assessor', fn (Builder $assessorQuery) => $assessorQuery->where('role', 'coach'));
                });
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $publishedQuery) {
            $publishedQuery->whereIn('assessor_role', ['admin', 'coach'])
                ->orWhere(function (Builder $legacyQuery) {
                    $legacyQuery->whereNull('assessor_role')
                        ->whereHas('assessor', fn (Builder $assessorQuery) => $assessorQuery->whereIn('role', ['admin', 'coach']));
                });
        });
    }

    public static function scoreFields(): array
    {
        return array_keys(self::SCORE_FIELDS);
    }

    public static function labels(): array
    {
        return self::SCORE_FIELDS;
    }
}

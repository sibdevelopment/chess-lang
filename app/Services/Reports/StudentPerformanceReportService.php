<?php

namespace App\Services\Reports;

use App\Models\ClassParticipant;
use App\Models\Group;
use App\Models\HomeworkSubmission;
use App\Models\LiveClass;
use App\Models\QuizSession;
use App\Models\QuizSessionResponse;
use App\Models\StudentPerformanceAssessment;
use App\Models\StudentPuzzleAttempt;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentPerformanceReportService
{
    public function visibleStudentsQuery(User $viewer): Builder
    {
        $query = User::students()
            ->where('is_active', true)
            ->with(['groups' => fn ($groupQuery) => $groupQuery->with('coach:id,name')])
            ->orderBy('name');

        if ($viewer->isCoach()) {
            $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('coach_id', $viewer->id));
        }

        if ($viewer->isStudent()) {
            $query->whereKey($viewer->id);
        }

        return $query;
    }

    public function visiblePublishedAssessmentsQuery(User $viewer): Builder
    {
        $query = StudentPerformanceAssessment::query()
            ->published()
            ->with([
                'student.groups' => fn ($groupQuery) => $groupQuery->with('coach:id,name'),
                'assessor:id,name,username,role',
            ])
            ->whereHas('student', function (Builder $studentQuery) use ($viewer) {
                $studentQuery->where('role', 'student');

                if ($viewer->isCoach()) {
                    $studentQuery->whereHas('groups', fn (Builder $groupQuery) => $groupQuery->where('coach_id', $viewer->id));
                }

                if ($viewer->isStudent()) {
                    $studentQuery->whereKey($viewer->id);
                }
            })
            ->latest('assessed_at')
            ->latest('id');

        return $query;
    }

    public function build(
        User $student,
        bool $publishedOnly = false,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null
    ): array {
        $student->loadMissing(['groups.coach']);

        $displayGroups = $this->displayGroups($student);
        $primaryGroup = $displayGroups->sortBy('name')->first();
        $classes = $this->classPerformance($student, $periodStart, $periodEnd);
        $homework = $this->homeworkPerformance($student, $periodStart, $periodEnd);
        $puzzles = $this->puzzlePerformance($student, $periodStart, $periodEnd);
        $quizzes = $this->quizPerformance($student, $periodStart, $periodEnd);
        $tournaments = $this->tournamentPerformance($student, $periodStart, $periodEnd);
        $latestAssessment = StudentPerformanceAssessment::query()
            ->with('assessor:id,name,username')
            ->where('student_id', $student->id)
            ->when($publishedOnly, fn (Builder $query) => $query->published())
            ->latest('assessed_at')
            ->latest('id')
            ->first();

        return [
            'student' => $student,
            'profile' => $this->profile($student, $displayGroups, $primaryGroup, $periodStart, $periodEnd),
            'period' => $this->periodDetails($periodStart, $periodEnd),
            'classes' => $classes,
            'homework' => $homework,
            'puzzles' => $puzzles,
            'quizzes' => $quizzes,
            'tournaments' => $tournaments,
            'latest_assessment' => $latestAssessment,
            'assessment_labels' => StudentPerformanceAssessment::labels(),
            'assessment_references' => $this->assessmentReferences($primaryGroup, $classes, $homework, $puzzles, $quizzes, $tournaments),
        ];
    }

    public function captureSnapshot(User $student, ?Carbon $periodStart = null, ?Carbon $periodEnd = null): array
    {
        return Arr::only($this->build($student, false, $periodStart, $periodEnd), [
            'profile',
            'period',
            'classes',
            'homework',
            'puzzles',
            'quizzes',
            'tournaments',
            'assessment_references',
        ]);
    }

    public function buildSaved(User $student, StudentPerformanceAssessment $assessment): array
    {
        $assessment->loadMissing('assessor:id,name,username,role');
        [$periodStart, $periodEnd] = $this->assessmentPeriodBounds($assessment);
        $report = $this->build($student, true, $periodStart, $periodEnd);
        $snapshot = is_array($assessment->report_snapshot) ? $assessment->report_snapshot : [];

        foreach (['profile', 'period', 'classes', 'homework', 'puzzles', 'quizzes', 'tournaments', 'assessment_references'] as $section) {
            if (isset($snapshot[$section]) && is_array($snapshot[$section])) {
                $report[$section] = $snapshot[$section];
            }
        }

        $report['latest_assessment'] = $assessment;

        return $report;
    }

    protected function displayGroups(User $student): Collection
    {
        $activeGroups = $student->groups->where('is_active', true)->values();

        return $activeGroups->isNotEmpty() ? $activeGroups : $student->groups->values();
    }

    protected function profile(
        User $student,
        Collection $groups,
        ?Group $primaryGroup,
        ?Carbon $periodStart,
        ?Carbon $periodEnd
    ): array {
        $coachNames = $groups->pluck('coach.name')->filter()->unique()->values();

        return [
            'name' => $student->name,
            'username' => $student->username,
            'country' => User::studentCountryLabel($student->country),
            'current_batch' => $groups->pluck('name')->filter()->unique()->join(', ') ?: '—',
            'coach' => $coachNames->join(', ') ?: '—',
            'current_level' => $primaryGroup
                ? Str::headline($primaryGroup->getCurrentCurriculumLevel() ?? $primaryGroup->level)
                : '—',
            'status' => $this->statusLabel($student),
            'report_date' => now()->timezone(config('app.timezone'))->format('d M Y'),
            'report_period' => $this->periodDetails($periodStart, $periodEnd)['label'],
            'last_activity' => $this->lastActivityLabel($student, $periodStart, $periodEnd),
        ];
    }

    protected function classPerformance(User $student, ?Carbon $periodStart, ?Carbon $periodEnd): array
    {
        $groupIds = $student->groups->pluck('id')->unique()->values();
        if ($groupIds->isEmpty()) {
            return $this->zeroClassPerformance();
        }

        $historicalClasses = LiveClass::query()
            ->whereIn('group_id', $groupIds)
            ->where('status', '!=', 'cancelled')
            ->whereRaw('COALESCE(scheduled_start_at, scheduled_for) <= ?', [now()->utc()->format('Y-m-d H:i:s')])
            ->tap(fn (Builder $query) => $this->applyPeriod(
                $query,
                'COALESCE(scheduled_start_at, scheduled_for, created_at)',
                $periodStart,
                $periodEnd
            ))
            ->get();

        $attendanceRows = ClassParticipant::query()
            ->with('liveClass')
            ->where('user_id', $student->id)
            ->whereHas('liveClass', function (Builder $query) use ($groupIds, $periodStart, $periodEnd) {
                $query->whereIn('group_id', $groupIds)
                    ->where('status', '!=', 'cancelled');
                $this->applyPeriod(
                    $query,
                    'COALESCE(scheduled_start_at, scheduled_for, created_at)',
                    $periodStart,
                    $periodEnd
                );
            })
            ->get();

        $attendedIds = $attendanceRows->pluck('live_class_id')->unique();
        $completedClasses = $historicalClasses->filter(fn (LiveClass $class) => $class->isCompleted());

        $lateJoins = $attendanceRows
            ->filter(function (ClassParticipant $participant) {
                $class = $participant->liveClass;
                if (! $class || ! $participant->joined_at) {
                    return false;
                }

                $start = $class->actualStart() ?? $class->scheduledStart();

                return $start && $participant->joined_at->gt($start);
            })
            ->count();

        $learningMinutes = $historicalClasses
            ->filter(fn (LiveClass $class) => $attendedIds->contains($class->id) && $class->isCompleted())
            ->sum(function (LiveClass $class) {
                if ($class->duration_minutes) {
                    return (int) $class->duration_minutes;
                }

                $start = $class->actualStart() ?? $class->scheduledStart();
                $end = $class->actualEnd() ?? $class->scheduledEnd();

                return $start && $end ? $start->diffInMinutes($end) : 0;
            });

        $totalScheduled = $historicalClasses->count();
        $classesAttended = $attendedIds->count();
        $classesMissed = $completedClasses
            ->reject(fn (LiveClass $class) => $attendedIds->contains($class->id))
            ->count();

        return [
            'total_classes_scheduled' => $totalScheduled,
            'classes_attended' => $classesAttended,
            'classes_missed' => $classesMissed,
            'attendance_percent' => $totalScheduled > 0 ? round(($classesAttended / $totalScheduled) * 100, 1) : 0,
            'total_learning_hours' => round($learningMinutes / 60, 1),
        ];
    }

    protected function homeworkPerformance(User $student, ?Carbon $periodStart, ?Carbon $periodEnd): array
    {
        $submissions = HomeworkSubmission::query()
            ->with('homework:id,due_date,created_at')
            ->where('user_id', $student->id)
            ->whereHas('homework', fn (Builder $query) => $this->applyPeriod(
                $query,
                'created_at',
                $periodStart,
                $periodEnd
            ))
            ->get();

        $assigned = $submissions->count();
        $completed = $submissions->where('status', 'done')->count();
        $missed = $submissions->filter(function (HomeworkSubmission $submission) {
            return $submission->status !== 'done'
                && $submission->homework?->due_date
                && $submission->homework->due_date->isPast();
        })->count();
        $pending = $submissions->filter(function (HomeworkSubmission $submission) {
            return $submission->status !== 'done'
                && (! $submission->homework?->due_date || $submission->homework->due_date->isFuture() || $submission->homework->due_date->isToday());
        })->count();

        $graded = $submissions->filter(fn (HomeworkSubmission $submission) => $submission->marked_correct !== null);
        $averageAccuracy = $graded->isNotEmpty()
            ? round(($graded->where('marked_correct', true)->count() / $graded->count()) * 100, 1)
            : 0;

        return [
            'homework_assigned' => $assigned,
            'homework_completed' => $completed,
            'homework_pending' => $pending,
            'homework_missed' => $missed,
            'completion_percent' => $assigned > 0 ? round(($completed / $assigned) * 100, 1) : 0,
            'average_accuracy_percent' => $averageAccuracy,
        ];
    }

    protected function puzzlePerformance(User $student, ?Carbon $periodStart, ?Carbon $periodEnd): array
    {
        $attempts = StudentPuzzleAttempt::query()
            ->where('user_id', $student->id)
            ->whereHas('puzzle.puzzleSet', fn (Builder $query) => $query->where('is_active', true))
            ->tap(fn (Builder $query) => $this->applyPeriod(
                $query,
                'COALESCE(completed_at, started_at, updated_at, created_at)',
                $periodStart,
                $periodEnd
            ))
            ->get();

        $attempted = $attempts->filter(fn (StudentPuzzleAttempt $attempt) => $attempt->status !== 'not_started')->count();
        $correct = $attempts->where('status', 'completed')->count();
        $wrong = (int) $attempts->sum('wrong_attempts');

        return [
            'puzzles_attempted' => $attempted,
            'correct' => $correct,
            'wrong' => $wrong,
            'accuracy_percent' => $attempted > 0 ? round(($correct / $attempted) * 100, 1) : 0,
            'hints_used' => (int) $attempts->sum('hint_count'),
        ];
    }

    protected function quizPerformance(User $student, ?Carbon $periodStart, ?Carbon $periodEnd): array
    {
        $sessions = QuizSession::query()
            ->with('quizQueue')
            ->where(function (Builder $query) use ($student) {
                $query->whereHas('attendees', fn (Builder $attendeeQuery) => $attendeeQuery->where('user_id', $student->id))
                    ->orWhereHas('responses', fn (Builder $responseQuery) => $responseQuery->where('user_id', $student->id));
            })
            ->tap(fn (Builder $query) => $this->applyPeriod(
                $query,
                'scheduled_at',
                $periodStart,
                $periodEnd
            ))
            ->get();

        if ($sessions->isEmpty()) {
            return [
                'quiz_sessions_played' => 0,
                'average_score' => 0,
                'highest_score' => 0,
                'quiz_accuracy_percent' => 0,
            ];
        }

        $sessionIds = $sessions->pluck('id');
        $sessions = $sessions->keyBy('id');

        $responses = QuizSessionResponse::query()
            ->where('user_id', $student->id)
            ->whereIn('quiz_session_id', $sessionIds)
            ->get()
            ->groupBy('quiz_session_id');

        $scores = [];
        $totalCorrect = 0;
        $totalAnswers = 0;

        foreach ($sessionIds as $sessionId) {
            $session = $sessions->get($sessionId);
            $sessionResponses = $responses->get($sessionId, collect());
            $questionCount = max((int) ($session?->quizQueue?->count() ?? 0), $sessionResponses->count());
            $correct = $sessionResponses->where('is_correct', true)->count();

            $totalCorrect += $correct;
            $totalAnswers += $sessionResponses->count();
            $scores[] = $questionCount > 0 ? round(($correct / $questionCount) * 100, 1) : 0;
        }

        return [
            'quiz_sessions_played' => count($scores),
            'average_score' => round(collect($scores)->avg() ?? 0, 1),
            'highest_score' => round(collect($scores)->max() ?? 0, 1),
            'quiz_accuracy_percent' => $totalAnswers > 0 ? round(($totalCorrect / $totalAnswers) * 100, 1) : 0,
        ];
    }

    protected function tournamentPerformance(User $student, ?Carbon $periodStart, ?Carbon $periodEnd): array
    {
        $student->loadMissing('groups');

        $tournaments = Tournament::query()
            ->with(['participants.user', 'pairings', 'targetGroups:id', 'targetUsers:id'])
            ->tap(fn (Builder $query) => $this->applyPeriod(
                $query,
                'scheduled_at',
                $periodStart,
                $periodEnd
            ))
            ->orderBy('scheduled_at')
            ->get();

        $joinedTournaments = $tournaments->filter(fn (Tournament $tournament) => $tournament->participants->contains('user_id', $student->id));
        $eligiblePastTournaments = $tournaments->filter(function (Tournament $tournament) use ($student) {
            return $tournament->isTerminal()
                && $tournament->scheduled_at
                && $tournament->scheduled_at->isPast()
                && $tournament->isStudentEligible($student);
        });

        $wins = 0;
        $draws = 0;
        $losses = 0;
        $bestRank = null;
        $points = 0.0;

        foreach ($joinedTournaments as $tournament) {
            $participant = $tournament->participants->firstWhere('user_id', $student->id);
            if ($participant) {
                $points += (float) $participant->score;
            }

            foreach ($tournament->pairings as $pairing) {
                if ($pairing->status !== 'completed' || $pairing->is_bye) {
                    continue;
                }

                $isWhite = (int) $pairing->white_user_id === (int) $student->id;
                $isBlack = (int) $pairing->black_user_id === (int) $student->id;

                if (! $isWhite && ! $isBlack) {
                    continue;
                }

                if (($isWhite && $pairing->result === '1-0') || ($isBlack && $pairing->result === '0-1')) {
                    $wins++;
                } elseif ($pairing->result === '0.5-0.5') {
                    $draws++;
                } else {
                    $losses++;
                }
            }

            if ($tournament->isCompleted()) {
                $standings = $tournament->standings();
                $position = $standings->search(fn ($row) => (int) $row->user_id === (int) $student->id);

                if ($position !== false) {
                    $bestRank = $bestRank === null ? $position + 1 : min($bestRank, $position + 1);
                }
            }
        }

        return [
            'tournament_played' => $joinedTournaments->count(),
            'tournament_missed' => $eligiblePastTournaments
                ->reject(fn (Tournament $tournament) => $tournament->participants->contains('user_id', $student->id))
                ->count(),
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'tournament_points' => round($points, 1),
            'best_rank' => $bestRank,
            'tournament_participation' => $eligiblePastTournaments->count() > 0
                ? $joinedTournaments->count().' / '.$eligiblePastTournaments->count()
                : (string) $joinedTournaments->count(),
            'tournament_performance_percent' => ($wins + $draws + $losses) > 0
                ? round((($wins + ($draws * 0.5)) / ($wins + $draws + $losses)) * 100, 1)
                : 0,
        ];
    }

    protected function assessmentReferences(?Group $primaryGroup, array $classes, array $homework, array $puzzles, array $quizzes, array $tournaments): array
    {
        $currentLevel = $primaryGroup
            ? Str::headline($primaryGroup->getCurrentCurriculumLevel() ?? $primaryGroup->level)
            : '—';
        $timeReference = round(($quizzes['quiz_accuracy_percent'] + $tournaments['tournament_performance_percent']) / 2, 1);

        return [
            'opening_knowledge' => [
                'label' => 'Current Level',
                'value' => $currentLevel,
            ],
            'tactical_awareness' => [
                'label' => 'Puzzle Accuracy',
                'value' => number_format($puzzles['accuracy_percent'], 1).'%',
            ],
            'calculation' => [
                'label' => 'Puzzle Accuracy',
                'value' => number_format($puzzles['accuracy_percent'], 1).'%',
            ],
            'strategy' => [
                'label' => 'Homework Accuracy',
                'value' => number_format($homework['average_accuracy_percent'], 1).'%',
            ],
            'positional_understanding' => [
                'label' => 'Current Level',
                'value' => $currentLevel,
            ],
            'endgame_knowledge' => [
                'label' => 'Current Level',
                'value' => $currentLevel,
            ],
            'board_vision' => [
                'label' => 'Puzzle Accuracy',
                'value' => number_format($puzzles['accuracy_percent'], 1).'%',
            ],
            'decision_making' => [
                'label' => 'Quiz Average',
                'value' => number_format($quizzes['average_score'], 1).'%',
            ],
            'time_management' => [
                'label' => 'Tournament / Quiz Timing Data',
                'value' => number_format($timeReference, 1).'%',
            ],
            'game_analysis' => [
                'label' => 'Homework Accuracy',
                'value' => number_format($homework['average_accuracy_percent'], 1).'%',
            ],
        ];
    }

    protected function lastActivityLabel(User $student, ?Carbon $periodStart, ?Carbon $periodEnd): string
    {
        $studentLastSeen = $student->last_seen;
        if ($studentLastSeen && ! $this->timestampInPeriod($studentLastSeen, $periodStart, $periodEnd)) {
            $studentLastSeen = null;
        }

        $timestamps = collect([
            $studentLastSeen,
            $this->latestInPeriod(
                ClassParticipant::query()->where('user_id', $student->id),
                'COALESCE(last_seen_at, joined_at)',
                $periodStart,
                $periodEnd
            ),
            $this->latestInPeriod(
                HomeworkSubmission::query()->where('user_id', $student->id),
                'COALESCE(submitted_at, updated_at, created_at)',
                $periodStart,
                $periodEnd
            ),
            $this->latestInPeriod(
                StudentPuzzleAttempt::query()->where('user_id', $student->id),
                'COALESCE(completed_at, started_at, updated_at, created_at)',
                $periodStart,
                $periodEnd
            ),
            $this->latestInPeriod(
                QuizSessionResponse::query()->where('user_id', $student->id),
                'COALESCE(picked_at, updated_at, created_at)',
                $periodStart,
                $periodEnd
            ),
            $this->latestInPeriod(
                TournamentPairing::query()
                    ->where(function (Builder $query) use ($student) {
                        $query->where('white_user_id', $student->id)
                            ->orWhere('black_user_id', $student->id);
                    }),
                'COALESCE(completed_at, last_move_at, updated_at, created_at)',
                $periodStart,
                $periodEnd
            ),
        ])->filter();

        if ($timestamps->isEmpty()) {
            return '—';
        }

        $latest = $timestamps
            ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
            ->sort()
            ->last();

        return $latest?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '—';
    }

    protected function periodDetails(?Carbon $periodStart, ?Carbon $periodEnd): array
    {
        if (! $periodStart || ! $periodEnd) {
            return [
                'start' => null,
                'end' => null,
                'label' => 'All Time',
            ];
        }

        $start = $periodStart->copy()->timezone(config('app.timezone'));
        $end = $periodEnd->copy()->timezone(config('app.timezone'));

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'label' => $start->format('d M Y').' to '.$end->format('d M Y'),
        ];
    }

    protected function assessmentPeriodBounds(StudentPerformanceAssessment $assessment): array
    {
        if (! $assessment->period_start || ! $assessment->period_end) {
            return [null, null];
        }

        return [
            Carbon::createFromFormat(
                'Y-m-d',
                $assessment->period_start->format('Y-m-d'),
                config('app.timezone')
            )->startOfDay()->utc(),
            Carbon::createFromFormat(
                'Y-m-d',
                $assessment->period_end->format('Y-m-d'),
                config('app.timezone')
            )->endOfDay()->utc(),
        ];
    }

    protected function applyPeriod(
        Builder $query,
        string $timestampExpression,
        ?Carbon $periodStart,
        ?Carbon $periodEnd
    ): Builder {
        return $query
            ->when(
                $periodStart,
                fn (Builder $rangeQuery) => $rangeQuery->whereRaw(
                    $timestampExpression.' >= ?',
                    [$periodStart->format('Y-m-d H:i:s')]
                )
            )
            ->when(
                $periodEnd,
                fn (Builder $rangeQuery) => $rangeQuery->whereRaw(
                    $timestampExpression.' <= ?',
                    [$periodEnd->format('Y-m-d H:i:s')]
                )
            );
    }

    protected function latestInPeriod(
        Builder $query,
        string $timestampExpression,
        ?Carbon $periodStart,
        ?Carbon $periodEnd
    ): mixed {
        return $this->applyPeriod($query, $timestampExpression, $periodStart, $periodEnd)
            ->selectRaw('MAX('.$timestampExpression.') as latest_activity')
            ->value('latest_activity');
    }

    protected function timestampInPeriod(Carbon $timestamp, ?Carbon $periodStart, ?Carbon $periodEnd): bool
    {
        return (! $periodStart || $timestamp->gte($periodStart))
            && (! $periodEnd || $timestamp->lte($periodEnd));
    }

    protected function zeroClassPerformance(): array
    {
        return [
            'total_classes_scheduled' => 0,
            'classes_attended' => 0,
            'classes_missed' => 0,
            'attendance_percent' => 0,
            'total_learning_hours' => 0,
        ];
    }

    protected function statusLabel(User $student): string
    {
        return match ($student->studentLifecycleStatus()) {
            'new_id' => 'New ID',
            'inactive' => 'Inactive',
            default => 'Active',
        };
    }
}

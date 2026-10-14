<?php

namespace App\Console\Commands;

use App\Models\ArcherBadge;
use App\Models\ArcherMission;
use App\Models\ArcherMonthlyChallenge;
use App\Models\HomeworkSubmission;
use App\Models\LiveClass;
use App\Models\StudentPuzzleAttempt;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\User;
use App\Services\ArcherJourney\ArcherJourneyEngine;
use App\Services\ArcherJourney\ArcherLmsEventBridge;
use App\Services\ArcherJourney\FeatureFlagService;
use App\Services\ArcherJourney\FideRatingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MaintainArcherJourney extends Command
{
    protected $signature = 'archer:maintain {--backfill : Import existing LMS history into Archer Journey}';

    protected $description = 'Maintain recurring Archer missions/challenges, monthly attendance, and optional historical backfill.';

    public function handle(ArcherLmsEventBridge $bridge, FideRatingService $ratings): int
    {
        if (! app(FeatureFlagService::class)->enabled('archer_stars_enabled')) {
            $this->warn('Archer Journey is disabled.');

            return self::SUCCESS;
        }

        $this->ensureCurrentWeekMissions();
        $this->ensureCurrentMonthChallenge();
        $this->awardPreviousMonthAttendance();

        if ($this->option('backfill')) {
            $this->backfill($bridge, $ratings);
        }

        $this->info('Archer Journey maintenance complete.');

        return self::SUCCESS;
    }

    protected function ensureCurrentWeekMissions(): void
    {
        $now = CarbonImmutable::now('UTC');
        $start = $now->startOfWeek()->startOfDay();
        $end = $now->endOfWeek()->endOfDay();
        $templates = [
            ['Puzzle Mission', 'Solve 10 puzzles this week.', '🧩', ArcherJourneyEngine::PUZZLE_COMPLETED, 10, 15],
            ['Attendance Mission', 'Attend 3 classes this week.', '🏹', ArcherJourneyEngine::CLASS_ATTENDED, 3, 15],
            ['Tournament Mission', 'Play 3 tournament games this week.', '🏆', ArcherJourneyEngine::TOURNAMENT_GAME_PLAYED, 3, 15],
            ['Homework Mission', 'Complete assigned homework this week.', '📘', ArcherJourneyEngine::HOMEWORK_COMPLETED, 1, 15],
            ['Endgame Mission', 'Complete an endgame puzzle challenge.', '♔', ArcherJourneyEngine::ENDGAME_PUZZLE_COMPLETED, 1, 20],
        ];

        foreach ($templates as [$name, $description, $icon, $event, $target, $reward]) {
            ArcherMission::firstOrCreate(
                ['name' => $name, 'starts_at' => $start, 'ends_at' => $end],
                [
                    'description' => $description,
                    'icon' => $icon,
                    'criteria_event_type' => $event,
                    'target' => $target,
                    'reward_stars' => $reward,
                    'criteria' => ['recurrence' => 'weekly_system'],
                    'is_active' => true,
                ]
            );
        }
    }

    protected function ensureCurrentMonthChallenge(): void
    {
        $now = CarbonImmutable::now('UTC');
        $start = $now->startOfMonth();
        $code = $start->format('Y-m').'-challenge';
        if (ArcherMonthlyChallenge::where('code', $code)->exists()) {
            return;
        }

        $badge = ArcherBadge::firstOrCreate(
            ['code' => $code],
            [
                'name' => $start->format('F Y').' Archer',
                'description' => 'Completed the '.$start->format('F Y').' Archer Challenge.',
                'icon' => '🏹',
                'criteria_target' => 1,
                'criteria' => [],
                'is_automatic' => false,
                'is_active' => true,
            ]
        );
        $template = ArcherMonthlyChallenge::orderByDesc('month_start')->first();
        ArcherMonthlyChallenge::create([
            'code' => $code,
            'name' => strtoupper($start->format('F')).' Challenge',
            'description' => $template?->description ?? 'Build a balanced month of classes, puzzles, tournaments and homework.',
            'icon' => $template?->icon ?? '🏹',
            'month_start' => $start->toDateString(),
            'month_end' => $start->endOfMonth()->toDateString(),
            'criteria' => $template?->criteria ?? [
                ArcherJourneyEngine::CLASS_ATTENDED => 8,
                ArcherJourneyEngine::PUZZLE_COMPLETED => 10,
                ArcherJourneyEngine::TOURNAMENT_PARTICIPATED => 1,
                ArcherJourneyEngine::HOMEWORK_COMPLETED => 1,
            ],
            'reward_stars' => $template?->reward_stars ?? 25,
            'badge_id' => $badge->id,
            'is_active' => true,
        ]);
    }

    protected function awardPreviousMonthAttendance(): void
    {
        User::students()->active()->select(['id', 'name', 'timezone'])->chunkById(250, function ($students) {
            foreach ($students as $student) {
                $timezone = $student->preferredTimezone();
                $month = CarbonImmutable::now($timezone)->subMonthNoOverflow();
                $startUtc = $month->startOfMonth()->startOfDay()->utc();
                $endUtc = $month->endOfMonth()->endOfDay()->utc();
                $groupIds = $student->groups()->pluck('groups.id');
                $classIds = LiveClass::whereIn('group_id', $groupIds)
                    ->whereIn('status', ['ended', 'completed'])
                    ->whereBetween(DB::raw('COALESCE(actual_end_at, ended_at)'), [$startUtc, $endUtc])
                    ->pluck('id');
                if ($classIds->isEmpty()) {
                    continue;
                }

                $attended = DB::table('class_participants')
                    ->where('user_id', $student->id)
                    ->whereIn('live_class_id', $classIds)
                    ->distinct()
                    ->count('live_class_id');
                if ($attended !== $classIds->count()) {
                    continue;
                }

                app(ArcherJourneyEngine::class)->record(
                    ArcherJourneyEngine::MONTHLY_ATTENDANCE_100,
                    $student,
                    'monthly_attendance',
                    [
                        'title' => '100% attendance in '.$month->format('F Y'),
                        'month' => $month->format('Y-m'),
                        'classes' => $attended,
                    ],
                    null,
                    $endUtc,
                    $timezone,
                    'monthly_attendance_100:'.$student->id.':'.$month->format('Y-m'),
                );
            }
        });
    }

    protected function backfill(ArcherLmsEventBridge $bridge, FideRatingService $ratings): void
    {
        $this->line('Backfilling classes…');
        LiveClass::whereIn('status', ['ended', 'completed'])->chunkById(100, fn ($classes) => $classes->each(fn ($class) => $bridge->classCompleted($class)));
        $this->line('Backfilling homework…');
        HomeworkSubmission::where('status', 'done')->chunkById(200, fn ($submissions) => $submissions->each(fn ($submission) => $bridge->homeworkCompleted($submission)));
        $this->line('Backfilling puzzles…');
        StudentPuzzleAttempt::with('puzzle')->where('status', 'completed')->chunkById(200, fn ($attempts) => $attempts->each(fn ($attempt) => $bridge->puzzleCompleted($attempt, $attempt->puzzle)));
        $this->line('Backfilling tournament activity…');
        Tournament::whereIn('status', [Tournament::STATUS_LIVE, Tournament::STATUS_COMPLETED])->chunkById(50, fn ($tournaments) => $tournaments->each(fn ($tournament) => $bridge->tournamentStarted($tournament)));
        TournamentPairing::where('status', 'completed')->where('is_bye', false)->chunkById(100, fn ($pairings) => $pairings->each(fn ($pairing) => $bridge->tournamentGameCompleted($pairing)));
        Tournament::where('status', Tournament::STATUS_COMPLETED)->chunkById(50, fn ($tournaments) => $tournaments->each(fn ($tournament) => $ratings->processTournament($tournament)));
    }
}

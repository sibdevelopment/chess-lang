<?php

namespace App\Services\ArcherJourney;

use App\Models\ArcherAchievement;
use App\Models\ArcherActivityEvent;
use App\Models\ArcherBadgeAward;
use App\Models\ArcherLevel;
use App\Models\ArcherMission;
use App\Models\ArcherMissionProgress;
use App\Models\ArcherMonthlyChallenge;
use App\Models\ArcherMonthlyChallengeProgress;
use App\Models\ArcherNotification;
use App\Models\ArcherNotificationPreference;
use App\Models\ArcherPassportEntry;
use App\Models\ArcherStarBalance;
use App\Models\StudentChessRating;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArcherJourneyViewService
{
    public function profile(User $student): array
    {
        $balance = ArcherStarBalance::firstOrNew(
            ['user_id' => $student->id],
            ['lifetime_stars' => 0, 'current_streak' => 0, 'longest_streak' => 0]
        );
        $levels = ArcherLevel::active()->orderBy('minimum_stars')->get();
        $currentLevel = $levels->where('minimum_stars', '<=', $balance->lifetime_stars)->last() ?? $levels->first();
        $nextLevel = $levels->first(fn (ArcherLevel $level) => $level->minimum_stars > $balance->lifetime_stars);
        $mission = $this->currentMission($student, $currentLevel?->id);
        $challenge = $this->currentChallenge($student, $currentLevel?->id);

        return [
            'student' => $student,
            'balance' => $balance,
            'levels' => $levels,
            'currentLevel' => $currentLevel,
            'nextLevel' => $nextLevel,
            'starsToNextLevel' => $nextLevel ? max(0, $nextLevel->minimum_stars - $balance->lifetime_stars) : 0,
            'levelProgress' => $this->levelProgress($balance->lifetime_stars, $currentLevel, $nextLevel),
            'badges' => ArcherBadgeAward::with('badge')->where('user_id', $student->id)->latest('earned_at')->get(),
            'ratings' => StudentChessRating::where('user_id', $student->id)->get()->keyBy('rating_type'),
            'tournamentCount' => TournamentParticipant::where('user_id', $student->id)->where('withdrawn', false)->count(),
            'mission' => $mission,
            'challenge' => $challenge,
            'recentAchievements' => ArcherAchievement::where('user_id', $student->id)->latest('awarded_at')->limit(5)->get(),
            'notifications' => ArcherNotification::where('user_id', $student->id)->latest()->limit(8)->get(),
            'notificationPreferences' => ArcherNotificationPreference::firstOrNew(
                ['user_id' => $student->id],
                ['in_app_enabled' => true, 'level_updates' => true, 'badge_updates' => true, 'mission_updates' => true, 'tournament_updates' => true]
            ),
            'passport' => ArcherPassportEntry::where('user_id', $student->id)->latest('occurred_at')->paginate(20),
            'starsThisMonth' => (int) DB::table('archer_star_period_totals')
                ->where('user_id', $student->id)
                ->where('period_type', 'month')
                ->where('period_key', now($student->preferredTimezone())->format('Y-m'))
                ->value('stars'),
            'activityCounts' => ArcherActivityEvent::query()
                ->where('user_id', $student->id)
                ->whereIn('event_type', [
                    ArcherJourneyEngine::CLASS_ATTENDED,
                    ArcherJourneyEngine::HOMEWORK_COMPLETED,
                    ArcherJourneyEngine::PUZZLE_COMPLETED,
                    ArcherJourneyEngine::TOURNAMENT_GAME_PLAYED,
                    ArcherJourneyEngine::TOURNAMENT_WON,
                ])
                ->select('event_type', DB::raw('COUNT(*) as total'))
                ->groupBy('event_type')
                ->pluck('total', 'event_type')
                ->map(fn ($total) => (int) $total),
        ];
    }

    public function leaderboard(User $viewer, string $type = 'stars', string $scope = 'global'): Collection
    {
        $type = in_array($type, ['stars', 'tournament', 'improved', 'puzzle', 'achievement'], true) ? $type : 'stars';
        $scope = in_array($scope, ['global', 'country', 'level', 'class'], true) ? $scope : 'global';
        $eligible = $this->eligibleStudentsQuery($viewer, $scope);

        $rows = match ($type) {
            'tournament' => $this->eventLeaderboard($eligible, ArcherJourneyEngine::TOURNAMENT_WON, 'wins'),
            'puzzle' => $this->eventLeaderboard($eligible, ArcherJourneyEngine::PUZZLE_COMPLETED, 'puzzles'),
            'achievement' => $this->achievementLeaderboard($eligible),
            'improved' => $this->improvedLeaderboard($eligible),
            default => $this->starsLeaderboard($eligible),
        };

        return $rows->values()->map(function ($row, $index) use ($viewer) {
            $parts = preg_split('/\s+/', trim((string) $row->name)) ?: [];
            $displayName = $parts[0] ?? 'Archer';
            if (count($parts) > 1) {
                $displayName .= ' '.mb_substr(end($parts), 0, 1).'.';
            }

            return (object) [
                'rank' => $index + 1,
                'user_id' => (int) $row->user_id,
                'display_name' => $displayName,
                'country' => $row->country,
                'score' => (int) $row->score,
                'is_viewer' => (int) $row->user_id === (int) $viewer->id,
            ];
        });
    }

    protected function currentMission(User $student, ?int $levelId): ?object
    {
        $mission = ArcherMission::activeNow()
            ->where(fn ($query) => $query->whereNull('applicable_country')->orWhere('applicable_country', $student->country))
            ->where(fn ($query) => $query->whereNull('applicable_level_id')->orWhere('applicable_level_id', $levelId))
            ->with(['progressRows' => fn ($query) => $query->where('user_id', $student->id)])
            ->orderBy('ends_at')
            ->get()
            ->sortBy(fn (ArcherMission $mission) => $mission->progressRows->first()?->status === 'completed' ? 1 : 0)
            ->first();

        if (! $mission) {
            return null;
        }

        $progress = $mission->progressRows->first() ?? new ArcherMissionProgress(['progress' => 0, 'status' => 'active']);

        return (object) [
            'model' => $mission,
            'progress' => min($mission->target, $progress->progress),
            'percent' => min(100, (int) round(($progress->progress / max(1, $mission->target)) * 100)),
            'completed' => $progress->status === 'completed',
        ];
    }

    protected function currentChallenge(User $student, ?int $levelId): ?object
    {
        $challenge = ArcherMonthlyChallenge::activeNow()
            ->where(fn ($query) => $query->whereNull('applicable_country')->orWhere('applicable_country', $student->country))
            ->where(fn ($query) => $query->whereNull('applicable_level_id')->orWhere('applicable_level_id', $levelId))
            ->first();
        if (! $challenge) {
            return null;
        }

        $progress = ArcherMonthlyChallengeProgress::where('challenge_id', $challenge->id)
            ->where('user_id', $student->id)
            ->first();

        return (object) [
            'model' => $challenge,
            'progress' => $progress?->progress ?? [],
            'completed' => (bool) $progress?->completed_at,
        ];
    }

    protected function levelProgress(int $stars, ?ArcherLevel $current, ?ArcherLevel $next): int
    {
        if (! $current || ! $next) {
            return $current ? 100 : 0;
        }

        return min(100, max(0, (int) round((($stars - $current->minimum_stars) / max(1, $next->minimum_stars - $current->minimum_stars)) * 100)));
    }

    protected function eligibleStudentsQuery(User $viewer, string $scope): Builder
    {
        $query = DB::table('users')->where('users.role', 'student')->where('users.is_active', true);
        if ($scope === 'country' && filled($viewer->country)) {
            $query->where('users.country', $viewer->country);
        }
        if ($scope === 'class') {
            $groupIds = $viewer->groups()->pluck('groups.id');
            $query->whereIn('users.id', DB::table('group_student')->select('user_id')->whereIn('group_id', $groupIds));
        }
        if ($scope === 'level') {
            $stars = (int) ArcherStarBalance::where('user_id', $viewer->id)->value('lifetime_stars');
            $level = ArcherLevel::active()->where('minimum_stars', '<=', $stars)->orderByDesc('minimum_stars')->first();
            $nextMinimum = ArcherLevel::active()->where('minimum_stars', '>', $level?->minimum_stars ?? -1)->min('minimum_stars');
            $minimum = $level?->minimum_stars ?? 0;
            $query->where(function ($levelQuery) use ($minimum, $nextMinimum) {
                $levelQuery->whereIn('users.id', DB::table('archer_star_balances')->select('user_id')
                    ->where('lifetime_stars', '>=', $minimum)
                    ->when($nextMinimum, fn ($balanceQuery) => $balanceQuery->where('lifetime_stars', '<', $nextMinimum)));
                if ($minimum === 0) {
                    $levelQuery->orWhereNotExists(fn ($balanceQuery) => $balanceQuery
                        ->selectRaw('1')
                        ->from('archer_star_balances')
                        ->whereColumn('archer_star_balances.user_id', 'users.id'));
                }
            });
        }

        return $query;
    }

    protected function starsLeaderboard(Builder $eligible): Collection
    {
        return $eligible->leftJoin('archer_star_balances', 'users.id', '=', 'archer_star_balances.user_id')
            ->select('users.id as user_id', 'users.name', 'users.country', DB::raw('COALESCE(archer_star_balances.lifetime_stars, 0) as score'))
            ->orderByDesc('score')->orderBy('users.name')->limit(50)->get();
    }

    protected function eventLeaderboard(Builder $eligible, string $eventType, string $alias): Collection
    {
        return $eligible->leftJoin('archer_activity_events', function ($join) use ($eventType) {
            $join->on('users.id', '=', 'archer_activity_events.user_id')->where('archer_activity_events.event_type', $eventType);
        })->groupBy('users.id', 'users.name', 'users.country')
            ->select('users.id as user_id', 'users.name', 'users.country', DB::raw('COUNT(archer_activity_events.id) as score'))
            ->orderByDesc('score')->orderBy('users.name')->limit(50)->get();
    }

    protected function achievementLeaderboard(Builder $eligible): Collection
    {
        return $eligible->leftJoin('archer_achievements', 'users.id', '=', 'archer_achievements.user_id')
            ->groupBy('users.id', 'users.name', 'users.country')
            ->select('users.id as user_id', 'users.name', 'users.country', DB::raw('COUNT(archer_achievements.id) as score'))
            ->orderByDesc('score')->orderBy('users.name')->limit(50)->get();
    }

    protected function improvedLeaderboard(Builder $eligible): Collection
    {
        return $eligible->leftJoin('student_chess_ratings', 'users.id', '=', 'student_chess_ratings.user_id')
            ->groupBy('users.id', 'users.name', 'users.country')
            ->select('users.id as user_id', 'users.name', 'users.country', DB::raw('COALESCE(MAX(student_chess_ratings.rating - student_chess_ratings.initial_rating), 0) as score'))
            ->orderByDesc('score')->orderBy('users.name')->limit(50)->get();
    }
}

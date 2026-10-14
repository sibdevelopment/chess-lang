<?php

namespace App\Services\ArcherJourney;

use App\Models\StudentChessRating;
use App\Models\StudentChessRatingGame;
use App\Models\StudentChessRatingPeriod;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FideRatingService
{
    public const DEFAULT_RATING = 1400;

    public const RATING_FLOOR = 1400;

    /**
     * FIDE table 8.1.2: upper rating-difference bound => score probability
     * for the higher-rated player.
     */
    private const EXPECTED_SCORE_TABLE = [
        3 => 0.50, 10 => 0.51, 17 => 0.52, 25 => 0.53, 32 => 0.54,
        39 => 0.55, 46 => 0.56, 53 => 0.57, 61 => 0.58, 68 => 0.59,
        76 => 0.60, 83 => 0.61, 91 => 0.62, 98 => 0.63, 106 => 0.64,
        113 => 0.65, 121 => 0.66, 129 => 0.67, 137 => 0.68, 145 => 0.69,
        153 => 0.70, 162 => 0.71, 170 => 0.72, 179 => 0.73, 188 => 0.74,
        197 => 0.75, 206 => 0.76, 215 => 0.77, 225 => 0.78, 235 => 0.79,
        245 => 0.80, 256 => 0.81, 267 => 0.82, 278 => 0.83, 290 => 0.84,
        302 => 0.85, 315 => 0.86, 328 => 0.87, 344 => 0.88, 357 => 0.89,
        374 => 0.90, 391 => 0.91, 411 => 0.92, 432 => 0.93, 456 => 0.94,
        484 => 0.95, 517 => 0.96, 559 => 0.97, 619 => 0.98, 735 => 0.99,
        PHP_INT_MAX => 1.00,
    ];

    /**
     * Calculate one completed LMS tournament as a single rating period.
     * The transaction and unique period key make repeated scheduler calls safe.
     */
    public function processTournament(Tournament|int $tournament): Collection
    {
        if (! app(FeatureFlagService::class)->enabled('archer_ratings_enabled')) {
            return collect();
        }

        $tournamentId = $tournament instanceof Tournament ? $tournament->id : $tournament;
        $changes = DB::transaction(function () use ($tournamentId) {
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            if (! $tournament->isCompleted()) {
                return collect();
            }

            $ratingType = strtolower((string) $tournament->format);
            if (! in_array($ratingType, StudentChessRating::TYPES, true)) {
                throw new RuntimeException('Tournament format cannot be rated by Archer Journey.');
            }

            if (StudentChessRatingPeriod::where('tournament_id', $tournament->id)
                ->where('rating_type', $ratingType)
                ->exists()) {
                return collect();
            }

            $pairings = $this->rateablePairings($tournament);
            if ($pairings->isEmpty()) {
                return collect();
            }

            $studentIds = $pairings
                ->flatMap(fn (TournamentPairing $pairing) => [$pairing->white_user_id, $pairing->black_user_id])
                ->filter()
                ->unique()
                ->values();

            foreach ($studentIds as $studentId) {
                StudentChessRating::query()->insertOrIgnore([
                    'user_id' => $studentId,
                    'rating_type' => $ratingType,
                    'initial_rating' => self::DEFAULT_RATING,
                    'rating' => self::DEFAULT_RATING,
                    'peak_rating' => self::DEFAULT_RATING,
                    'games_played' => 0,
                    'is_provisional' => true,
                    'ever_reached_2400' => false,
                    'source' => 'lms',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $ratings = StudentChessRating::query()
                ->where('rating_type', $ratingType)
                ->whereIn('user_id', $studentIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            $calculations = $this->calculatePeriods($pairings, $ratings, $ratingType);
            $calculatedAt = $tournament->completed_at ?? now()->utc();
            $changes = collect();

            foreach ($calculations as $studentId => $calculation) {
                /** @var StudentChessRating $rating */
                $rating = $ratings->get($studentId);
                $ratingAfter = max(self::RATING_FLOOR, $rating->rating + $calculation['change']);
                $actualChange = $ratingAfter - $rating->rating;

                $period = StudentChessRatingPeriod::create([
                    'user_id' => $studentId,
                    'tournament_id' => $tournament->id,
                    'rating_type' => $ratingType,
                    'rating_before' => $rating->rating,
                    'rating_change' => $actualChange,
                    'rating_after' => $ratingAfter,
                    'k_factor' => $calculation['k_factor'],
                    'games_rated' => count($calculation['games']),
                    'score' => $calculation['score'],
                    'expected_score' => $calculation['expected_score'],
                    'calculated_at' => $calculatedAt,
                    'calculation' => [
                        'method' => 'Archer FIDE-style rating',
                        'rating_floor' => self::RATING_FLOOR,
                        'difference_cap' => 400,
                        'raw_change' => $calculation['raw_change'],
                    ],
                ]);

                foreach ($calculation['games'] as $game) {
                    StudentChessRatingGame::create($game + ['rating_period_id' => $period->id]);
                }

                $newGamesPlayed = $rating->games_played + count($calculation['games']);
                $rating->update([
                    'rating' => $ratingAfter,
                    'peak_rating' => max($rating->peak_rating, $ratingAfter),
                    'games_played' => $newGamesPlayed,
                    'is_provisional' => $newGamesPlayed < 30,
                    'ever_reached_2400' => $rating->ever_reached_2400 || $ratingAfter >= 2400,
                    'last_rated_at' => $calculatedAt,
                ]);

                $changes->push([
                    'student_id' => (int) $studentId,
                    'rating_type' => $ratingType,
                    'rating_before' => $period->rating_before,
                    'rating_after' => $ratingAfter,
                    'rating_change' => $actualChange,
                    'rating_gain_from_start' => $ratingAfter - $rating->initial_rating,
                ]);
            }

            return $changes;
        }, 5);

        $tournament = Tournament::find($tournamentId);
        if ($tournament) {
            foreach ($changes->where('rating_change', '>', 0) as $change) {
                $student = User::find($change['student_id']);
                if (! $student) {
                    continue;
                }

                app(ArcherJourneyEngine::class)->record(
                    ArcherJourneyEngine::RATING_IMPROVED,
                    $student,
                    $tournament,
                    $change,
                    null,
                    $tournament->completed_at,
                    $tournament->tournament_timezone,
                    'rating_improved:'.$change['student_id'].':'.$tournament->id.':'.$change['rating_type'],
                );
            }
        }

        return $changes;
    }

    public function expectedScore(int $playerRating, int $opponentRating): float
    {
        $difference = max(-400, min(400, $playerRating - $opponentRating));
        $absoluteDifference = abs($difference);
        $higherRatedProbability = 1.0;

        foreach (self::EXPECTED_SCORE_TABLE as $upperBound => $probability) {
            if ($absoluteDifference <= $upperBound) {
                $higherRatedProbability = $probability;
                break;
            }
        }

        if ($difference === 0) {
            return 0.5;
        }

        return $difference > 0 ? $higherRatedProbability : 1 - $higherRatedProbability;
    }

    public function kFactor(StudentChessRating $rating, int $gamesInPeriod): int
    {
        $base = $rating->ever_reached_2400 || $rating->rating >= 2400
            ? 10
            : ($rating->games_played < 30 ? 40 : 20);

        return max(1, min($base, (int) floor(700 / max(1, $gamesInPeriod))));
    }

    protected function rateablePairings(Tournament $tournament): Collection
    {
        return $tournament->pairings()
            ->where('status', 'completed')
            ->where('is_bye', false)
            ->whereNotNull('white_user_id')
            ->whereNotNull('black_user_id')
            ->whereIn('result', ['1-0', '0-1', '0.5-0.5'])
            ->where('move_count', '>=', 2)
            ->orderBy('round')
            ->orderBy('board')
            ->get();
    }

    protected function calculatePeriods(Collection $pairings, Collection $ratings, string $ratingType): array
    {
        $periods = [];

        foreach ($pairings as $pairing) {
            $whiteRating = $ratings->get($pairing->white_user_id);
            $blackRating = $ratings->get($pairing->black_user_id);
            if (! $whiteRating || ! $blackRating) {
                continue;
            }

            if ($this->excludedByHighDifferenceRule($ratingType, $whiteRating->rating, $blackRating->rating)) {
                continue;
            }

            $whiteScore = $this->scoreForPlayer($pairing, $pairing->white_user_id);
            $blackScore = 1 - $whiteScore;
            $this->appendGame($periods, $pairing, $whiteRating, $blackRating, $whiteScore);
            $this->appendGame($periods, $pairing, $blackRating, $whiteRating, $blackScore);
        }

        foreach ($periods as $studentId => &$period) {
            $rating = $ratings->get($studentId);
            $period['k_factor'] = $this->kFactor($rating, count($period['games']));
            $period['raw_change'] = $period['k_factor'] * ($period['score'] - $period['expected_score']);
            $period['change'] = (int) round($period['raw_change'], 0, PHP_ROUND_HALF_UP);
        }
        unset($period);

        return $periods;
    }

    protected function appendGame(
        array &$periods,
        TournamentPairing $pairing,
        StudentChessRating $player,
        StudentChessRating $opponent,
        float $score,
    ): void {
        $expected = $this->expectedScore($player->rating, $opponent->rating);
        $periods[$player->user_id] ??= [
            'score' => 0.0,
            'expected_score' => 0.0,
            'games' => [],
        ];
        $periods[$player->user_id]['score'] += $score;
        $periods[$player->user_id]['expected_score'] += $expected;
        $periods[$player->user_id]['games'][] = [
            'pairing_id' => $pairing->id,
            'opponent_id' => $opponent->user_id,
            'opponent_rating' => $opponent->rating,
            'score' => $score,
            'expected_score' => $expected,
            'raw_delta' => $score - $expected,
        ];
    }

    protected function scoreForPlayer(TournamentPairing $pairing, int $studentId): float
    {
        if ($pairing->result === '0.5-0.5') {
            return 0.5;
        }

        return (int) $pairing->winner_user_id === $studentId ? 1.0 : 0.0;
    }

    protected function excludedByHighDifferenceRule(string $ratingType, int $ratingA, int $ratingB): bool
    {
        return in_array($ratingType, ['rapid', 'blitz'], true)
            && abs($ratingA - $ratingB) >= 600
            && max($ratingA, $ratingB) > 2600;
    }
}

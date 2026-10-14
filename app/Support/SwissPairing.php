<?php

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\TournamentPairing;
use Illuminate\Support\Collection;

/**
 * Simplified Dutch-system Swiss pairing.
 *
 * For each round we:
 *   1. Group active participants by current score, highest first.
 *   2. Inside each score group, sort deterministically (round 1 = random
 *      seed; later rounds = score then prior buchholz).
 *   3. Pair top-half against bottom-half avoiding rematches.
 *   4. Drop unpaired players into the next score group and retry.
 *   5. If an odd participant is left at the end, give them a bye (1 pt).
 *
 * Color allocation is the simple alternation rule: a player who had white
 * last round prefers black this round (and vice-versa). Round 1 is alternating
 * by seed.
 */
class SwissPairing
{
    /**
     * @param  Tournament                  $t
     * @param  int                         $round
     * @param  \Illuminate\Support\Carbon|null $startAt  When the side-to-move's clock
     *         should begin ticking. Defaults to now(). Pass `scheduled_at` for round 1
     *         when boards are pre-built during the 3-minute pairing window.
     */
    public static function generateRound(Tournament $t, int $round, $startAt = null): void
    {
        $startAt = $startAt ?: now();

        if ($t->pairings()->where('round', $round)->exists()) {
            return;
        }

        $participants = $t->participants()
            ->where('withdrawn', false)
            ->with('user')
            ->get();

        if ($participants->isEmpty()) {
            return;
        }

        // Map of user_id => list of opponent user_ids already faced.
        $history = self::buildHistory($t);
        // Map of user_id => last color played ('w'/'b') or null.
        $lastColor = self::buildLastColors($t, $participants);
        $byeReceived = $participants->keyBy('user_id')->map(fn ($p) => (bool) $p->bye_received)->all();

        $sorted = self::sortForPairing($participants, $round);

        // Score buckets, highest first.
        $buckets = $sorted->groupBy(fn ($p) => (string) $p->score)
            ->sortKeysDesc();

        $unpaired = collect();
        $pairs    = [];

        foreach ($buckets as $bucket) {
            $pool = $unpaired->merge($bucket)->values();
            $unpaired = collect();

            // Try to pair within this pool (size N). Walk from the top, pair
            // each with the closest acceptable opponent further down.
            $taken = array_fill(0, $pool->count(), false);

            for ($i = 0; $i < $pool->count(); $i++) {
                if ($taken[$i]) continue;
                $a = $pool[$i];
                $partnerIdx = null;
                for ($j = $i + 1; $j < $pool->count(); $j++) {
                    if ($taken[$j]) continue;
                    $b = $pool[$j];
                    if (in_array($b->user_id, $history[$a->user_id] ?? [], true)) continue;
                    $partnerIdx = $j;
                    break;
                }
                if ($partnerIdx === null) {
                    // Couldn't find an opponent here — push into unpaired so
                    // they fall down into the next score bucket.
                    continue;
                }
                $taken[$i] = true;
                $taken[$partnerIdx] = true;
                $b = $pool[$partnerIdx];
                $pairs[] = self::orderColors($a, $b, $lastColor);
            }

            for ($i = 0; $i < $pool->count(); $i++) {
                if (!$taken[$i]) $unpaired->push($pool[$i]);
            }
        }

        // Whoever is still left after the lowest bucket: bye candidate (only
        // one, by design). If two or more remain, pair them ignoring rematch
        // history as a fallback.
        while ($unpaired->count() >= 2) {
            $a = $unpaired->shift();
            $b = $unpaired->shift();
            $pairs[] = self::orderColors($a, $b, $lastColor);
        }

        $bye = self::pickByeCandidate($unpaired, $byeReceived);

        // Persist pairings as boards 1..N.
        $boardNo = 1;
        foreach ($pairs as $pair) {
            self::createPairingRow($t, $round, $boardNo++, $pair['white'], $pair['black'], $startAt);
        }

        if ($bye) {
            self::createByeRow($t, $round, $boardNo, $bye);
            $bye->bye_received = true;
            $bye->score        = (float) $bye->score + 1.0;
            $bye->save();
        }
    }

    /**
     * Returns ['white' => participant, 'black' => participant] honouring color
     * preferences derived from each player's most-recent color.
     */
    protected static function orderColors(TournamentParticipant $a, TournamentParticipant $b, array $lastColor): array
    {
        $aLast = $lastColor[$a->user_id] ?? null;
        $bLast = $lastColor[$b->user_id] ?? null;

        // Both played white last → flip both. The one with worse score plays white now.
        // If only one of them played white last, give the OTHER white (so it alternates).
        if ($aLast === 'w' && $bLast !== 'w') {
            return ['white' => $b, 'black' => $a];
        }
        if ($bLast === 'w' && $aLast !== 'w') {
            return ['white' => $a, 'black' => $b];
        }
        if ($aLast === 'b' && $bLast !== 'b') {
            return ['white' => $a, 'black' => $b];
        }
        if ($bLast === 'b' && $aLast !== 'b') {
            return ['white' => $b, 'black' => $a];
        }
        // No info or symmetric: higher seeded (already top of the bucket) gets white.
        return ['white' => $a, 'black' => $b];
    }

    protected static function buildHistory(Tournament $t): array
    {
        $history = [];
        foreach ($t->pairings()->where('is_bye', false)->get() as $p) {
            if (!$p->white_user_id || !$p->black_user_id) continue;
            $history[$p->white_user_id][] = $p->black_user_id;
            $history[$p->black_user_id][] = $p->white_user_id;
        }
        return $history;
    }

    protected static function buildLastColors(Tournament $t, Collection $participants): array
    {
        $latest = $t->pairings()
            ->where('is_bye', false)
            ->orderByDesc('round')
            ->orderByDesc('id')
            ->get();

        $byUser = [];
        foreach ($latest as $p) {
            if ($p->white_user_id && !isset($byUser[$p->white_user_id])) {
                $byUser[$p->white_user_id] = 'w';
            }
            if ($p->black_user_id && !isset($byUser[$p->black_user_id])) {
                $byUser[$p->black_user_id] = 'b';
            }
        }
        return $byUser;
    }

    protected static function sortForPairing(Collection $participants, int $round): Collection
    {
        if ($round <= 1) {
            // Random seed for round 1, deterministic-ish using user ids.
            return $participants->shuffle();
        }
        return $participants->sortBy([
            ['score', 'desc'],
            ['buchholz', 'desc'],
            fn ($a, $b) => strcmp(optional($a->user)->name ?? '', optional($b->user)->name ?? ''),
        ])->values();
    }

    protected static function pickByeCandidate(Collection $unpaired, array $byeReceived): ?TournamentParticipant
    {
        if ($unpaired->isEmpty()) {
            return null;
        }

        $neverByed = $unpaired->filter(fn (TournamentParticipant $p) => !($byeReceived[$p->user_id] ?? false));
        if ($neverByed->isNotEmpty()) {
            return $neverByed
                ->sortBy([
                    ['score', 'asc'],
                    ['buchholz', 'asc'],
                    fn ($a, $b) => strcmp(optional($a->user)->name ?? '', optional($b->user)->name ?? ''),
                ])
                ->first();
        }

        return $unpaired
            ->sortBy([
                ['score', 'asc'],
                ['buchholz', 'asc'],
                fn ($a, $b) => strcmp(optional($a->user)->name ?? '', optional($b->user)->name ?? ''),
            ])
            ->first();
    }

    protected static function createPairingRow(Tournament $t, int $round, int $board, TournamentParticipant $white, TournamentParticipant $black, $startAt = null): void
    {
        // For round 1 inside the pre-pair window we receive scheduled_at so the
        // clock for the side-to-move only starts ticking at the official start.
        $clockStart = $startAt ?: now();

        TournamentPairing::create([
            'tournament_id'   => $t->id,
            'round'           => $round,
            'board'           => $board,
            'white_user_id'   => $white->user_id,
            'black_user_id'   => $black->user_id,
            'is_bye'          => false,
            'current_fen'     => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'move_count'      => 0,
            'white_clock_ms'  => $t->startingClockMs(),
            'black_clock_ms'  => $t->startingClockMs(),
            'status'          => 'active',
            'last_move_at'    => $clockStart,
        ]);
    }

    protected static function createByeRow(Tournament $t, int $round, int $board, TournamentParticipant $byed): void
    {
        TournamentPairing::create([
            'tournament_id'   => $t->id,
            'round'           => $round,
            'board'           => $board,
            'white_user_id'   => $byed->user_id,
            'black_user_id'   => null,
            'is_bye'          => true,
            'result'          => '1-0',                   // by convention: bye = 1 pt to the byed player
            'winner_user_id'  => $byed->user_id,
            'current_fen'     => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'move_count'      => 0,
            'white_clock_ms'  => 0,
            'black_clock_ms'  => 0,
            'status'          => 'completed',
        ]);
    }
}

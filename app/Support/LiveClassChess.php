<?php

namespace App\Support;

use InvalidArgumentException;
use PChess\Chess\Chess;
use PChess\Chess\Move;

final class LiveClassChess
{
    /**
     * Validate the coach's full solution from the supplied position and store
     * every move in an unambiguous coordinate form.
     *
     * @return array<int, string>
     */
    public function normalizeSolution(string $fen, array $moves): array
    {
        try {
            $game = new Chess($fen);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('The puzzle starting position is invalid.', previous: $e);
        }

        $normalized = [];
        foreach (array_values($moves) as $index => $raw) {
            $executed = $this->executeExpectedMove($game, (string) $raw);
            if (! $executed) {
                throw new InvalidArgumentException('Solution move '.($index + 1).' is not legal from the recorded position.');
            }

            $normalized[] = $this->coordinateKey($executed);
        }

        return $normalized;
    }

    /**
     * Execute a student's coordinate move against the server-side position.
     * The returned FEN is authoritative; browser-submitted FEN data is ignored.
     *
     * @return array{move: Move, fen: string}|null
     */
    public function executeCoordinate(string $fen, string $from, string $to, ?string $promotion = null): ?array
    {
        try {
            $game = new Chess($fen);
        } catch (\Throwable) {
            return null;
        }

        $candidate = $this->findLegalMove($game, strtolower($from), strtolower($to), $promotion);
        if (! $candidate) {
            return null;
        }

        $executed = $game->move([
            'from' => $candidate->from,
            'to' => $candidate->to,
            'promotion' => $candidate->promotion,
        ]);

        return $executed ? ['move' => $executed, 'fen' => $game->fen()] : null;
    }

    public function coordinateKey(Move $move): string
    {
        $key = strtolower($move->from.'-'.$move->to);

        return $move->promotion ? $key.'='.strtolower($move->promotion) : $key;
    }

    private function executeExpectedMove(Chess $game, string $raw): ?Move
    {
        $value = trim($raw);
        if (preg_match('/^([a-h][1-8])\s*-?\s*([a-h][1-8])(?:=([qrbn]))?$/i', $value, $matches)) {
            $candidate = $this->findLegalMove(
                $game,
                strtolower($matches[1]),
                strtolower($matches[2]),
                isset($matches[3]) ? strtolower($matches[3]) : null,
            );

            if (! $candidate) {
                return null;
            }

            return $game->move([
                'from' => $candidate->from,
                'to' => $candidate->to,
                'promotion' => $candidate->promotion,
            ]);
        }

        return $game->move($value);
    }

    private function findLegalMove(Chess $game, string $from, string $to, ?string $promotion = null): ?Move
    {
        $candidates = collect($game->moves())
            ->filter(fn (Move $move) => $move->from === $from && $move->to === $to)
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($promotion) {
            return $candidates->first(fn (Move $move) => $move->promotion === strtolower($promotion));
        }

        return $candidates->first(fn (Move $move) => $move->promotion === 'q')
            ?: $candidates->first();
    }
}

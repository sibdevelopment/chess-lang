<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentPairing extends Model
{
    protected $fillable = [
        'tournament_id', 'round', 'board',
        'white_user_id', 'black_user_id', 'is_bye',
        'result', 'winner_user_id',
        'current_fen', 'move_count', 'moves_pgn',
        'white_clock_ms', 'black_clock_ms', 'last_move_at',
        'completed_at', 'status', 'resigned_by', 'draw_offer_by',
    ];

    protected $casts = [
        'is_bye'          => 'boolean',
        'round'           => 'integer',
        'board'           => 'integer',
        'move_count'      => 'integer',
        'white_clock_ms'  => 'integer',
        'black_clock_ms'  => 'integer',
        'last_move_at'    => 'datetime',
        'completed_at'    => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function white(): BelongsTo
    {
        return $this->belongsTo(User::class, 'white_user_id');
    }

    public function black(): BelongsTo
    {
        return $this->belongsTo(User::class, 'black_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    // ── Domain ─────────────────────────────────────────────────────────
    public function isComplete(): bool { return $this->status === 'completed'; }
    public function isActive(): bool   { return $this->status === 'active'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function isPlayer(int $userId): bool
    {
        return $this->white_user_id === $userId || $this->black_user_id === $userId;
    }

    /**
     * 'w' or 'b' — which colour this user plays in this pairing.
     */
    public function colorOf(int $userId): ?string
    {
        if ($this->white_user_id === $userId) return 'w';
        if ($this->black_user_id === $userId) return 'b';
        return null;
    }

    /**
     * The opponent's User model (or null for a bye).
     */
    public function opponentOf(int $userId): ?User
    {
        if ($this->is_bye) return null;
        if ($this->white_user_id === $userId) return $this->black;
        if ($this->black_user_id === $userId) return $this->white;
        return null;
    }

    /**
     * Whose turn is it right now? Returns 'w' or 'b' parsed from current_fen,
     * or null if the game is over.
     */
    public function sideToMove(): ?string
    {
        if ($this->status !== 'active') return null;
        $parts = explode(' ', (string) $this->current_fen);
        return $parts[1] ?? null;
    }

    /**
     * How much time remains, in ms, for the side currently to move
     * (after subtracting the time elapsed since last_move_at).
     */
    public function liveClockForSideToMove(): int
    {
        $turn = $this->sideToMove();
        if (!$turn) return 0;
        $stored = $turn === 'w' ? $this->white_clock_ms : $this->black_clock_ms;
        if (!$this->last_move_at) return $stored;
        $elapsed = (int) max(0, now()->diffInMilliseconds($this->last_move_at, false) * -1);
        return max(0, $stored - $elapsed);
    }

    /**
     * Snapshot both clocks adjusted for elapsed wall-clock time. Returns
     * ['white' => int_ms, 'black' => int_ms].
     */
    public function liveClocks(): array
    {
        $w = $this->white_clock_ms;
        $b = $this->black_clock_ms;
        if ($this->status !== 'active') return ['white' => $w, 'black' => $b];

        $turn = $this->sideToMove();
        if ($this->last_move_at && $turn) {
            $elapsedMs = (int) max(0, Carbon::now()->diffInMilliseconds($this->last_move_at, false) * -1);
            if ($turn === 'w') $w = max(0, $w - $elapsedMs);
            else                $b = max(0, $b - $elapsedMs);
        }
        return ['white' => $w, 'black' => $b];
    }
}

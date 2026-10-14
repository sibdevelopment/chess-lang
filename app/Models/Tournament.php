<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Tournament extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_LIVE = 'live';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CANCELLED_INSUFFICIENT_PLAYERS = 'cancelled_insufficient_players';

    public const AUDIENCE_ALL_STUDENTS = 'all_students';

    public const AUDIENCE_SPECIFIC_GROUPS = 'specific_groups';

    public const AUDIENCE_SPECIFIC_STUDENTS = 'specific_students';

    public const LEGACY_AUDIENCE_ALL_GROUPS = 'all_groups';

    protected $fillable = [
        'name', 'event_series', 'format', 'time_control', 'rounds', 'minimum_players', 'scheduled_at',
        'tournament_timezone',
        'round_minutes', 'pairing_window_minutes', 'round_break_minutes',
        'audience_type', 'status', 'cancellation_reason', 'current_round',
        'round_started_at', 'next_round_at', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'round_started_at' => 'datetime',
        'next_round_at' => 'datetime',
        'completed_at' => 'datetime',
        'rounds' => 'integer',
        'minimum_players' => 'integer',
        'current_round' => 'integer',
        'round_minutes' => 'integer',
        'pairing_window_minutes' => 'integer',
        'round_break_minutes' => 'integer',
    ];

    // ── Relations ──────────────────────────────────────────────────────
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'tournament_groups');
    }

    public function targetUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_users');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(TournamentPairing::class);
    }

    // ── Domain helpers ─────────────────────────────────────────────────
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isUpcoming(): bool
    {
        return $this->status === self::STATUS_UPCOMING;
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isScheduled(): bool
    {
        return $this->isUpcoming();
    }

    public function isInProgress(): bool
    {
        return $this->isLive();
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_CANCELLED_INSUFFICIENT_PLAYERS], true);
    }

    public function isJoinable(): bool
    {
        return $this->isUpcoming() && $this->registrationClosesAt()->isFuture();
    }

    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isCancelled();
    }

    /**
     * Parse "5+0" → ['base' => 5, 'increment' => 0] (minutes / seconds).
     */
    public function timeControlParts(): array
    {
        $parts = explode('+', (string) $this->time_control);

        return [
            'base' => (int) ($parts[0] ?? 0),
            'increment' => (int) ($parts[1] ?? 0),
        ];
    }

    /**
     * Initial clock per side, in milliseconds.
     */
    public function startingClockMs(): int
    {
        $tc = $this->timeControlParts();

        return $tc['base'] * 60 * 1000;
    }

    /**
     * Increment per move, in milliseconds.
     */
    public function incrementMs(): int
    {
        $tc = $this->timeControlParts();

        return $tc['increment'] * 1000;
    }

    /**
     * Pretty label for human display.
     */
    public function getFormatLabelAttribute(): string
    {
        return ucfirst($this->format);
    }

    public function isArcherSunday(): bool
    {
        return $this->event_series === 'archer_sunday';
    }

    /**
     * The set of users eligible to play this tournament given its
     * audience_type. Returned as a Collection of User models.
     */
    public function eligibleStudentsQuery(): Builder
    {
        return match ($this->audience_type) {
            self::AUDIENCE_ALL_STUDENTS => User::where('role', 'student')->where('is_active', true),
            self::LEGACY_AUDIENCE_ALL_GROUPS => User::where('role', 'student')->where('is_active', true)
                ->whereHas('groups'),
            self::AUDIENCE_SPECIFIC_GROUPS => User::where('role', 'student')->where('is_active', true)
                ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $this->targetGroups->pluck('id'))),
            self::AUDIENCE_SPECIFIC_STUDENTS => User::where('role', 'student')
                ->where('is_active', true)
                ->whereIn('id', $this->targetUsers()->pluck('users.id')),
            default => User::whereRaw('1 = 0'),
        };
    }

    public function eligibleStudents(): Collection
    {
        return $this->eligibleStudentsQuery()->get();
    }

    /**
     * Standings (participants sorted by score desc, then buchholz desc, then name).
     * Each row is annotated with wins/draws/losses computed from pairings.
     */
    public function standings(): Collection
    {
        $rows = $this->participants()->with('user')->get();

        // Pre-compute W/D/L per participant from pairings.
        $stats = [];
        foreach ($this->pairings as $p) {
            if ($p->status !== 'completed') {
                continue;
            }
            if ($p->is_bye) {
                $uid = $p->white_user_id;
                if ($uid) {
                    $stats[$uid]['wins'] = ($stats[$uid]['wins'] ?? 0) + 1; // bye counts as a win
                }

                continue;
            }
            $w = $p->white_user_id;
            $b = $p->black_user_id;
            if (! $w || ! $b) {
                continue;
            }
            if ($p->result === '1-0') {
                $stats[$w]['wins'] = ($stats[$w]['wins'] ?? 0) + 1;
                $stats[$b]['losses'] = ($stats[$b]['losses'] ?? 0) + 1;
            } elseif ($p->result === '0-1') {
                $stats[$b]['wins'] = ($stats[$b]['wins'] ?? 0) + 1;
                $stats[$w]['losses'] = ($stats[$w]['losses'] ?? 0) + 1;
            } elseif ($p->result === '0.5-0.5') {
                $stats[$w]['draws'] = ($stats[$w]['draws'] ?? 0) + 1;
                $stats[$b]['draws'] = ($stats[$b]['draws'] ?? 0) + 1;
            }
        }

        foreach ($rows as $row) {
            $s = $stats[$row->user_id] ?? [];
            $row->wins = (int) ($s['wins'] ?? 0);
            $row->draws = (int) ($s['draws'] ?? 0);
            $row->losses = (int) ($s['losses'] ?? 0);
        }

        return $rows
            ->sortBy([
                ['score',    'desc'],
                ['buchholz', 'desc'],
                fn ($a, $b) => strcmp(optional($a->user)->name ?? '', optional($b->user)->name ?? ''),
            ])
            ->values();
    }

    // ── New: roster + timing helpers ───────────────────────────────────

    /**
     * Users who have actively JOINED (clicked Join Tournament).
     */
    public function joinedStudents(): Collection
    {
        return $this->participants()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->sortBy('name')
            ->values();
    }

    /**
     * Eligible students who have NOT joined yet.
     */
    public function pendingStudents(): Collection
    {
        $joinedIds = $this->participants()->pluck('user_id')->all();

        return $this->eligibleStudentsQuery()
            ->whereNotIn('users.id', $joinedIds)
            ->orderBy('users.name')
            ->get();
    }

    /**
     * Has a given user joined this tournament?
     */
    public function hasJoined(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->where('withdrawn', false)->exists();
    }

    /**
     * Are we inside the pre-tournament pairing window?
     * (scheduled_at - pairing_window_minutes ≤ now < scheduled_at)
     */
    public function isPairingWindow(): bool
    {
        if (! $this->scheduled_at) {
            return false;
        }
        $window = (int) ($this->pairing_window_minutes ?? 3);

        return now()->between(
            $this->scheduled_at->copy()->subMinutes($window),
            $this->scheduled_at
        );
    }

    /**
     * The instant when round 1 should be paired (= scheduled_at - window).
     */
    public function pairingStartsAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->scheduled_at) {
            return null;
        }
        $window = (int) ($this->pairing_window_minutes ?? 3);

        return $this->scheduled_at->copy()->subMinutes($window);
    }

    /**
     * The next "thing happens" timestamp for the live countdown banner:
     * - while scheduled  → scheduled_at (round 1 starts)
     * - between rounds   → next_round_at
     * - mid-round        → null (no countdown shown)
     */
    public function countdownTarget(): ?\Illuminate\Support\Carbon
    {
        if ($this->isUpcoming()) {
            return $this->scheduled_at;
        }
        if ($this->isLive() && $this->next_round_at && $this->next_round_at->isFuture()) {
            return $this->next_round_at;
        }

        return null;
    }

    public function registrationClosesAt(): Carbon
    {
        return $this->scheduled_at?->copy() ?? now();
    }

    public function minimumPlayers(): int
    {
        return max(4, (int) ($this->minimum_players ?? 4));
    }

    public function canBeManagedBy(User $user): bool
    {
        if ($user->isAdmin() || $user->isStaff()) {
            return true;
        }

        return $user->isCoach() && (int) $this->created_by === (int) $user->id;
    }

    public function canBeViewedBy(User $user): bool
    {
        if ($user->isAdmin() || $user->isStaff() || $user->isCoach()) {
            return true;
        }

        return $user->isStudent() && $this->isStudentEligible($user);
    }

    public function canBeCancelledBy(User $user): bool
    {
        if (! $this->canBeManagedBy($user)) {
            return false;
        }

        if ($this->isLive() && ! $user->isAdmin()) {
            return false;
        }

        return ! $this->isTerminal();
    }

    public function isStudentEligible(User $student): bool
    {
        if (! $student->isStudent() || ! $student->is_active) {
            return false;
        }

        $studentGroupIds = $student->groups->pluck('id');

        return match ($this->audience_type) {
            self::AUDIENCE_ALL_STUDENTS => true,
            self::LEGACY_AUDIENCE_ALL_GROUPS => $studentGroupIds->isNotEmpty(),
            self::AUDIENCE_SPECIFIC_GROUPS => $this->targetGroups->pluck('id')->intersect($studentGroupIds)->isNotEmpty(),
            self::AUDIENCE_SPECIFIC_STUDENTS => $this->targetUsers->pluck('id')->contains($student->id),
            default => false,
        };
    }
}

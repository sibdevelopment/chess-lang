<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class QuizSession extends Model
{
    protected $fillable = [
        'name', 'coach_id', 'level', 'countries', 'scheduled_at', 'status', 'week',
        'current_quiz_id', 'current_quiz_started_at',
        'started_at', 'ended_at', 'created_by',
    ];

    protected $casts = [
        'scheduled_at'            => 'datetime',
        'current_quiz_started_at' => 'datetime',
        'started_at'              => 'datetime',
        'ended_at'                => 'datetime',
        'week'                    => 'integer',
        'countries'               => 'array',
    ];

    // ── Relations ──────────────────────────────────────────────────────
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentQuiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'current_quiz_id');
    }

    public function quizQueue(): HasMany
    {
        return $this->hasMany(QuizSessionQuiz::class)->orderBy('position');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(QuizSessionAttendee::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizSessionResponse::class);
    }

    // ── Status helpers ─────────────────────────────────────────────────
    public function isScheduled(): bool { return $this->status === 'scheduled'; }
    public function isLive(): bool      { return $this->status === 'live'; }
    public function isEnded(): bool     { return $this->status === 'ended'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    /**
     * Has the given user joined this session?
     */
    public function hasJoined(int $userId): bool
    {
        return $this->attendees()->where('user_id', $userId)->exists();
    }

    /**
     * Country targeting helper.
     *
     * - If `countries` is null or empty -> session is open to all regions
     *   (legacy behaviour for sessions created before targeting existed).
     * - Otherwise the student's country must be one of the listed codes.
     *
     * Students with a missing country (null) only match open sessions.
     */
    public function isCountryAllowed(?string $studentCountry): bool
    {
        $list = $this->countries;
        if (!is_array($list) || empty($list)) {
            return true;
        }
        return $studentCountry !== null && $studentCountry !== '' && in_array($studentCountry, $list, true);
    }

    /**
     * Pretty render-friendly list of country labels for display.
     * Falls back to short codes if the code isn't in our static list.
     */
    public function countryLabels(): array
    {
        $list = $this->countries ?? [];
        return array_map(fn ($code) => User::studentCountryLabel($code), $list);
    }

    /**
     * The next quiz that hasn't been shown yet, in queue order.
     */
    public function nextUnshownQuiz(): ?QuizSessionQuiz
    {
        return $this->quizQueue()->where('shown', false)->first();
    }

    /**
     * Seconds remaining on the currently-broadcast quiz, or null if none.
     */
    public function secondsRemainingOnCurrent(): ?int
    {
        if (!$this->current_quiz_id || !$this->current_quiz_started_at) return null;
        $limit = (int) ($this->currentQuiz->time_limit_seconds ?? 120);
        $elapsed = $this->current_quiz_started_at->diffInSeconds(Carbon::now(), false);
        return max(0, $limit - $elapsed);
    }
}

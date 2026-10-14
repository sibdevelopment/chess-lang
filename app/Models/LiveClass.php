<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class LiveClass extends Model
{
    protected static ?bool $supportsCancelledAt = null;
    protected static ?bool $supportsCompletionLifecycle = null;

    protected $table = 'live_classes';

    protected $fillable = [
        'name', 'coach_id', 'group_id', 'chapter_id', 'chapter_index', 'status', 'room_id',
        'current_move_index', 'board_state', 'arrows', 'highlights', 'students_joined',
        'group_schedule_id', 'scheduled_for', 'started_at', 'ended_at', 'cancelled_at',
        'scheduled_start_at', 'scheduled_end_at', 'actual_start_at', 'actual_end_at',
        'duration_minutes', 'completion_type', 'chapter_completed',
    ];

    public function isScheduled() { return $this->status === 'scheduled'; }
    public function isCancelled() { return $this->status === 'cancelled'; }

    public function coach()   { return $this->belongsTo(User::class, 'coach_id'); }
    public function group()   { return $this->belongsTo(Group::class); }
    public function schedule() { return $this->belongsTo(GroupSchedule::class, 'group_schedule_id'); }
    public function chapter() { return $this->belongsTo(Chapter::class); }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'class_participants', 'live_class_id', 'user_id')
                    ->withPivot('joined_at', 'left_at');
    }

    public function homework()
    {
        return $this->hasMany(Homework::class, 'source_class_id');
    }

    public function questions()
    {
        return $this->hasMany(ClassQuestion::class, 'live_class_id');
    }

    public function isLive()  { return $this->status === 'live'; }
    public function isEnded() { return in_array($this->status, ['ended', 'completed'], true); }
    public function isCompleted() { return $this->status === 'completed' || $this->status === 'ended'; }

    public static function supportsCompletionLifecycle(): bool
    {
        if (static::$supportsCompletionLifecycle === null) {
            static::$supportsCompletionLifecycle =
                Schema::hasColumn('live_classes', 'scheduled_start_at')
                && Schema::hasColumn('live_classes', 'scheduled_end_at')
                && Schema::hasColumn('live_classes', 'actual_start_at')
                && Schema::hasColumn('live_classes', 'actual_end_at')
                && Schema::hasColumn('live_classes', 'duration_minutes')
                && Schema::hasColumn('live_classes', 'completion_type')
                && Schema::hasColumn('live_classes', 'chapter_completed');
        }

        return static::$supportsCompletionLifecycle;
    }

    public static function supportsCancelledAt(): bool
    {
        if (static::$supportsCancelledAt === null) {
            static::$supportsCancelledAt = Schema::hasColumn('live_classes', 'cancelled_at');
        }

        return static::$supportsCancelledAt;
    }

    public static function statusTimestampOrderExpression(): string
    {
        if (static::supportsCompletionLifecycle()) {
            return static::supportsCancelledAt()
                ? 'COALESCE(actual_end_at, cancelled_at, scheduled_start_at, created_at)'
                : 'COALESCE(actual_end_at, scheduled_start_at, created_at)';
        }

        return static::supportsCancelledAt()
            ? 'COALESCE(ended_at, cancelled_at, scheduled_for, created_at)'
            : 'COALESCE(ended_at, scheduled_for, created_at)';
    }

    public static function cancellationUpdatePayload($timestamp): array
    {
        $payload = [
            'status' => 'cancelled',
            'completion_type' => 'cancelled',
            'chapter_completed' => false,
            'actual_end_at' => static::supportsCompletionLifecycle() ? $timestamp : null,
            'ended_at' => $timestamp,
            'duration_minutes' => 60,
        ];

        if (static::supportsCancelledAt()) {
            $payload['cancelled_at'] = $timestamp;
        }

        return $payload;
    }

    public function displayTimezone(): string
    {
        return $this->group?->scheduleTimezone() ?? config('app.timezone');
    }

    public function getScheduledForAttribute($value): ?Carbon
    {
        return $this->asUtcCarbon($value);
    }

    public function setScheduledForAttribute($value): void
    {
        $this->attributes['scheduled_for'] = $this->fromUtcCarbon($value);
    }

    public function getScheduledStartAtAttribute($value): ?Carbon
    {
        return $this->asUtcCarbon($value);
    }

    public function setScheduledStartAtAttribute($value): void
    {
        $this->attributes['scheduled_start_at'] = $this->fromUtcCarbon($value);
    }

    public function getScheduledEndAtAttribute($value): ?Carbon
    {
        return $this->asUtcCarbon($value);
    }

    public function setScheduledEndAtAttribute($value): void
    {
        $this->attributes['scheduled_end_at'] = $this->fromUtcCarbon($value);
    }

    public function getStartedAtAttribute($value): ?Carbon
    {
        return $this->asUtcCarbon($value);
    }

    public function setStartedAtAttribute($value): void
    {
        $this->attributes['started_at'] = $this->fromUtcCarbon($value);
    }

    public function getEndedAtAttribute($value): ?Carbon
    {
        return $this->asUtcCarbon($value);
    }

    public function setEndedAtAttribute($value): void
    {
        $this->attributes['ended_at'] = $this->fromUtcCarbon($value);
    }

    public function getActualStartAtAttribute($value): ?Carbon
    {
        return $this->asUtcCarbon($value);
    }

    public function setActualStartAtAttribute($value): void
    {
        $this->attributes['actual_start_at'] = $this->fromUtcCarbon($value);
    }

    public function getActualEndAtAttribute($value): ?Carbon
    {
        return $this->asUtcCarbon($value);
    }

    public function setActualEndAtAttribute($value): void
    {
        $this->attributes['actual_end_at'] = $this->fromUtcCarbon($value);
    }

    public function getCancelledAtAttribute($value): ?Carbon
    {
        return $this->asUtcCarbon($value);
    }

    public function setCancelledAtAttribute($value): void
    {
        $this->attributes['cancelled_at'] = $this->fromUtcCarbon($value);
    }

    public function getDurationAttribute(): string
    {
        $start = $this->actual_start_at ?? $this->started_at;
        if (!$start) {
            return '—';
        }

        $storedMinutes = static::supportsCompletionLifecycle() ? (int) ($this->duration_minutes ?? 0) : 0;
        if ($storedMinutes > 0 && ($this->isCompleted() || $this->isCancelled())) {
            $mins = $storedMinutes;
        } else {
            $end = $this->actual_end_at ?? $this->ended_at ?? now();
            $mins = $start->diffInMinutes($end);
        }

        return $mins < 60 ? "{$mins}m" : floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
    }

    public function scheduledStart(): ?Carbon
    {
        return $this->scheduled_start_at ?? $this->scheduled_for;
    }

    public function scheduledEnd(): ?Carbon
    {
        if ($this->scheduled_end_at) {
            return $this->scheduled_end_at;
        }

        $start = $this->scheduledStart();
        if (!$start) {
            return null;
        }

        return $start->copy()->addMinutes((int) ($this->duration_minutes ?: 60));
    }

    public function actualStart(): ?Carbon
    {
        return $this->actual_start_at ?? $this->started_at;
    }

    public function actualEnd(): ?Carbon
    {
        return $this->actual_end_at ?? $this->ended_at;
    }

    public function completionTypeLabel(): ?string
    {
        return $this->completion_type ?: match ($this->status) {
            'cancelled' => 'cancelled',
            'ended', 'completed' => 'manual',
            default => null,
        };
    }

    protected function asUtcCarbon($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse($value, 'UTC');
    }

    protected function fromUtcCarbon($value): ?string
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}

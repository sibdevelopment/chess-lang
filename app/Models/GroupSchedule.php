<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupSchedule extends Model
{
    protected $fillable = [
        'group_id',
        'day_of_week',
        'start_time',
        'duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'duration_minutes' => 'integer',
        'is_active'   => 'boolean',
    ];

    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function liveClasses()
    {
        return $this->hasMany(LiveClass::class, 'group_schedule_id');
    }

    public function getDayLabelAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }
}

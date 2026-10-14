<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassQuestion extends Model
{
    protected $fillable = [
        'live_class_id', 'coach_id', 'position_fen',
        'expected_moves', 'arrows', 'highlights', 'text',
        'is_active', 'ended_at',
    ];

    protected $casts = [
        'expected_moves' => 'array',
        'arrows'         => 'array',
        'highlights'     => 'array',
        'is_active'      => 'boolean',
        'ended_at'       => 'datetime',
    ];

    public function liveClass() { return $this->belongsTo(LiveClass::class); }
    public function coach()     { return $this->belongsTo(User::class, 'coach_id'); }
    public function attempts()  { return $this->hasMany(ClassQuestionAttempt::class); }
}

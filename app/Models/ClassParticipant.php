<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassParticipant extends Model
{
    protected $table = 'class_participants';

    public $timestamps = false;

    protected $fillable = [
        'live_class_id',
        'user_id',
        'joined_at',
        'left_at',
        'last_seen_at',
    ];

    protected $casts = [
        'joined_at'    => 'datetime',
        'left_at'      => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /** A participant is "live" if heartbeat fired in the last 30 seconds. */
    public function isLive(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subSeconds(30));
    }

    public function liveClass() { return $this->belongsTo(LiveClass::class); }
    public function student()   { return $this->belongsTo(User::class, 'user_id'); }
}

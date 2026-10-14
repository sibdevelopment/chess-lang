<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassChatMessage extends Model
{
    protected $fillable = [
        'live_class_id',
        'user_id',
        'role',
        'body',
    ];

    public function liveClass() { return $this->belongsTo(LiveClass::class); }
    public function user()      { return $this->belongsTo(User::class); }
}

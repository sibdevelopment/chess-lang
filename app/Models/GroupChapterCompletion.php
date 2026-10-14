<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupChapterCompletion extends Model
{
    protected $fillable = [
        'group_id',
        'chapter_id',
        'completed_by',
        'live_class_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function liveClass()
    {
        return $this->belongsTo(LiveClass::class, 'live_class_id');
    }
}

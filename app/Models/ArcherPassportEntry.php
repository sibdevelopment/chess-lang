<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArcherPassportEntry extends Model
{
    protected $fillable = [
        'entry_key', 'user_id', 'activity_event_id', 'entry_type', 'title',
        'description', 'icon', 'source_type', 'source_id', 'occurred_at', 'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];
}

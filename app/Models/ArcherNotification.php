<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArcherNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'data', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];
}

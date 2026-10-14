<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArcherNotificationPreference extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'in_app_enabled', 'email_enabled', 'level_updates',
        'badge_updates', 'mission_updates', 'tournament_updates',
    ];

    protected $casts = [
        'in_app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'level_updates' => 'boolean',
        'badge_updates' => 'boolean',
        'mission_updates' => 'boolean',
        'tournament_updates' => 'boolean',
    ];
}

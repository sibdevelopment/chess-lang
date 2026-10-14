<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcherStarBalance extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'lifetime_stars', 'current_streak', 'longest_streak', 'last_activity_on',
    ];

    protected $casts = [
        'lifetime_stars' => 'integer',
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'last_activity_on' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

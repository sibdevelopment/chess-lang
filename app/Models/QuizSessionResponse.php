<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSessionResponse extends Model
{
    protected $fillable = [
        'quiz_session_id', 'quiz_id', 'user_id',
        'picked_index', 'is_correct', 'picked_at',
    ];

    protected $casts = [
        'picked_index' => 'integer',
        'is_correct'   => 'boolean',
        'picked_at'    => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

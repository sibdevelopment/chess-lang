<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Frozen ordered queue of quizzes inside a single session. Snapshot is taken
 * when the coach starts the session and picks a week, so even if quizzes are
 * edited mid-session the live broadcast is stable.
 */
class QuizSessionQuiz extends Model
{
    protected $fillable = ['quiz_session_id', 'quiz_id', 'position', 'shown'];

    protected $casts = [
        'shown'    => 'boolean',
        'position' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}

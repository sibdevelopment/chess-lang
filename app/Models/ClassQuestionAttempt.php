<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassQuestionAttempt extends Model
{
    protected $fillable = [
        'class_question_id', 'user_id', 'current_fen',
        'moves_played', 'correct_count', 'wrong_count',
        'completed', 'completed_at',
    ];

    protected $casts = [
        'moves_played' => 'array',
        'completed'    => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function question() { return $this->belongsTo(ClassQuestion::class, 'class_question_id'); }
    public function user()     { return $this->belongsTo(User::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeworkSubmission extends Model
{
    use HasFactory;

    protected $table = 'homework_submissions';

    protected $fillable = [
        'homework_id',
        'user_id',
        'status',
        'moves_played',
        'result',
        'marked_correct',
        'coach_note',
        'submitted_at',
    ];

    protected $casts = [
        'marked_correct' => 'boolean',
        'submitted_at'   => 'datetime',
    ];

    public function homework() { return $this->belongsTo(Homework::class); }
    public function student()  { return $this->belongsTo(User::class, 'user_id'); }
}

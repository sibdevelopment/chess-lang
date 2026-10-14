<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Homework extends Model
{
    use HasFactory;

    protected $table = 'homework';

    protected $fillable = [
        'title',
        'chapter_id',
        'group_id',
        'assigned_by',
        'source_class_id',
        'auto_assigned',
        'due_date',
    ];

    protected $casts = [
        'auto_assigned' => 'boolean',
        'due_date' => 'date',
    ];

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function sourceClass()
    {
        return $this->belongsTo(LiveClass::class, 'source_class_id');
    }

    public function submissions()
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function getStatsAttribute(): array
    {
        $subs = $this->submissions;

        $total = $subs->count();
        $done = $subs->where('status', 'done')->count();
        $reviewed = $subs->filter(
            fn (HomeworkSubmission $submission) => $submission->status === 'done'
                && $submission->marked_correct !== null
        )->count();
        $awaitingReview = $subs->filter(
            fn (HomeworkSubmission $submission) => $submission->status === 'done'
                && $submission->marked_correct === null
        )->count();
        $feedbackCount = $subs->filter(
            fn (HomeworkSubmission $submission) => trim((string) $submission->coach_note) !== ''
        )->count();

        $reviewStatus = match (true) {
            $done === 0 => 'No solved work yet',
            $awaitingReview > 0 && $reviewed > 0 => 'Review in progress',
            $awaitingReview > 0 => 'Needs coach review',
            default => 'Review complete',
        };

        return [
            'total' => $total,
            'pending' => $subs->where('status', 'pending')->count(),
            'in_progress' => $subs->where('status', 'in_progress')->count(),
            'done' => $done,
            'correct' => $subs->filter(
                fn (HomeworkSubmission $submission) => $submission->status === 'done'
                    && $submission->marked_correct === true
            )->count(),
            'wrong' => $subs->filter(
                fn (HomeworkSubmission $submission) => $submission->status === 'done'
                    && $submission->marked_correct === false
            )->count(),
            'reviewed' => $reviewed,
            'awaiting_review' => $awaitingReview,
            'feedback_count' => $feedbackCount,
            'completion_percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'review_percent' => $done > 0 ? (int) round(($reviewed / $done) * 100) : 0,
            'review_status' => $reviewStatus,
        ];
    }
}

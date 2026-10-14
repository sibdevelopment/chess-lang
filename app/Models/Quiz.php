<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single quiz question in the bank.
 *
 *   $quiz->options is an array of 4 items, each:
 *     ['kind' => 'text',  'value' => '64']
 *     ['kind' => 'image', 'value' => 'quizzes/options/abc.png']
 *
 *   $quiz->correct_index is 0..3.
 *   $quiz->type is 'text' (question is text) or 'picture' (question is image).
 */
class Quiz extends Model
{
    protected $attributes = [
        'time_limit_seconds' => 120,
    ];

    protected $fillable = [
        'week', 'category', 'age_min', 'age_max', 'type', 'question_text', 'image_path',
        'options', 'correct_index', 'time_limit_seconds', 'created_by',
    ];

    protected $casts = [
        'options'            => 'array',
        'week'               => 'integer',
        'age_min'            => 'integer',
        'age_max'            => 'integer',
        'correct_index'      => 'integer',
        'time_limit_seconds' => 'integer',
    ];

    /** Curated set of category labels. The DB column is free-form so admin can add more later. */
    public const DEFAULT_CATEGORIES = [
        'Chess',
        'Science',
        'Funny',
        'Riddles',
        'General Knowledge',
        'World',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSessionQuiz::class);
    }

    /**
     * Public-facing URL for the question image (if picture type).
     */
    public function questionImageUrl(): ?string
    {
        return $this->image_path ? asset('storage/' . ltrim($this->image_path, '/')) : null;
    }

    /**
     * Resolve a single option to a render-friendly array.
     */
    public function optionView(int $index): array
    {
        $opt = $this->options[$index] ?? ['kind' => 'text', 'value' => ''];
        $kind = $opt['kind'] ?? 'text';
        $value = $opt['value'] ?? '';
        return [
            'kind'  => $kind,
            'text'  => $kind === 'text'  ? (string) $value : null,
            'image' => $kind === 'image' ? asset('storage/' . ltrim($value, '/')) : null,
        ];
    }

    /**
     * All four options as render-friendly arrays.
     */
    public function optionsForRender(): array
    {
        $out = [];
        for ($i = 0; $i < 4; $i++) {
            $out[$i] = $this->optionView($i);
        }
        return $out;
    }

    public function isCorrect(int $picked): bool
    {
        return $picked === (int) $this->correct_index;
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChapterPuzzleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'original_checksum' => 'required|string|size:64',
            'single_game_pgn' => 'required|string|min:1',
            'puzzle_name' => 'nullable|string|max:255',
            'question' => 'nullable|string|max:2000',
            'answer' => 'nullable|string|max:2000',
            'hint' => 'nullable|string|max:2000',
            'explanation' => 'nullable|string|max:10000',
            'general_notes' => 'nullable|string|max:10000',
            'annotation' => 'nullable|string|max:10000',
        ];
    }
}

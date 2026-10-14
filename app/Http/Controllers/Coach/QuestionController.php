<?php

namespace App\Http\Controllers\Coach;

use App\Events\LiveClassQuestionChanged;
use App\Http\Controllers\Controller;
use App\Models\ClassQuestion;
use App\Models\ClassQuestionAttempt;
use App\Models\LiveClass;
use App\Support\LiveClassChess;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionController extends Controller
{
    /**
     * Coach creates a new question. Closes any previous active question
     * for this class so only one question is live at a time.
     */
    public function store(Request $request, LiveClass $class, LiveClassChess $chess)
    {
        abort_unless($class->coach_id === Auth::id(), 403);
        abort_unless($class->isLive(), 409, 'Only a live class can broadcast a puzzle.');

        $data = $request->validate([
            'position_fen'   => 'required|string',
            'expected_moves' => 'required|array|min:1|max:30',
            'expected_moves.*' => 'string',
            'arrows'         => 'nullable|array',
            'highlights'     => 'nullable|array',
            'text'           => 'nullable|string|max:500',
        ]);

        try {
            $solution = $chess->normalizeSolution($data['position_fen'], $data['expected_moves']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['expected_moves' => $e->getMessage()]);
        }

        // End any previous active question
        ClassQuestion::where('live_class_id', $class->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'ended_at' => now()]);

        $q = ClassQuestion::create([
            'live_class_id'  => $class->id,
            'coach_id'       => Auth::id(),
            'position_fen'   => $data['position_fen'],
            'expected_moves' => $solution,
            'arrows'         => $data['arrows'] ?? [],
            'highlights'     => $data['highlights'] ?? [],
            'text'           => $data['text'] ?? null,
            'is_active'      => true,
        ]);

        try {
            broadcast(new LiveClassQuestionChanged($class->room_id, 'started', $q->id));
        } catch (\Throwable $e) {
        }

        return response()->json(['ok' => true, 'question_id' => $q->id]);
    }

    /**
     * End the currently-active question for this class.
     */
    public function end(LiveClass $class)
    {
        abort_unless($class->coach_id === Auth::id(), 403);

        $questionId = ClassQuestion::where('live_class_id', $class->id)
            ->where('is_active', true)
            ->value('id');

        ClassQuestion::where('live_class_id', $class->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'ended_at' => now()]);

        try {
            broadcast(new LiveClassQuestionChanged($class->room_id, 'ended', $questionId));
        } catch (\Throwable $e) {
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Coach polls for the active question + every student's attempt.
     * This is what powers the "all student boards + leaderboard" view.
     */
    public function activeWithAttempts(LiveClass $class)
    {
        abort_unless($class->coach_id === Auth::id(), 403);

        $q = ClassQuestion::where('live_class_id', $class->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$q) {
            return response()->json(['active' => false]);
        }

        // Build the attempts list — include EVERY student in the group, even
        // if they haven't attempted yet, so the grid renders empty boards too.
        $studentIds = $class->group?->students->pluck('id', 'id') ?? collect();
        $attempts = ClassQuestionAttempt::with('user:id,name,username')
            ->where('class_question_id', $q->id)
            ->get()
            ->keyBy('user_id');

        $rows = [];
        foreach ($studentIds as $sid) {
            $student = $class->group->students->firstWhere('id', $sid);
            $a = $attempts->get($sid);
            $rows[] = [
                'user_id'       => $sid,
                'name'          => $student->name,
                'initials'      => $student->initials,
                'avatar_color'  => $student->avatar_color,
                'current_fen'   => $a?->current_fen ?? $q->position_fen,
                'correct_count' => (int) ($a?->correct_count ?? 0),
                'wrong_count'   => (int) ($a?->wrong_count ?? 0),
                'completed'     => (bool) ($a?->completed ?? false),
                'last_move_at'  => $a?->updated_at?->toIso8601String(),
            ];
        }

        // Leaderboard = sort by correct desc, then wrong asc, then time
        usort($rows, function ($a, $b) {
            if ($b['correct_count'] !== $a['correct_count']) return $b['correct_count'] <=> $a['correct_count'];
            return $a['wrong_count'] <=> $b['wrong_count'];
        });

        return response()->json([
            'active'   => true,
            'question' => [
                'id'             => $q->id,
                'position_fen'   => $q->position_fen,
                'expected_count' => count($q->expected_moves ?? []),
                'arrows'         => $q->arrows ?? [],
                'highlights'     => $q->highlights ?? [],
                'text'           => $q->text,
            ],
            'students' => $rows,
        ]);
    }
}

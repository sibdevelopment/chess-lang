<?php

namespace App\Http\Controllers\Student;

use App\Events\LiveClassQuestionChanged;
use App\Http\Controllers\Controller;
use App\Models\ClassQuestion;
use App\Models\ClassQuestionAttempt;
use App\Models\LiveClass;
use App\Support\LiveClassChess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class QuestionController extends Controller
{
    /**
     * Student polls for the currently-active question in this class.
     * Returns the puzzle position + arrows/highlights, and the student's
     * own attempt state (so the page survives reloads).
     */
    public function active(LiveClass $class)
    {
        $student = Auth::user();
        $this->assertEnrolled($class, $student);
        abort_unless($class->isLive(), 403, 'This class is not currently live.');

        $q = ClassQuestion::where('live_class_id', $class->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$q) {
            return response()->json(['active' => false]);
        }

        $attempt = ClassQuestionAttempt::firstOrCreate(
            ['class_question_id' => $q->id, 'user_id' => $student->id],
            ['current_fen' => $q->position_fen, 'moves_played' => [], 'correct_count' => 0, 'wrong_count' => 0]
        );

        return response()->json([
            'active'        => true,
            'question_id'   => $q->id,
            'position_fen'  => $q->position_fen,
            'current_fen'   => $attempt->current_fen,
            'arrows'        => $q->arrows ?? [],
            'highlights'    => $q->highlights ?? [],
            'text'          => $q->text,
            'correct_count' => (int) $attempt->correct_count,
            'wrong_count'   => (int) $attempt->wrong_count,
            'expected_count'=> count($q->expected_moves ?? []),
            'completed'     => (bool) $attempt->completed,
        ]);
    }

    /**
     * Student attempts a move on the current question.
     * Body: { from: 'e2', to: 'e4', promotion?: 'q' }
     * Server checks against the next-expected-move; awards 1 pt for correct,
     * counts wrongs, and calculates the authoritative next FEN itself.
     */
    public function attemptMove(Request $request, LiveClass $class, ClassQuestion $question, LiveClassChess $chess)
    {
        $student = Auth::user();
        $this->assertEnrolled($class, $student);
        abort_unless($class->isLive(), 403, 'This class is not currently live.');
        abort_unless($question->live_class_id === $class->id, 404);
        abort_unless($question->is_active, 410, 'Question is no longer active');

        $data = $request->validate([
            'from'          => ['required', 'string', 'regex:/^[a-h][1-8]$/i'],
            'to'            => ['required', 'string', 'regex:/^[a-h][1-8]$/i'],
            'promotion'     => ['nullable', 'string', 'regex:/^[qrbn]$/i'],
            // Accepted for backwards compatibility with the current browser,
            // but deliberately ignored: the server calculates the real FEN.
            'resulting_fen' => 'nullable|string|max:128',
        ]);

        ClassQuestionAttempt::firstOrCreate(
            ['class_question_id' => $question->id, 'user_id' => $student->id],
            ['current_fen' => $question->position_fen, 'moves_played' => [], 'correct_count' => 0, 'wrong_count' => 0]
        );

        $result = DB::transaction(function () use ($question, $student, $data, $chess) {
            $lockedQuestion = ClassQuestion::query()->lockForUpdate()->findOrFail($question->id);
            abort_unless($lockedQuestion->is_active, 410, 'Question is no longer active');

            $attempt = ClassQuestionAttempt::query()
                ->where('class_question_id', $lockedQuestion->id)
                ->where('user_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            $expected = $lockedQuestion->expected_moves ?? [];
            if ($attempt->completed) {
                return [
                    'changed' => false,
                    'correct' => true,
                    'completed' => true,
                    'correct_count' => (int) $attempt->correct_count,
                    'wrong_count' => (int) $attempt->wrong_count,
                    'next_fen' => $attempt->current_fen,
                    'expected_count' => count($expected),
                    'message' => 'You already finished this puzzle!',
                ];
            }

            $idx = (int) $attempt->correct_count;
            $played = $chess->executeCoordinate(
                $attempt->current_fen,
                strtolower($data['from']),
                strtolower($data['to']),
                isset($data['promotion']) ? strtolower($data['promotion']) : null,
            );

            $expectedKey = null;
            if (isset($expected[$idx])) {
                try {
                    $expectedKey = $chess->normalizeSolution($attempt->current_fen, [$expected[$idx]])[0] ?? null;
                } catch (\InvalidArgumentException) {
                    abort(422, 'The saved coach solution is invalid for this position.');
                }
            }

            $playedKey = $played ? $chess->coordinateKey($played['move']) : strtolower($data['from'].'-'.$data['to']);
            $isCorrect = $played !== null && $expectedKey !== null && $playedKey === $expectedKey;
            $moves = $attempt->moves_played ?? [];
            $moves[] = $playedKey;

            if ($isCorrect) {
                $attempt->correct_count = $idx + 1;
                $attempt->current_fen = $played['fen'];
                if ($attempt->correct_count >= count($expected)) {
                    $attempt->completed = true;
                    $attempt->completed_at = now();
                }
            } else {
                $attempt->wrong_count++;
            }

            $attempt->moves_played = $moves;
            $attempt->save();

            return [
                'changed' => true,
                'correct' => $isCorrect,
                'completed' => (bool) $attempt->completed,
                'correct_count' => (int) $attempt->correct_count,
                'wrong_count' => (int) $attempt->wrong_count,
                'next_fen' => $attempt->current_fen,
                'expected_count' => count($expected),
            ];
        }, 5);

        if ($result['changed']) {
            try {
                broadcast(new LiveClassQuestionChanged(
                    $class->room_id,
                    'attempted',
                    $question->id,
                    $student->id,
                ));
            } catch (Throwable $e) {
            }
        }

        unset($result['changed']);

        return response()->json($result);
    }

    private function assertEnrolled(LiveClass $class, $student): void
    {
        if (!$class->group || !$class->group->students()->where('users.id', $student->id)->exists()) {
            abort(403, 'You are not enrolled in this class group.');
        }
    }
}

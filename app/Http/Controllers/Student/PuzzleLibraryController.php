<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Puzzle;
use App\Models\PuzzleSet;
use App\Services\PuzzleLibraryService;
use Illuminate\Http\Request;

class PuzzleLibraryController extends Controller
{
    public function __construct(
        protected PuzzleLibraryService $service
    ) {
    }

    public function index(Request $request)
    {
        $student = $request->user();

        $sets = PuzzleSet::query()
            ->active()
            ->with('puzzles')
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(function (PuzzleSet $set) use ($student) {
                $set->progress = $set->progressForStudent($student);
                $nextPuzzle = $set->nextPuzzleForStudent($student);
                $set->start_url = $nextPuzzle
                    ? route('student.puzzle-library.solve', [$set, $nextPuzzle])
                    : route('student.puzzle-library.show', $set);
                return $set;
            });

        return view('student.puzzle-library.index', [
            'sets' => $sets,
        ]);
    }

    public function show(Request $request, PuzzleSet $puzzleSet)
    {
        $student = $request->user();
        $this->authorizeSet($student, $puzzleSet);

        $puzzleSet->load('puzzles');
        $attempts = $puzzleSet->puzzles
            ->load(['attempts' => fn ($q) => $q->where('user_id', $student->id)])
            ->map(function (Puzzle $puzzle) {
                $attempt = $puzzle->attempts->first();
                $puzzle->student_attempt = $attempt;
                return $puzzle;
            });

        $progress = $puzzleSet->progressForStudent($student);

        return view('student.puzzle-library.show', [
            'set' => $puzzleSet,
            'puzzles' => $attempts,
            'progress' => $progress,
        ]);
    }

    public function solve(Request $request, PuzzleSet $puzzleSet, Puzzle $puzzle)
    {
        $student = $request->user();
        $this->authorizeSet($student, $puzzleSet);
        abort_unless($puzzle->puzzle_set_id === $puzzleSet->id, 404);

        $puzzleSet->load('puzzles');
        $attempt = $this->service->loadOrCreateAttempt($student, $puzzle);
        $puzzles = $puzzleSet->puzzles->load(['attempts' => fn ($q) => $q->where('user_id', $student->id)]);

        return view('student.puzzle-library.solve', [
            'set' => $puzzleSet,
            'puzzle' => $puzzle,
            'attempt' => $attempt,
            'puzzles' => $puzzles,
            'progress' => $puzzleSet->progressForStudent($student),
        ]);
    }

    public function attemptMove(Request $request, PuzzleSet $puzzleSet, Puzzle $puzzle)
    {
        $student = $request->user();
        $this->authorizeSet($student, $puzzleSet);
        abort_unless($puzzle->puzzle_set_id === $puzzleSet->id, 404);

        $data = $request->validate([
            'from' => 'required|string|size:2',
            'to' => 'required|string|size:2',
        ]);

        $result = $this->service->submitStudentMove($student, $puzzle, $data['from'], $data['to']);

        return response()->json([
            'ok' => true,
            'correct' => $result['correct'],
            'completed' => $result['completed'],
            'message' => $result['message'],
            'current_fen' => $result['current_fen'],
            'auto_reply' => $result['auto_reply'] ?? null,
            'attempt' => [
                'status' => $result['attempt']->status,
                'wrong_attempts' => (int) $result['attempt']->wrong_attempts,
                'hint_count' => (int) $result['attempt']->hint_count,
                'current_ply_index' => (int) $result['attempt']->current_ply_index,
                'points' => (int) $result['attempt']->points,
            ],
            'progress' => $puzzleSet->progressForStudent($student),
        ]);
    }

    public function hint(Request $request, PuzzleSet $puzzleSet, Puzzle $puzzle)
    {
        $student = $request->user();
        $this->authorizeSet($student, $puzzleSet);
        abort_unless($puzzle->puzzle_set_id === $puzzleSet->id, 404);

        $hint = $this->service->revealHint($student, $puzzle);

        return response()->json([
            'ok' => true,
            'level' => $hint['level'],
            'from' => $hint['from'] ?? null,
            'to' => $hint['to'] ?? null,
            'notation' => $hint['notation'] ?? null,
            'attempt' => [
                'status' => $hint['attempt']->status ?? 'in_progress',
                'hint_count' => (int) ($hint['attempt']->hint_count ?? 0),
                'points' => (int) ($hint['attempt']->points ?? 0),
            ],
        ]);
    }

    private function authorizeSet($student, PuzzleSet $puzzleSet): void
    {
        abort_unless($puzzleSet->is_active, 404);
        abort_unless(
            $puzzleSet->whereKey($puzzleSet->id)->active()->exists(),
            403
        );
    }
}

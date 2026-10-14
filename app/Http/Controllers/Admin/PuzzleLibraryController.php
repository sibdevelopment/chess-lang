<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateChapterPuzzleRequest;
use App\Models\PuzzleCategory;
use App\Models\PuzzleSet;
use App\Services\PgnChapterDocumentService;
use App\Services\PuzzleLibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PuzzleLibraryController extends Controller
{
    public function __construct(
        protected PuzzleLibraryService $service,
        protected PgnChapterDocumentService $pgnService
    ) {
    }

    public function index(Request $request)
    {
        $sets = PuzzleSet::query()
            ->with('creator')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.puzzle-library.index', [
            'sets' => $sets,
        ]);
    }

    public function create()
    {
        return view('admin.puzzle-library.create', [
            'levels' => PuzzleSet::LEVELS,
            'categories' => PuzzleCategory::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'pgn_file' => 'required|file|mimes:pgn,txt|max:8192',
        ]);

        $preview = $this->service->previewUpload($data['pgn_file']);

        return response()->json([
            'ok' => true,
            'token' => $preview['token'],
            'file_name' => $preview['file_name'],
            'detected_puzzle_count' => $preview['detected_puzzle_count'],
            'valid_puzzle_count' => $preview['valid_puzzle_count'],
            'invalid_puzzle_count' => $preview['invalid_puzzle_count'],
            'invalid_puzzles' => $preview['invalid_puzzles'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'level' => ['nullable', Rule::in(PuzzleSet::LEVELS)],
            'category_id' => 'nullable|exists:puzzle_categories,id',
            'category_name' => 'nullable|string|max:100',
            'preview_token' => 'required|string|max:100',
            'source_file_name' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $data['level'] = $data['level'] ?? PuzzleSet::LEVELS[0];
        $data['category_name'] = filled($data['category_name'] ?? null) ? $data['category_name'] : 'Mixed';

        $set = $this->service->createPuzzleSet($data, (int) $request->user()->id);

        return redirect()
            ->route('admin.puzzle-library.show', $set)
            ->with('success', 'Puzzle set created successfully.');
    }

    public function show(PuzzleSet $puzzleSet)
    {
        $puzzleSet->load(['puzzles', 'creator', 'category']);

        $editorPgn = $puzzleSet->puzzles
            ->pluck('source_pgn')
            ->filter(fn ($pgn) => filled($pgn))
            ->implode("\n\n");

        $puzzleChecksums = $puzzleSet->puzzles
            ->values()
            ->map(fn ($puzzle) => $this->pgnService->checksum((string) $puzzle->source_pgn))
            ->all();

        $puzzleAnswers = $puzzleSet->puzzles
            ->values()
            ->mapWithKeys(fn ($puzzle, $index) => [
                $index => [
                    'answer_pgn' => (string) $puzzle->answer_pgn,
                    'final_fen' => (string) $puzzle->final_fen,
                    'saved_at' => $puzzle->updated_at?->toIso8601String(),
                ],
            ])
            ->all();

        return view('admin.puzzle-library.show', [
            'set' => $puzzleSet,
            'editorPgn' => $editorPgn,
            'puzzleChecksums' => $puzzleChecksums,
            'puzzleAnswers' => $puzzleAnswers,
        ]);
    }

    public function updatePuzzleAnswer(
        UpdateChapterPuzzleRequest $request,
        PuzzleSet $puzzleSet,
        int $puzzleIndex
    ) {
        if ($puzzleIndex < 0) {
            abort(404);
        }

        $data = $request->validated();
        $validatedPgn = $this->pgnService->validateSingleGame($data['single_game_pgn']);

        if (!($validatedPgn['ok'] ?? false)) {
            return response()->json([
                'message' => 'Puzzle answer could not be saved.',
                'errors' => $validatedPgn['errors'] ?? [
                    'single_game_pgn' => ['The puzzle answer is invalid.'],
                ],
            ], 422);
        }

        $puzzle = $puzzleSet->puzzles()
            ->where('puzzle_order', $puzzleIndex + 1)
            ->firstOrFail();

        $answerFen = $validatedPgn['headers']['fen']
            ?? 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

        if (trim($answerFen) !== trim((string) $puzzle->original_fen)) {
            return response()->json([
                'message' => 'Puzzle answer could not be saved.',
                'errors' => [
                    'single_game_pgn' => ['The original puzzle position cannot be changed.'],
                ],
            ], 422);
        }

        try {
            $answerPayload = $this->service->buildPuzzlePayload(
                $validatedPgn['normalized'],
                (int) $puzzle->puzzle_order
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Puzzle answer could not be saved.',
                'errors' => [
                    'single_game_pgn' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $result = DB::transaction(function () use ($puzzle, $puzzleSet, $data, $answerFen, $answerPayload, $validatedPgn) {
            $lockedPuzzle = $puzzleSet->puzzles()
                ->whereKey($puzzle->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sourceChecksum = $this->pgnService->checksum((string) $lockedPuzzle->source_pgn);
            if (!hash_equals($sourceChecksum, $data['original_checksum'])) {
                return ['conflict' => true];
            }

            if (trim($answerFen) !== trim((string) $lockedPuzzle->original_fen)) {
                return ['position_changed' => true];
            }

            $lockedPuzzle->update([
                'answer_pgn' => $validatedPgn['normalized'],
                'main_line' => $answerPayload['main_line'],
                'solution_uci' => $answerPayload['solution_uci'],
                'final_fen' => $answerPayload['final_fen'],
                'comments' => $answerPayload['comments'],
            ]);

            return [
                'conflict' => false,
                'checksum' => $sourceChecksum,
                'move_count' => count($answerPayload['main_line']),
                'final_fen' => $answerPayload['final_fen'],
            ];
        });

        if ($result['conflict'] ?? false) {
            return response()->json([
                'message' => 'This puzzle was changed in another session. Reload before saving.',
            ], 409);
        }

        if ($result['position_changed'] ?? false) {
            return response()->json([
                'message' => 'Puzzle answer could not be saved.',
                'errors' => [
                    'single_game_pgn' => ['The original puzzle position cannot be changed.'],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Answer saved successfully.',
            'checksum' => $result['checksum'],
            'saved_single_game_pgn' => $validatedPgn['normalized'],
            'move_count' => $result['move_count'],
            'puzzle_count' => $puzzleSet->puzzles()->count(),
            'final_fen' => $result['final_fen'],
        ]);
    }

    public function importPgn(Request $request, PuzzleSet $puzzleSet)
    {
        $data = $request->validate([
            'pgn_file' => 'nullable|required_without:pgn_text|file|mimes:pgn,txt|max:8192',
            'pgn_text' => 'nullable|required_without:pgn_file|string',
        ]);

        $pgn = trim((string) ($data['pgn_text'] ?? ''));
        if ($pgn === '' && isset($data['pgn_file'])) {
            $pgn = (string) file_get_contents($data['pgn_file']->getRealPath());
        }

        try {
            $result = $this->service->appendPuzzles($puzzleSet, $pgn);
        } catch (RuntimeException $exception) {
            return back()
                ->withErrors(['pgn_file' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.puzzle-library.show', $puzzleSet)
            ->with(
                'success',
                $result['appended_count'] . ' puzzle(s) imported successfully.'
            );
    }

    public function edit(PuzzleSet $puzzleSet)
    {
        return view('admin.puzzle-library.edit', [
            'set' => $puzzleSet->load('category'),
            'levels' => PuzzleSet::LEVELS,
            'categories' => PuzzleCategory::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, PuzzleSet $puzzleSet)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'level' => ['nullable', Rule::in(PuzzleSet::LEVELS)],
            'category_id' => 'nullable|exists:puzzle_categories,id',
            'category_name' => 'nullable|string|max:100',
            'is_active' => 'required|boolean',
        ]);

        $data['level'] = $data['level'] ?? $puzzleSet->level ?? PuzzleSet::LEVELS[0];
        $data['category_name'] = filled($data['category_name'] ?? null) ? $data['category_name'] : ($puzzleSet->category_name ?: 'Mixed');

        $category = null;
        if (!empty($data['category_id'])) {
            $category = PuzzleCategory::find($data['category_id']);
        } elseif (filled($data['category_name'])) {
            $category = PuzzleCategory::firstOrCreate(
                ['slug' => Str::slug($data['category_name'])],
                [
                    'name' => trim($data['category_name']),
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                ]
            );
        }

        $puzzleSet->update([
            'name' => trim($data['name']),
            'level' => $data['level'],
            'category_id' => $category?->id,
            'category_name' => $category?->name ?? trim((string) ($data['category_name'] ?? $puzzleSet->category_name)),
            'is_active' => (bool) $data['is_active'],
        ]);

        return redirect()
            ->route('admin.puzzle-library.show', $puzzleSet)
            ->with('success', 'Puzzle set updated successfully.');
    }

    public function toggleStatus(PuzzleSet $puzzleSet)
    {
        $puzzleSet->update([
            'is_active' => !$puzzleSet->is_active,
        ]);

        return back()->with('success', $puzzleSet->is_active ? 'Puzzle set activated.' : 'Puzzle set deactivated.');
    }
}

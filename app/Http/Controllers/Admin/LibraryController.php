<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateChapterPuzzleRequest;
use App\Models\Chapter;
use App\Services\PgnChapterDocumentService;
use App\Services\PgnPuzzleConflictException;
use App\Services\PgnValidationException;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * PGN Library is flat (no folders). Each PGN has:
 *   - level   (beginner / intermediate / advanced)
 *   - sequence_number (GLOBAL across all levels — 1,2,3,... unique)
 *
 * When admin uploads, we ask for the sequence number. The UI suggests
 * "next available" = max(sequence_number) + 1.
 */
class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $level = $request->get('level', 'all');

        $query = Chapter::with('uploader')->orderBy('sequence_number');
        if ($level !== 'all') $query->where('level', $level);
        $chapters = $query->get()->groupBy('level');

        $stats = collect(Chapter::LEVELS)
            ->mapWithKeys(fn (string $chapterLevel) => [$chapterLevel => Chapter::where('level', $chapterLevel)->count()])
            ->all();

        $nextSeq = ((int) Chapter::max('sequence_number')) + 1;

        return view('admin.library.index', compact('chapters', 'stats', 'nextSeq', 'level'));
    }

    public function viewChapter(Chapter $chapter, PgnChapterDocumentService $pgnService)
    {
        $puzzleChecksums = array_map(
            fn (string $game) => $pgnService->checksum($game),
            $pgnService->splitGames((string) $chapter->pgn)
        );
        $puzzleAnswers = $chapter->puzzleAnswers()
            ->orderBy('puzzle_index')
            ->get()
            ->mapWithKeys(fn ($answer) => [$answer->puzzle_index => [
                'answer_pgn' => $answer->answer_pgn,
                'final_fen' => $answer->final_fen,
                'saved_at' => optional($answer->updated_at)->toIso8601String(),
            ]]);

        $chapters = Chapter::query()
            ->with('uploader')
            ->orderBy('sequence_number')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.library.chapter', compact('chapter', 'puzzleChecksums', 'chapters', 'puzzleAnswers'));
    }

    public function updateChapterPuzzle(
        UpdateChapterPuzzleRequest $request,
        Chapter $chapter,
        int $puzzleIndex,
        PgnChapterDocumentService $pgnService
    ) {
        try {
            $result = $pgnService->saveChapterPuzzle(
                $chapter,
                $puzzleIndex,
                (string) $request->validated('original_checksum'),
                (string) $request->validated('single_game_pgn'),
                $request->user()?->id,
            );
        } catch (PgnPuzzleConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (PgnValidationException $e) {
            return response()->json([
                'message' => 'The submitted puzzle could not be saved.',
                'errors' => $e->errors,
            ], 422);
        }

        return response()->json([
            'message' => 'Answer saved successfully.',
            'checksum' => $result['checksum'],
            'saved_single_game_pgn' => $result['saved_single_game_pgn'],
            'move_count' => $result['move_count'],
            'puzzle_count' => $result['puzzle_count'],
            'final_fen' => $result['final_fen'],
        ]);
    }

    public function uploadPgn(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:200',
            'level'           => 'required|in:' . implode(',', Chapter::LEVELS),
            'sequence_number' => 'required|integer|min:1|unique:chapters,sequence_number',
            'pgn_file'        => 'required|file|mimes:pgn,txt|max:4096',
            'description'     => 'nullable|string|max:2000',
        ]);

        $file = $request->file('pgn_file');
        $pgn  = file_get_contents($file->getRealPath());
        $path = $file->store("pgn/{$data['level']}", 'local');

        preg_match_all('/\d+\./', $pgn, $matches);
        $moveCount = count($matches[0]);

        Chapter::create([
            'name'            => $data['name'],
            'pgn'             => $pgn,
            'level'           => $data['level'],
            'sequence_number' => $data['sequence_number'],
            'file_path'       => $path,
            'move_count'      => $moveCount,
            'sort_order'      => $data['sequence_number'],
            'description'     => $data['description'] ?? null,
            'uploaded_by'     => auth()->id(),
        ]);

        return back()->with('success',
            "PGN #{$data['sequence_number']} \"{$data['name']}\" added to the {$data['level']} library.");
    }

    public function deleteChapter(Chapter $chapter)
    {
        if ($chapter->file_path) Storage::delete($chapter->file_path);

        try {
            $chapter->delete();
        } catch (QueryException $e) {
            Log::warning('Chapter delete blocked.', [
                'user_id' => auth()->id(),
                'action' => 'chapter.delete',
                'model_type' => Chapter::class,
                'model_id' => $chapter->id,
                'route' => request()->route()?->getName(),
                'exception' => $e::class,
            ]);

            return back()->with('error', 'This chapter cannot be deleted because it is already assigned to groups, classes, or history.');
        }

        return back()->with('success', 'Chapter deleted.');
    }

    /**
     * Edit a chapter's metadata, and optionally replace its PGN.
     * If no file is uploaded, the current PGN stays unchanged.
     */
    public function updateChapter(Request $request, Chapter $chapter)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:200',
            'level'           => 'required|in:' . implode(',', Chapter::LEVELS),
            'sequence_number' => 'required|integer|min:1|unique:chapters,sequence_number,'.$chapter->id,
            'pgn_file'        => 'nullable|file|mimes:pgn,txt|max:4096',
            'pgn_text'        => 'nullable|string',
        ]);

        $newPgn = $chapter->pgn;
        $newPath = $chapter->file_path;
        $appendedCount = 0;

        $uploadedPgn = null;
        if ($request->filled('pgn_text')) {
            $uploadedPgn = (string) $request->input('pgn_text');
        } elseif ($request->hasFile('pgn_file')) {
            $file = $request->file('pgn_file');
            $uploadedPgn = file_get_contents($file->getRealPath());
        }

        if ($uploadedPgn !== null && trim($uploadedPgn) !== '') {
            try {
                $appendResult = app(PgnChapterDocumentService::class)->appendGames((string) $chapter->pgn, $uploadedPgn);
            } catch (PgnValidationException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages($e->errors);
            }
            $newPgn = $appendResult['pgn'];
            $appendedCount = $appendResult['appended_count'];

            if ($chapter->file_path) Storage::delete($chapter->file_path);
            $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $data['name']) ?: 'chapter';
            $newPath = "pgn/{$data['level']}/chapter_{$chapter->id}_{$safeName}.pgn";
            Storage::disk('local')->put($newPath, $newPgn);
        }

        preg_match_all('/\d+\./', (string) $newPgn, $matches);
        $moveCount = count($matches[0]);

        $chapter->update([
            'name'            => $data['name'],
            'level'           => $data['level'],
            'sequence_number' => $data['sequence_number'],
            'sort_order'      => $data['sequence_number'],
            'pgn'             => $newPgn,
            'file_path'       => $newPath,
            'move_count'      => $moveCount,
        ]);

        $verbed = ($uploadedPgn !== null && trim($uploadedPgn) !== '')
            ? "updated and appended {$appendedCount} puzzle(s)"
            : 'updated';
        return back()->with('success', "Chapter \"{$chapter->name}\" — {$verbed}.");
    }

    /** Stream the chapter's PGN as a downloadable .pgn file. */
    public function downloadChapter(Chapter $chapter)
    {
        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $chapter->name);
        $filename = sprintf('%03d-%s.pgn', $chapter->sequence_number ?? 0, $safeName ?: 'chapter');

        return response()->streamDownload(function () use ($chapter) {
            echo (string) $chapter->pgn;
        }, $filename, [
            'Content-Type' => 'application/x-chess-pgn',
        ]);
    }

    // ── Legacy folder routes: the folder system is deprecated.
    //    We keep stubs so old links don't 500; they just redirect back.
    public function createFolder(Request $request) {
        return back()->with('info', 'Folders have been removed. Upload PGNs directly with a level and sequence number.');
    }
    public function deleteFolder($folder) {
        return back()->with('info', 'Folders have been removed.');
    }

    // ── Database page: now a simple overview. Every coach automatically
    //    sees every uploaded PGN — no per-folder assignment needed.
    public function database(Request $request)
    {
        // Coach-wise view: each coach card shows the chapters they have access to
        // (currently the full library since access is global). The layout mirrors
        // exactly what each coach sees on their own coach.database page.
        $coaches = User::coaches()->active()
            ->withCount('coachedGroups as group_count')
            ->orderBy('name')
            ->get();

        $totals = collect(Chapter::LEVELS)
            ->mapWithKeys(fn (string $chapterLevel) => [$chapterLevel => Chapter::where('level', $chapterLevel)->count()])
            ->all();

        // The full library — same data each coach gets. We pass it once and
        // the view loops it under each coach card.
        $library = Chapter::with('uploader')
            ->orderBy('level')
            ->orderBy('sequence_number')
            ->get()
            ->groupBy('level');

        return view('admin.library.database', compact('coaches', 'totals', 'library'));
    }

    public function assignDatabase(Request $request) {
        return back()->with('info', 'Coaches now see all PGNs automatically — no manual assignment needed.');
    }
    public function revokeDatabase($assignment) {
        return back()->with('info', 'Coaches now see all PGNs automatically — no manual assignment needed.');
    }
}

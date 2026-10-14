<?php

namespace App\Services;

use App\Models\Puzzle;
use App\Models\PuzzleCategory;
use App\Models\PuzzleSet;
use App\Models\StudentPuzzleAttempt;
use App\Models\User;
use App\Services\ArcherJourney\ArcherLmsEventBridge;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PChess\Chess\Chess;
use RuntimeException;

class PuzzleLibraryService
{
    private const DEFAULT_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    private const RESULTS = ['1-0', '0-1', '1/2-1/2', '0.5-0.5', '*'];

    public function __construct(
        protected PgnChapterDocumentService $pgnService
    ) {}

    public function previewUpload(UploadedFile $file): array
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $games = $this->pgnService->splitGames($contents);
        $valid = [];
        $invalid = [];

        foreach ($games as $index => $game) {
            $validated = $this->pgnService->validateSingleGame($game);
            if (! ($validated['ok'] ?? false)) {
                $invalid[] = [
                    'index' => $index + 1,
                    'errors' => array_values($validated['errors']['single_game_pgn'] ?? ['Invalid PGN puzzle.']),
                ];

                continue;
            }

            try {
                $valid[] = $this->buildPuzzlePayload($validated['normalized'], $index + 1);
            } catch (\Throwable $e) {
                $invalid[] = [
                    'index' => $index + 1,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        $token = (string) Str::uuid();
        $tempPath = "puzzle-library/tmp/{$token}.pgn";
        Storage::disk('local')->put($tempPath, $contents);

        return [
            'token' => $token,
            'temp_path' => $tempPath,
            'file_name' => $file->getClientOriginalName(),
            'detected_puzzle_count' => count($games),
            'valid_puzzle_count' => count($valid),
            'invalid_puzzle_count' => count($invalid),
            'invalid_puzzles' => $invalid,
            'valid_puzzles' => $valid,
        ];
    }

    public function createPuzzleSet(array $data, int $createdBy): PuzzleSet
    {
        $tempPath = "puzzle-library/tmp/{$data['preview_token']}.pgn";
        if (! Storage::disk('local')->exists($tempPath)) {
            throw new RuntimeException('Preview file not found. Upload the PGN again.');
        }

        $contents = Storage::disk('local')->get($tempPath);
        $games = $this->pgnService->splitGames($contents);
        $valid = [];
        $invalid = [];

        foreach ($games as $index => $game) {
            $validated = $this->pgnService->validateSingleGame($game);
            if (! ($validated['ok'] ?? false)) {
                $invalid[] = $index + 1;

                continue;
            }

            $valid[] = $this->buildPuzzlePayload($validated['normalized'], $index + 1);
        }

        if (count($valid) === 0 || ! empty($invalid)) {
            throw new RuntimeException('This PGN preview is no longer valid. Please upload the file again.');
        }

        $category = $this->resolveCategory($data['category_id'] ?? null, $data['category_name'] ?? null, $createdBy);
        $sourceFileName = $data['source_file_name'] ?? ('puzzle-set-'.Str::slug($data['name']).'.pgn');
        $fileName = Str::slug(pathinfo($sourceFileName, PATHINFO_FILENAME) ?: $data['name']) ?: 'puzzle-set';
        $finalPath = "puzzle-library/sets/{$fileName}-".now()->format('YmdHis').'.pgn';
        Storage::disk('local')->put($finalPath, $contents);

        $set = DB::transaction(function () use ($data, $createdBy, $category, $contents, $sourceFileName, $finalPath, $valid) {
            $set = PuzzleSet::create([
                'name' => trim($data['name']),
                'level' => $data['level'],
                'category_id' => $category?->id,
                'category_name' => $category?->name ?? trim((string) $data['category_name']),
                'source_pgn' => $contents,
                'source_file_name' => $sourceFileName,
                'source_file_path' => $finalPath,
                'total_puzzles' => count($valid),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $createdBy,
            ]);

            foreach ($valid as $index => $payload) {
                $set->puzzles()->create([
                    'puzzle_order' => $index + 1,
                    'source_pgn' => $payload['source_pgn'],
                    'original_fen' => $payload['original_fen'],
                    'side_to_move' => $payload['side_to_move'],
                    'answer_pgn' => $payload['answer_pgn'],
                    'main_line' => $payload['main_line'],
                    'solution_uci' => $payload['solution_uci'],
                    'final_fen' => $payload['final_fen'],
                    'comments' => $payload['comments'],
                ]);
            }

            return $set;
        });

        Storage::disk('local')->delete($tempPath);

        return $set->load(['puzzles', 'category', 'creator']);
    }

    public function appendPuzzles(PuzzleSet $puzzleSet, string $pgn): array
    {
        $games = $this->pgnService->splitGames($pgn);
        if (empty($games)) {
            throw new RuntimeException('The imported PGN does not contain any puzzles.');
        }

        $payloads = [];
        foreach ($games as $index => $game) {
            $validated = $this->pgnService->validateSingleGame($game);
            if (! ($validated['ok'] ?? false)) {
                $message = collect($validated['errors'] ?? [])
                    ->flatten()
                    ->first() ?? 'Invalid PGN puzzle.';
                throw new RuntimeException('Puzzle '.($index + 1).': '.$message);
            }

            $payloads[] = $this->buildPuzzlePayload($validated['normalized'], $index + 1);
        }

        $result = DB::transaction(function () use ($puzzleSet, $payloads) {
            $lockedSet = PuzzleSet::query()->lockForUpdate()->findOrFail($puzzleSet->id);
            $lastOrder = (int) ($lockedSet->puzzles()->max('puzzle_order') ?? 0);

            foreach ($payloads as $index => $payload) {
                $lockedSet->puzzles()->create([
                    'puzzle_order' => $lastOrder + $index + 1,
                    'source_pgn' => $payload['source_pgn'],
                    'original_fen' => $payload['original_fen'],
                    'side_to_move' => $payload['side_to_move'],
                    'answer_pgn' => $payload['answer_pgn'],
                    'main_line' => $payload['main_line'],
                    'solution_uci' => $payload['solution_uci'],
                    'final_fen' => $payload['final_fen'],
                    'comments' => $payload['comments'],
                ]);
            }

            $incomingPgn = implode("\n\n", array_column($payloads, 'source_pgn'));
            $combinedPgn = collect([(string) $lockedSet->source_pgn, $incomingPgn])
                ->map(fn ($value) => trim($value))
                ->filter()
                ->implode("\n\n");
            $totalPuzzles = $lockedSet->puzzles()->count();

            $lockedSet->update([
                'source_pgn' => $combinedPgn,
                'total_puzzles' => $totalPuzzles,
            ]);

            return [
                'source_pgn' => $combinedPgn,
                'source_file_path' => $lockedSet->source_file_path,
                'appended_count' => count($payloads),
                'total_puzzles' => $totalPuzzles,
            ];
        });

        if (filled($result['source_file_path'])) {
            Storage::disk('local')->put($result['source_file_path'], $result['source_pgn']);
        }

        return $result;
    }

    public function buildPuzzlePayload(string $singleGamePgn, int $order = 1): array
    {
        $headers = $this->parseHeaders($singleGamePgn);
        $originalFen = $headers['fen'] ?? self::DEFAULT_FEN;
        $sideToMove = Str::contains($originalFen, ' b ') ? 'b' : 'w';
        $mainLineSan = $this->extractMainLineSan($singleGamePgn);
        if (empty($mainLineSan)) {
            throw new RuntimeException("Puzzle {$order} does not contain a playable main line.");
        }

        $result = $this->replayMainLine($originalFen, $mainLineSan);

        return [
            'source_pgn' => $singleGamePgn,
            'original_fen' => $originalFen,
            'side_to_move' => $sideToMove,
            'answer_pgn' => $singleGamePgn,
            'main_line' => $mainLineSan,
            'solution_uci' => $result['uci'],
            'final_fen' => $result['final_fen'],
            'comments' => $this->extractComments($singleGamePgn),
        ];
    }

    public function loadOrCreateAttempt(User $student, Puzzle $puzzle): StudentPuzzleAttempt
    {
        return StudentPuzzleAttempt::firstOrCreate(
            ['user_id' => $student->id, 'puzzle_id' => $puzzle->id],
            [
                'status' => 'not_started',
                'student_moves' => [],
                'current_ply_index' => 0,
                'wrong_attempts' => 0,
                'hint_count' => 0,
                'current_hint_level' => 0,
                'hint_used' => false,
                'current_fen' => $puzzle->original_fen,
            ]
        );
    }

    public function submitStudentMove(User $student, Puzzle $puzzle, string $from, string $to): array
    {
        $attempt = $this->loadOrCreateAttempt($student, $puzzle);
        $solution = $puzzle->solution_uci ?? [];
        $currentFen = $attempt->current_fen ?: $puzzle->original_fen;

        if ($attempt->status === 'completed') {
            return [
                'correct' => true,
                'completed' => true,
                'message' => 'Puzzle solved successfully.',
                'current_fen' => $currentFen,
                'attempt' => $attempt,
            ];
        }

        if (! isset($solution[$attempt->current_ply_index])) {
            $attempt->update([
                'status' => 'completed',
                'completed_at' => $attempt->completed_at ?: now(),
            ]);

            app(ArcherLmsEventBridge::class)->puzzleCompleted($attempt->fresh(), $puzzle);

            return [
                'correct' => true,
                'completed' => true,
                'message' => 'Puzzle solved successfully.',
                'current_fen' => $currentFen,
                'attempt' => $attempt->fresh(),
            ];
        }

        $expected = strtolower((string) $solution[$attempt->current_ply_index]);
        $played = strtolower($from.$to);

        if ($played !== $expected) {
            $attempt->update([
                'status' => $attempt->status === 'not_started' ? 'in_progress' : $attempt->status,
                'wrong_attempts' => $attempt->wrong_attempts + 1,
                'started_at' => $attempt->started_at ?: now(),
            ]);

            return [
                'correct' => false,
                'completed' => false,
                'message' => 'Wrong move. Try again.',
                'current_fen' => $currentFen,
                'attempt' => $attempt->fresh(),
            ];
        }

        $game = new Chess($currentFen);
        $move = $game->move([
            'from' => substr($expected, 0, 2),
            'to' => substr($expected, 2, 2),
            'promotion' => strlen($expected) > 4 ? substr($expected, 4, 1) : null,
        ]);

        if (! $move) {
            throw new RuntimeException('The stored puzzle answer is invalid for the current position.');
        }

        $studentMoves = $attempt->student_moves ?? [];
        $studentMoves[] = [
            'from' => $from,
            'to' => $to,
            'uci' => $expected,
            'played_at' => now()->toIso8601String(),
        ];

        $nextPlyIndex = $attempt->current_ply_index + 1;
        $autoReply = null;

        if (isset($solution[$nextPlyIndex])) {
            $reply = strtolower((string) $solution[$nextPlyIndex]);
            $replyMove = $game->move([
                'from' => substr($reply, 0, 2),
                'to' => substr($reply, 2, 2),
                'promotion' => strlen($reply) > 4 ? substr($reply, 4, 1) : null,
            ]);

            if ($replyMove) {
                $autoReply = [
                    'uci' => $reply,
                    'san' => $replyMove->san ?? strtoupper($reply),
                ];
                $nextPlyIndex++;
            }
        }

        $completed = $nextPlyIndex >= count($solution);
        $attempt->update([
            'status' => $completed ? 'completed' : 'in_progress',
            'student_moves' => $studentMoves,
            'current_ply_index' => $nextPlyIndex,
            'current_hint_level' => 0,
            'current_fen' => $game->fen(),
            'started_at' => $attempt->started_at ?: now(),
            'completed_at' => $completed ? now() : null,
        ]);

        if ($completed) {
            app(ArcherLmsEventBridge::class)->puzzleCompleted($attempt->fresh(), $puzzle);
        }

        return [
            'correct' => true,
            'completed' => $completed,
            'message' => $completed ? 'Puzzle solved successfully.' : 'Correct move.',
            'current_fen' => $game->fen(),
            'auto_reply' => $autoReply,
            'attempt' => $attempt->fresh(),
        ];
    }

    public function revealHint(User $student, Puzzle $puzzle): array
    {
        $attempt = $this->loadOrCreateAttempt($student, $puzzle);
        $solution = $puzzle->solution_uci ?? [];
        $expected = strtolower((string) ($solution[$attempt->current_ply_index] ?? ''));
        if ($expected === '') {
            return [
                'level' => 0,
                'message' => 'Puzzle solved successfully.',
            ];
        }

        $nextLevel = min(3, max(1, $attempt->current_hint_level + 1));
        $hint = [
            'level' => $nextLevel,
            'from' => substr($expected, 0, 2),
            'to' => substr($expected, 2, 2),
            'notation' => $this->uciToHintNotation($expected),
        ];

        $attempt->update([
            'hint_used' => true,
            'hint_count' => $attempt->hint_count + 1,
            'current_hint_level' => $nextLevel,
            'status' => $attempt->status === 'not_started' ? 'in_progress' : $attempt->status,
            'started_at' => $attempt->started_at ?: now(),
        ]);

        return $hint + ['attempt' => $attempt->fresh()];
    }

    public function eligibleLevelsForStudent(User $student): array
    {
        return $student->groups()
            ->where('groups.is_active', true)
            ->pluck('groups.level')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCategory(?int $categoryId, ?string $customName, int $createdBy): ?PuzzleCategory
    {
        if ($categoryId) {
            return PuzzleCategory::find($categoryId);
        }

        $name = trim((string) $customName);
        if ($name === '') {
            return null;
        }

        return PuzzleCategory::firstOrCreate(
            ['slug' => Str::slug($name)],
            [
                'name' => $name,
                'is_active' => true,
                'created_by' => $createdBy,
            ]
        );
    }

    private function parseHeaders(string $pgn): array
    {
        preg_match_all('/^\[([A-Za-z0-9_]+)\s+"((?:[^"\\\\]|\\\\.)*)"\]\s*$/m', $pgn, $matches, PREG_SET_ORDER);
        $headers = [];
        foreach ($matches as $match) {
            $headers[strtolower($match[1])] = stripcslashes($match[2]);
        }

        return $headers;
    }

    private function extractComments(string $pgn): ?string
    {
        preg_match_all('/\{([^}]*)\}/', $pgn, $matches);
        $comments = collect($matches[1] ?? [])
            ->map(fn ($comment) => trim((string) $comment))
            ->filter()
            ->values()
            ->all();

        return empty($comments) ? null : implode("\n", $comments);
    }

    private function extractMainLineSan(string $pgn): array
    {
        $body = preg_replace('/^\[[^\n]+\]\s*$/m', '', $pgn);
        $body = preg_replace('/\{[^}]*\}/', ' ', $body);
        $body = preg_replace('/;[^\n]*/', ' ', $body);
        $body = $this->stripVariations($body);
        $body = preg_replace('/\$\d+/', ' ', $body);
        $tokens = preg_split('/\s+/', trim((string) $body)) ?: [];

        return collect($tokens)
            ->map(fn ($token) => trim((string) $token))
            ->filter(function (string $token) {
                if ($token === '') {
                    return false;
                }
                if (in_array($token, self::RESULTS, true)) {
                    return false;
                }
                if (preg_match('/^\d+\.(\.\.)?$/', $token)) {
                    return false;
                }
                if (preg_match('/^\d+\.(.+)$/', $token, $match)) {
                    return trim($match[1]) !== '';
                }

                return true;
            })
            ->map(function (string $token) {
                if (preg_match('/^\d+\.(.+)$/', $token, $match)) {
                    return trim($match[1]);
                }

                return $token;
            })
            ->values()
            ->all();
    }

    private function replayMainLine(string $fen, array $mainLineSan): array
    {
        $game = new Chess($fen ?: self::DEFAULT_FEN);
        $uci = [];

        foreach ($mainLineSan as $san) {
            $move = $game->move($san);
            if (! $move) {
                throw new RuntimeException("Invalid move in PGN answer line: {$san}");
            }
            $uci[] = strtolower(($move->from ?? '').($move->to ?? '').($move->promotion ?? ''));
        }

        return [
            'uci' => $uci,
            'final_fen' => $game->fen(),
        ];
    }

    private function stripVariations(string $body): string
    {
        $depth = 0;
        $output = '';
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];
            if ($char === '(') {
                $depth++;

                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);

                continue;
            }
            if ($depth === 0) {
                $output .= $char;
            }
        }

        return $output;
    }

    private function uciToHintNotation(string $uci): string
    {
        $from = substr($uci, 0, 2);
        $to = substr($uci, 2, 2);
        $promotion = strlen($uci) > 4 ? '='.strtoupper(substr($uci, 4, 1)) : '';

        return strtoupper($from).' → '.strtoupper($to).$promotion;
    }
}

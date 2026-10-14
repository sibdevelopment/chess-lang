<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\ChapterPuzzleAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PgnChapterDocumentService
{
    public function appendGames(string $chapterPgn, string $appendPgn): array
    {
        $existingGames = $this->splitGames($chapterPgn);
        $incomingGames = $this->splitGames($appendPgn);

        if (empty($incomingGames)) {
            throw new PgnValidationException([
                'pgn_file' => ['The uploaded PGN does not contain any puzzles.'],
            ]);
        }

        $normalizedIncomingGames = [];
        foreach ($incomingGames as $game) {
            $validated = $this->validateSingleGame($game);
            if (!$validated['ok']) {
                throw new PgnValidationException([
                    'pgn_file' => $validated['errors']['single_game_pgn'] ?? ['One or more uploaded puzzles are invalid.'],
                ]);
            }

            $normalizedIncomingGames[] = $validated['normalized'];
        }

        $mergedGames = array_merge($existingGames, $normalizedIncomingGames);

        return [
            'pgn' => implode("\n\n", $mergedGames),
            'existing_count' => count($existingGames),
            'appended_count' => count($normalizedIncomingGames),
            'total_count' => count($mergedGames),
        ];
    }

    public function splitGames(string $pgn): array
    {
        $text = $this->normalizeDocument($pgn);
        if ($text === '') {
            return [];
        }

        // Keep server-side chapter splitting aligned with the browser parser.
        // Lichess study exports often contain many header-only games before the
        // first movetext block, and the legacy scanner collapsed those into one
        // "game". That broke puzzle_index mapping and made saved answers fail
        // original-FEN validation for many positions.
        if (preg_match('/^\[Event\s+/mi', $text) === 1) {
            $blocks = preg_split('/(?=^\[Event\s+)/mi', $text) ?: [];
            return array_values(array_filter(array_map(
                fn ($block) => trim((string) $block),
                $blocks
            ), fn ($block) => $block !== ''));
        }

        $lines = preg_split("/\n/", $text);
        $games = [];
        $current = [];
        $hasBody = false;
        $inBraceComment = false;

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            $isHeaderLine = !$inBraceComment && preg_match('/^\[[A-Za-z0-9_]+\s+"(?:[^"\\\\]|\\\\.)*"\]\s*$/', $trimmed) === 1;

            if ($isHeaderLine && $hasBody && !empty($current)) {
                $games[] = trim(implode("\n", $current));
                $current = [$line];
                $hasBody = false;
            } else {
                $current[] = $line;
                if (!$isHeaderLine && trim($line) !== '') {
                    $hasBody = true;
                }
            }

            $inBraceComment = $this->advanceBraceCommentState($line, $inBraceComment);
        }

        if (!empty($current)) {
            $games[] = trim(implode("\n", $current));
        }

        return array_values(array_filter($games, fn ($game) => trim($game) !== ''));
    }

    public function replaceGame(string $chapterPgn, int $index, string $replacement): string
    {
        $games = $this->splitGames($chapterPgn);

        if (!array_key_exists($index, $games)) {
            throw new RuntimeException('Invalid puzzle index.');
        }

        $games[$index] = $this->normalizeSingleGame($replacement);

        return implode("\n\n", $games);
    }

    public function countGames(string $pgn): int
    {
        return count($this->splitGames($pgn));
    }

    public function mergeSavedAnswers(string $chapterPgn, iterable $answers): string
    {
        $games = $this->splitGames($chapterPgn);
        if (empty($games)) {
            return $this->normalizeDocument($chapterPgn);
        }

        foreach ($answers as $answer) {
            $index = (int) (is_array($answer) ? ($answer['puzzle_index'] ?? -1) : ($answer->puzzle_index ?? -1));
            $answerPgn = (string) (is_array($answer) ? ($answer['answer_pgn'] ?? '') : ($answer->answer_pgn ?? ''));

            if ($index < 0 || !array_key_exists($index, $games) || trim($answerPgn) === '') {
                continue;
            }

            $games[$index] = $this->normalizeSingleGame($answerPgn);
        }

        return implode("\n\n", $games);
    }

    public function checksum(string $singleGamePgn): string
    {
        return hash('sha256', $this->normalizeSingleGame($singleGamePgn));
    }

    public function saveChapterPuzzle(Chapter $chapter, int $puzzleIndex, string $originalChecksum, string $replacementPgn, ?int $savedBy = null): array
    {
        $single = $this->validateSingleGame($replacementPgn);
        if (!$single['ok']) {
            throw new PgnValidationException($single['errors']);
        }

        $answerHeaders = $single['headers'];
        $answerFen = $answerHeaders['fen'] ?? 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $backupPath = 'pgn/backups/chapter_answers/chapter_' . $chapter->id . '/' . now()->format('Ymd_His_u') . '_' . $puzzleIndex . '.pgn';
        $result = DB::transaction(function () use ($chapter, $puzzleIndex, $originalChecksum, $single, $answerFen, $backupPath, $savedBy) {
            /** @var Chapter $lockedChapter */
            $lockedChapter = Chapter::query()->lockForUpdate()->findOrFail($chapter->id);

            $games = $this->splitGames((string) $lockedChapter->pgn);
            if (!array_key_exists($puzzleIndex, $games)) {
                throw new PgnValidationException([
                    'single_game_pgn' => ['The selected puzzle index is invalid.'],
                ]);
            }

            $currentGame = $games[$puzzleIndex];
            if (!hash_equals($this->checksum($currentGame), $originalChecksum)) {
                throw new PgnPuzzleConflictException('This chapter was changed in another session. Reload the chapter before saving.');
            }

            $source = $this->validateSingleGame($currentGame);
            if (!$source['ok']) {
                throw new PgnValidationException($source['errors']);
            }

            $sourceFen = $source['headers']['fen']
                ?? 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
            if ($this->normalizeFen($answerFen) !== $this->normalizeFen($sourceFen)) {
                throw new PgnValidationException([
                    'single_game_pgn' => ['The original puzzle position cannot be changed.'],
                ]);
            }

            $sourceChecksum = $this->checksum($currentGame);
            $moveCount = $this->projectMoveCount($single['normalized']);
            $finalFen = $this->extractFinalFen($single['normalized'], $answerFen);

            Storage::disk('local')->put($backupPath, $single['normalized']);

            ChapterPuzzleAnswer::updateOrCreate(
                [
                    'chapter_id' => $lockedChapter->id,
                    'puzzle_index' => $puzzleIndex,
                ],
                [
                    'original_checksum' => $sourceChecksum,
                    'answer_pgn' => $single['normalized'],
                    'final_fen' => $finalFen,
                    'saved_by' => $savedBy,
                ]
            );

            return [
                'updated_full_pgn' => (string) $lockedChapter->pgn,
                'checksum' => $sourceChecksum,
                'move_count' => $moveCount,
                'puzzle_count' => count($games),
                'file_path' => $lockedChapter->file_path,
                'final_fen' => $finalFen,
            ];
        });

        return [
            'saved_single_game_pgn' => $single['normalized'],
            'checksum' => $result['checksum'],
            'answer_checksum' => $this->checksum($single['normalized']),
            'move_count' => $result['move_count'],
            'puzzle_count' => $result['puzzle_count'],
            'backup_path' => $backupPath,
            'updated_full_pgn' => $result['updated_full_pgn'],
            'final_fen' => $result['final_fen'],
        ];
    }

    public function validateSingleGame(string $pgn): array
    {
        $game = $this->normalizeSingleGame($pgn);
        if ($game === '') {
            return ['ok' => false, 'errors' => ['single_game_pgn' => ['Puzzle PGN cannot be empty.']]];
        }

        if ($this->countGames($game) !== 1) {
            return ['ok' => false, 'errors' => ['single_game_pgn' => ['Replacement must contain exactly one PGN game.']]];
        }

        $headers = $this->parseHeaders($game);
        $metadataErrors = $this->validateMetadataHeaders($headers);
        if ($metadataErrors) {
            return ['ok' => false, 'errors' => $metadataErrors];
        }

        $fen = $headers['fen'] ?? 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        $fenError = $this->validateFen($fen);
        if ($fenError) {
            return ['ok' => false, 'errors' => ['single_game_pgn' => [$fenError]]];
        }

        $tokens = $this->tokenizePgnBody($this->stripHeaders($game));
        try {
            $this->replayTokens($tokens, $this->parseFen($fen));
        } catch (RuntimeException $e) {
            return ['ok' => false, 'errors' => ['single_game_pgn' => [$e->getMessage()]]];
        }

        return [
            'ok' => true,
            'errors' => [],
            'normalized' => $game,
            'headers' => $headers,
        ];
    }

    public function projectMoveCount(string $pgn): int
    {
        preg_match_all('/\d+\./', $pgn, $matches);
        return count($matches[0]);
    }

    private function normalizeDocument(string $pgn): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $pgn);
        return trim($text);
    }

    private function normalizeSingleGame(string $pgn): string
    {
        return $this->normalizeDocument($pgn);
    }

    private function stripHeaders(string $game): string
    {
        return trim(preg_replace('/^\[[^\]]*\]\s*$/m', '', $game) ?? '');
    }

    private function parseHeaders(string $game): array
    {
        preg_match_all('/^\[([A-Za-z0-9_]+)\s+"((?:[^"\\\\]|\\\\.)*)"\]\s*$/m', $game, $matches, PREG_SET_ORDER);
        $headers = [];
        foreach ($matches as $match) {
            $headers[strtolower($match[1])] = stripcslashes($match[2]);
        }

        return $headers;
    }

    private function validateMetadataHeaders(array $headers): array
    {
        $limits = [
            'archerpuzzlename' => ['puzzle_name', 255],
            'archerquestion' => ['question', 2000],
            'archeranswer' => ['answer', 2000],
            'archerhint' => ['hint', 2000],
            'archerexplanation' => ['explanation', 10000],
            'archernotes' => ['general_notes', 10000],
        ];

        $errors = [];
        foreach ($limits as $header => [$field, $max]) {
            if (isset($headers[$header]) && mb_strlen($headers[$header]) > $max) {
                $errors[$field] = ["The {$field} field may not be greater than {$max} characters."];
            }
        }

        return $errors;
    }

    private function advanceBraceCommentState(string $line, bool $inBraceComment): bool
    {
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];
            if ($char === '{') {
                $inBraceComment = true;
            } elseif ($char === '}' && $inBraceComment) {
                $inBraceComment = false;
            }
        }
        return $inBraceComment;
    }

    private function tokenizePgnBody(string $body): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($body);

        while ($i < $len) {
            $ch = $body[$i];
            if (ctype_space($ch)) {
                $i++;
                continue;
            }
            if ($ch === '{') {
                $end = strpos($body, '}', $i);
                if ($end === false) {
                    break;
                }
                $tokens[] = ['type' => 'comment', 'value' => trim(substr($body, $i + 1, $end - $i - 1))];
                $i = $end + 1;
                continue;
            }
            if ($ch === '(') {
                $tokens[] = ['type' => 'open'];
                $i++;
                continue;
            }
            if ($ch === ')') {
                $tokens[] = ['type' => 'close'];
                $i++;
                continue;
            }
            if (preg_match('/^\d+\.+/', substr($body, $i), $match) === 1) {
                $i += strlen($match[0]);
                continue;
            }
            if (preg_match('/^\$\d+/', substr($body, $i), $match) === 1) {
                $tokens[] = ['type' => 'nag', 'value' => $match[0]];
                $i += strlen($match[0]);
                continue;
            }
            if (preg_match('/^(1-0|0-1|1\/2-1\/2|\*)\b/', substr($body, $i), $match) === 1) {
                $tokens[] = ['type' => 'result', 'value' => $match[0]];
                $i += strlen($match[0]);
                continue;
            }
            if (preg_match('/^[a-zA-Z0O][a-zA-Z0-9+#=!?\-O]*/', substr($body, $i), $match) === 1) {
                $tokens[] = ['type' => 'san', 'value' => $match[0]];
                $i += strlen($match[0]);
                continue;
            }
            $i++;
        }

        return $tokens;
    }

    private function replayTokens(array $tokens, array $startState): void
    {
        $root = ['state' => $this->cloneState($startState), 'line' => []];
        $stack = [&$root];

        foreach ($tokens as $token) {
            $topIndex = count($stack) - 1;
            if ($token['type'] === 'open') {
                $line = &$stack[$topIndex]['line'];
                $last = !empty($line) ? $line[count($line) - 1] : null;
                $variationState = $last['preState'] ?? $stack[$topIndex]['state'];
                $stack[] = ['state' => $this->cloneState($variationState), 'line' => []];
                continue;
            }
            if ($token['type'] === 'close') {
                if (count($stack) > 1) {
                    array_pop($stack);
                }
                continue;
            }
            if ($token['type'] !== 'san') {
                continue;
            }

            $state = &$stack[$topIndex]['state'];
            $before = $this->cloneState($state);
            if (!$this->applySan($state, $token['value'])) {
                throw new RuntimeException('Illegal move sequence in the submitted PGN.');
            }

            $stack[$topIndex]['line'][] = [
                'preState' => $before,
                'san' => $token['value'],
            ];
        }
    }

    private function validateFen(string $fen): ?string
    {
        $parts = preg_split('/\s+/', trim($fen));
        if (count($parts) !== 6) {
            return 'Invalid FEN in the submitted puzzle.';
        }
        [$board, $turn, $castling, $ep, $halfMove, $fullMove] = $parts;
        $rows = explode('/', $board);
        if (count($rows) !== 8) {
            return 'Invalid FEN in the submitted puzzle.';
        }
        foreach ($rows as $row) {
            $width = 0;
            foreach (str_split($row) as $char) {
                if (ctype_digit($char)) {
                    $width += (int) $char;
                } elseif (preg_match('/^[prnbqkPRNBQK]$/', $char) === 1) {
                    $width += 1;
                } else {
                    return 'Invalid FEN in the submitted puzzle.';
                }
            }
            if ($width !== 8) {
                return 'Invalid FEN in the submitted puzzle.';
            }
        }
        if (!in_array($turn, ['w', 'b'], true)) {
            return 'Invalid FEN in the submitted puzzle.';
        }
        if ($castling !== '-' && preg_match('/^[KQkq]+$/', $castling) !== 1) {
            return 'Invalid FEN in the submitted puzzle.';
        }
        if ($ep !== '-' && preg_match('/^[a-h][36]$/', $ep) !== 1) {
            return 'Invalid FEN in the submitted puzzle.';
        }
        if (!ctype_digit($halfMove) || !ctype_digit($fullMove)) {
            return 'Invalid FEN in the submitted puzzle.';
        }

        return null;
    }

    private function normalizeFen(string $fen): string
    {
        return trim(preg_replace('/\s+/', ' ', $fen) ?? $fen);
    }

    private function extractFinalFen(string $pgn, string $fallbackFen): string
    {
        $headers = $this->parseHeaders($pgn);
        $fen = $headers['fen'] ?? $fallbackFen;
        $tokens = $this->tokenizePgnBody($this->stripHeaders($pgn));
        $state = $this->parseFen($fen);

        try {
            $this->replayTokens($tokens, $state);
        } catch (RuntimeException) {
            return $this->normalizeFen($fallbackFen);
        }

        return $this->stateToFen($state);
    }

    private function parseFen(string $fen): array
    {
        $parts = preg_split('/\s+/', trim($fen));
        [$boardPart, $turn, $castling, $ep, $halfMove, $fullMove] = $parts;

        $board = [];
        foreach (explode('/', $boardPart) as $row) {
            $parsedRow = [];
            foreach (str_split($row) as $char) {
                if (ctype_digit($char)) {
                    for ($i = 0; $i < (int) $char; $i++) {
                        $parsedRow[] = null;
                    }
                } else {
                    $parsedRow[] = $char;
                }
            }
            $board[] = $parsedRow;
        }

        return [
            'board' => $board,
            'turn' => $turn,
            'castling' => $castling,
            'ep' => $ep,
            'halfMove' => (int) $halfMove,
            'fullMove' => (int) $fullMove,
        ];
    }

    private function cloneState(array $state): array
    {
        return [
            'board' => array_map(fn ($row) => array_values($row), $state['board']),
            'turn' => $state['turn'],
            'castling' => $state['castling'],
            'ep' => $state['ep'],
            'halfMove' => $state['halfMove'],
            'fullMove' => $state['fullMove'],
        ];
    }

    private function toSquare(int $rank, int $file): string
    {
        return chr(97 + $file) . ($rank + 1);
    }

    private function squareToCoords(string $square): array
    {
        return [((int) $square[1]) - 1, ord($square[0]) - 97];
    }

    private function getPiece(array $board, int $rank, int $file): ?string
    {
        return $board[7 - $rank][$file] ?? null;
    }

    private function setPiece(array &$board, int $rank, int $file, ?string $piece): void
    {
        $board[7 - $rank][$file] = $piece;
    }

    private function pieceMoves(array $state, int $rank, int $file): array
    {
        $piece = $this->getPiece($state['board'], $rank, $file);
        if (!$piece) {
            return [];
        }

        $isWhite = strtoupper($piece) === $piece;
        $type = strtolower($piece);
        $moves = [];

        if ($type === 'p') {
            $dir = $isWhite ? 1 : -1;
            $startRank = $isWhite ? 1 : 6;
            $oneAhead = $rank + $dir;
            if ($oneAhead >= 0 && $oneAhead <= 7 && !$this->getPiece($state['board'], $oneAhead, $file)) {
                $moves[] = [$oneAhead, $file];
                if ($rank === $startRank && !$this->getPiece($state['board'], $rank + 2 * $dir, $file)) {
                    $moves[] = [$rank + 2 * $dir, $file];
                }
            }
            foreach ([-1, 1] as $df) {
                $tf = $file + $df;
                if ($tf < 0 || $tf > 7 || $oneAhead < 0 || $oneAhead > 7) {
                    continue;
                }
                $target = $this->getPiece($state['board'], $oneAhead, $tf);
                if ($target && ((strtoupper($target) === $target) !== $isWhite)) {
                    $moves[] = [$oneAhead, $tf];
                }
                if ($state['ep'] !== '-') {
                    [$er, $ef] = $this->squareToCoords($state['ep']);
                    if ($er === $oneAhead && $ef === $tf) {
                        $moves[] = [$oneAhead, $tf];
                    }
                }
            }
        } elseif ($type === 'n') {
            foreach ([[1,2],[2,1],[-1,2],[-2,1],[1,-2],[2,-1],[-1,-2],[-2,-1]] as [$dr, $df]) {
                $tr = $rank + $dr;
                $tf = $file + $df;
                if ($tr < 0 || $tr > 7 || $tf < 0 || $tf > 7) {
                    continue;
                }
                $target = $this->getPiece($state['board'], $tr, $tf);
                if (!$target || ((strtoupper($target) === $target) !== $isWhite)) {
                    $moves[] = [$tr, $tf];
                }
            }
        } elseif ($type === 'k') {
            foreach ([[0,1],[0,-1],[1,0],[-1,0],[1,1],[1,-1],[-1,1],[-1,-1]] as [$dr, $df]) {
                $tr = $rank + $dr;
                $tf = $file + $df;
                if ($tr < 0 || $tr > 7 || $tf < 0 || $tf > 7) {
                    continue;
                }
                $target = $this->getPiece($state['board'], $tr, $tf);
                if (!$target || ((strtoupper($target) === $target) !== $isWhite)) {
                    $moves[] = [$tr, $tf];
                }
            }
        } else {
            $dirs = match ($type) {
                'b' => [[1,1],[1,-1],[-1,1],[-1,-1]],
                'r' => [[0,1],[0,-1],[1,0],[-1,0]],
                default => [[0,1],[0,-1],[1,0],[-1,0],[1,1],[1,-1],[-1,1],[-1,-1]],
            };

            foreach ($dirs as [$dr, $df]) {
                $tr = $rank;
                $tf = $file;
                while (true) {
                    $tr += $dr;
                    $tf += $df;
                    if ($tr < 0 || $tr > 7 || $tf < 0 || $tf > 7) {
                        break;
                    }
                    $target = $this->getPiece($state['board'], $tr, $tf);
                    if (!$target) {
                        $moves[] = [$tr, $tf];
                        continue;
                    }
                    if ((strtoupper($target) === $target) !== $isWhite) {
                        $moves[] = [$tr, $tf];
                    }
                    break;
                }
            }
        }

        return $moves;
    }

    private function applySan(array &$state, string $sanRaw): bool
    {
        $san = trim(preg_replace('/[+#!?]/', '', $sanRaw) ?? '');
        if ($san === '') {
            return false;
        }

        $isWhite = $state['turn'] === 'w';

        if (in_array($san, ['O-O', 'O-O-O', '0-0', '0-0-0'], true)) {
            $isKingside = in_array($san, ['O-O', '0-0'], true);
            $rank = $isWhite ? 0 : 7;
            $newKingFile = $isKingside ? 6 : 2;
            $oldRookFile = $isKingside ? 7 : 0;
            $newRookFile = $isKingside ? 5 : 3;
            $this->setPiece($state['board'], $rank, 4, null);
            $this->setPiece($state['board'], $rank, $newKingFile, $isWhite ? 'K' : 'k');
            $this->setPiece($state['board'], $rank, $oldRookFile, null);
            $this->setPiece($state['board'], $rank, $newRookFile, $isWhite ? 'R' : 'r');
            $state['castling'] = preg_replace($isWhite ? '/[KQ]/' : '/[kq]/', '', $state['castling']) ?: '-';
            $state['ep'] = '-';
            $state['turn'] = $isWhite ? 'b' : 'w';
            if (!$isWhite) {
                $state['fullMove']++;
            }
            $state['halfMove']++;
            return true;
        }

        $promotion = null;
        if (preg_match('/=([QRBN])/i', $san, $promotionMatch) === 1) {
            $promotion = strtoupper($promotionMatch[1]);
            $san = preg_replace('/=[QRBNqrbn]/', '', $san) ?? $san;
        }

        if (preg_match('/^([KQRBN])?([a-h])?([1-8])?x?([a-h][1-8])$/', $san, $match) !== 1) {
            return false;
        }

        $pieceLetter = $match[1] ?: 'P';
        $fromFile = $match[2] ?? null;
        $fromRank = $match[3] ?? null;
        $targetSq = $match[4];
        [$tr, $tf] = $this->squareToCoords($targetSq);
        $sourcePiece = $isWhite ? $pieceLetter : strtolower($pieceLetter);

        $source = null;
        for ($r = 0; $r < 8 && !$source; $r++) {
            for ($f = 0; $f < 8 && !$source; $f++) {
                if ($this->getPiece($state['board'], $r, $f) !== $sourcePiece) {
                    continue;
                }
                if ($fromFile && $f !== (ord($fromFile) - 97)) {
                    continue;
                }
                if ($fromRank && $r !== (((int) $fromRank) - 1)) {
                    continue;
                }
                foreach ($this->pieceMoves($state, $r, $f) as [$moveRank, $moveFile]) {
                    if ($moveRank === $tr && $moveFile === $tf) {
                        $source = [$r, $f];
                        break;
                    }
                }
            }
        }

        if (!$source) {
            return false;
        }

        [$sr, $sf] = $source;
        $movingPiece = $this->getPiece($state['board'], $sr, $sf);
        $wasCapture = (bool) $this->getPiece($state['board'], $tr, $tf);

        if (strtolower($movingPiece) === 'p' && $state['ep'] !== '-' && $targetSq === $state['ep']) {
            $epDir = $isWhite ? -1 : 1;
            $this->setPiece($state['board'], $tr + $epDir, $tf, null);
        }

        $this->setPiece($state['board'], $sr, $sf, null);
        $this->setPiece($state['board'], $tr, $tf, $promotion ? ($isWhite ? $promotion : strtolower($promotion)) : $movingPiece);

        if (strtolower($movingPiece) === 'p' && abs($tr - $sr) === 2) {
            $state['ep'] = $this->toSquare((int) (($sr + $tr) / 2), $sf);
        } else {
            $state['ep'] = '-';
        }

        $castling = $state['castling'] ?: '-';
        if ($movingPiece === 'K') {
            $castling = str_replace(['K', 'Q'], '', $castling);
        }
        if ($movingPiece === 'k') {
            $castling = str_replace(['k', 'q'], '', $castling);
        }
        if ($movingPiece === 'R') {
            if ($sr === 0 && $sf === 0) {
                $castling = str_replace('Q', '', $castling);
            }
            if ($sr === 0 && $sf === 7) {
                $castling = str_replace('K', '', $castling);
            }
        }
        if ($movingPiece === 'r') {
            if ($sr === 7 && $sf === 0) {
                $castling = str_replace('q', '', $castling);
            }
            if ($sr === 7 && $sf === 7) {
                $castling = str_replace('k', '', $castling);
            }
        }
        if ($tr === 0 && $tf === 0) {
            $castling = str_replace('Q', '', $castling);
        }
        if ($tr === 0 && $tf === 7) {
            $castling = str_replace('K', '', $castling);
        }
        if ($tr === 7 && $tf === 0) {
            $castling = str_replace('q', '', $castling);
        }
        if ($tr === 7 && $tf === 7) {
            $castling = str_replace('k', '', $castling);
        }
        $state['castling'] = $castling === '' ? '-' : $castling;

        $state['turn'] = $isWhite ? 'b' : 'w';
        if (!$isWhite) {
            $state['fullMove']++;
        }
        $state['halfMove'] = (strtolower($movingPiece) === 'p' || $wasCapture) ? 0 : $state['halfMove'] + 1;

        return true;
    }

    private function stateToFen(array $state): string
    {
        $rows = [];
        foreach ($state['board'] as $row) {
            $empty = 0;
            $fenRow = '';
            foreach ($row as $piece) {
                if ($piece === null) {
                    $empty++;
                    continue;
                }
                if ($empty > 0) {
                    $fenRow .= (string) $empty;
                    $empty = 0;
                }
                $fenRow .= $piece;
            }
            if ($empty > 0) {
                $fenRow .= (string) $empty;
            }
            $rows[] = $fenRow;
        }

        return implode('/', $rows)
            . ' ' . ($state['turn'] ?: 'w')
            . ' ' . (($state['castling'] ?? '-') === '' ? '-' : ($state['castling'] ?? '-'))
            . ' ' . ($state['ep'] ?: '-')
            . ' ' . (int) ($state['halfMove'] ?? 0)
            . ' ' . max(1, (int) ($state['fullMove'] ?? 1));
    }
}

class PgnValidationException extends RuntimeException
{
    public function __construct(public array $errors)
    {
        parent::__construct('The submitted puzzle PGN is invalid.');
    }
}

class PgnPuzzleConflictException extends RuntimeException
{
}

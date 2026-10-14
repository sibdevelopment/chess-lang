<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyseController extends Controller
{
    /**
     * List the student's saved analyses + show new-upload form.
     */
    public function index()
    {
        $analyses = StudentAnalysis::where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->get();

        return view('student.analyse.index', compact('analyses'));
    }

    /**
     * Persist a new analysis from a pasted PGN or uploaded .pgn file.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:200',
            'pgn'  => 'nullable|string',
            'file' => 'nullable|file|max:1024',
        ]);

        $pgn = $data['pgn'] ?? null;
        if ($request->hasFile('file')) {
            $pgn = file_get_contents($request->file('file')->getRealPath());
        }

        $pgn = trim((string) $pgn);
        if ($pgn === '') {
            return back()->with('error', 'Paste a PGN or upload a .pgn file.');
        }

        $name = $data['name']
            ?? $this->guessName($pgn)
            ?? ('Study ' . now()->format('M j, H:i'));

        $study = StudentAnalysis::create([
            'user_id'    => auth()->id(),
            'name'       => $name,
            'pgn'        => $pgn,
            'reviews'    => [],
            'commentary' => [],
            'opened_at'  => now(),
        ]);

        return redirect()->route('student.analyse.show', $study)
            ->with('success', 'Study saved. Review when you are ready.');
    }

    /**
     * Open a saved analysis.
     */
    public function show(StudentAnalysis $analysis)
    {
        $this->authorizeOwner($analysis);
        $analysis->forceFill(['opened_at' => now()])->saveQuietly();

        return view('student.analyse.show', [
            'analysis' => $analysis,
        ]);
    }

    /**
     * Persist engine-review JSON for one or many moves.
     * Body: { reviews: { "<gameIdx>-<moveIdx>": { class, drop, evalBefore, evalAfter, bestSan, pvSan: [..] } } }
     */
    public function updateReview(Request $request, StudentAnalysis $analysis)
    {
        $this->authorizeOwner($analysis);
        $patch = $request->input('reviews', []);
        if (!is_array($patch)) {
            return response()->json(['error' => 'Invalid payload'], 422);
        }

        $current = $analysis->reviews ?? [];
        foreach ($patch as $key => $val) {
            $current[$key] = $val;
        }
        $analysis->reviews = $current;
        $analysis->save();

        return response()->json(['ok' => true, 'count' => count($current)]);
    }

    /**
     * Generate (or fetch cached) AI commentary for one move and store it.
     */
    public function commentary(Request $request, StudentAnalysis $analysis)
    {
        $this->authorizeOwner($analysis);
        $payload = $request->validate([
            'key'            => 'required|string|max:32',
            'san'            => 'required|string|max:16',
            'fenBefore'      => 'required|string|max:120',
            'fenAfter'       => 'required|string|max:120',
            'evalBefore'     => 'nullable|numeric',
            'evalAfter'      => 'nullable|numeric',
            'bestSan'        => 'nullable|string|max:16',
            'pv'             => 'nullable|array',
            'classification' => 'nullable|string|max:20',
            'force'          => 'nullable|boolean',
        ]);

        $cache = $analysis->commentary ?? [];
        $force = (bool) ($payload['force'] ?? false);
        if (!$force && isset($cache[$payload['key']])) {
            $entry = $cache[$payload['key']];
            return response()->json([
                'text'   => is_array($entry) ? ($entry['text'] ?? '') : $entry,
                'source' => is_array($entry) ? ($entry['source'] ?? 'cache') : 'cache',
                'cached' => true,
            ]);
        }

        $apiKey = config('services.openai.api_key');
        $model  = config('services.openai.model', 'gpt-4o-mini');

        $text   = null;
        $source = 'heuristic';

        if ($apiKey) {
            try {
                $sys = "You are a strong chess coach (FIDE master level) explaining one move to a club-level student. " .
                       "Given the position before the move, the move played, the engine evaluations, and the engine's preferred line, " .
                       "write 2-4 sentences in plain language that cover: (1) was the move good or bad and why, " .
                       "(2) the concrete plan or threat or what it missed, " .
                       "(3) what the engine prefers and the idea behind it, including a short variation. " .
                       "Avoid markdown, avoid lists, avoid emojis. Write in flowing prose, like a coach speaking.";

                $user = json_encode([
                    'classification' => $payload['classification'] ?? null,
                    'fen_before'     => $payload['fenBefore'],
                    'fen_after'      => $payload['fenAfter'],
                    'move_played'    => $payload['san'],
                    'engine_eval_before_white_pawns' => $payload['evalBefore'] ?? null,
                    'engine_eval_after_white_pawns'  => $payload['evalAfter']  ?? null,
                    'engine_best_move_san' => $payload['bestSan'] ?? null,
                    'engine_principal_variation_san' => $payload['pv'] ?? [],
                ], JSON_UNESCAPED_UNICODE);

                $resp = Http::withToken($apiKey)
                    ->timeout(20)
                    ->retry(1, 400)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'    => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $sys],
                            ['role' => 'user',   'content' => $user],
                        ],
                        'temperature' => 0.55,
                        'max_tokens'  => 280,
                    ]);

                if ($resp->ok()) {
                    $text = trim($resp->json('choices.0.message.content') ?? '');
                    if ($text !== '') $source = 'openai';
                }
            } catch (\Throwable $e) {
                // Fall through to heuristic.
            }
        }

        if (!$text) {
            $text = $this->heuristic($payload);
            $source = 'heuristic';
        }

        $cache[$payload['key']] = ['text' => $text, 'source' => $source, 'at' => now()->toIso8601String()];
        $analysis->commentary = $cache;
        $analysis->save();

        return response()->json([
            'text'   => $text,
            'source' => $source,
            'cached' => false,
        ]);
    }

    /**
     * Persist the full study tree (variations, subvariations, NAGs, comments, AI text).
     * Body: { tree: { games: [ { name, startFen, root: <node> } ] } }
     * Each node: { san, uci, fen, moveNumber, side, nag?, comment?, ai?, review?, children: [...] }
     * Replaces the tree wholesale on user "Save" click.
     */
    public function saveTree(Request $request, StudentAnalysis $analysis)
    {
        $this->authorizeOwner($analysis);
        $tree = $request->input('tree');
        if (!is_array($tree)) {
            return response()->json(['error' => 'tree must be an object'], 422);
        }

        // Guard: if migration 0008 hasn't been applied, the `tree` column is missing.
        // Surface a clear, actionable error instead of a raw SQL exception.
        if (!\Illuminate\Support\Facades\Schema::hasColumn('student_analyses', 'tree')) {
            return response()->json([
                'error' => 'The student_analyses.tree column does not exist yet. '
                        . 'Run database/sql/missing_tables_fallback.sql in phpMyAdmin (or `php artisan migrate`) and try saving again.',
            ], 500);
        }

        try {
            $analysis->tree = $tree;
            $analysis->save();
        } catch (\Throwable $e) {
            Log::warning('Student analysis tree save failed.', [
                'user_id' => auth()->id(),
                'action' => 'analysis.save_tree',
                'model_type' => StudentAnalysis::class,
                'model_id' => $analysis->id,
                'route' => $request->route()?->getName(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'error' => 'Could not save the study right now. Please try again.',
            ], 500);
        }

        return response()->json([
            'ok'        => true,
            'savedAt'   => now()->toIso8601String(),
            'gameCount' => is_array($tree['games'] ?? null) ? count($tree['games']) : 0,
        ]);
    }

    /**
     * Delete a saved analysis.
     */
    public function destroy(StudentAnalysis $analysis)
    {
        $this->authorizeOwner($analysis);
        $analysis->delete();

        return redirect()->route('student.analyse')
            ->with('success', 'Study deleted.');
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    private function authorizeOwner(StudentAnalysis $a): void
    {
        abort_unless($a->user_id === auth()->id(), 404);
    }

    private function guessName(string $pgn): ?string
    {
        if (preg_match('/\[Event\s+"([^"]+)"\]/', $pgn, $ev) && trim($ev[1]) !== '') {
            return mb_substr(trim($ev[1]), 0, 200);
        }
        if (preg_match('/\[White\s+"([^"]+)"\]/', $pgn, $w)
            && preg_match('/\[Black\s+"([^"]+)"\]/', $pgn, $b)) {
            return mb_substr(trim($w[1]) . ' vs ' . trim($b[1]), 0, 200);
        }
        return null;
    }

    /**
     * Fallback commentary when no API key is configured (or call fails).
     */
    private function heuristic(array $p): string
    {
        $san  = $p['san'];
        $best = $p['bestSan'] ?? null;
        $cls  = $p['classification'] ?? '';
        $drop = isset($p['evalBefore'], $p['evalAfter'])
            ? round((float) $p['evalBefore'] - (float) $p['evalAfter'], 2)
            : null;
        $pvLine = is_array($p['pv'] ?? null)
            ? implode(' ', array_slice($p['pv'], 0, 4))
            : '';

        $features = [
            'capture' => str_contains($san, 'x'),
            'check'   => str_ends_with($san, '+'),
            'mate'    => str_ends_with($san, '#'),
            'castle'  => str_starts_with($san, 'O-O'),
        ];

        if ($cls === 'best') {
            if ($features['mate'])    return "Mate — game over.";
            if ($features['castle'])  return "Best move. Castling here is correct: the king finds safety and the rook joins the action. The engine continues with {$pvLine}.";
            if ($features['capture']) return "Best move. The capture wins material and the engine wants to follow up with {$pvLine}, keeping the initiative.";
            return "Best move — matches the engine's first choice. The plan is to continue with {$pvLine}.";
        }
        if ($cls === 'good') {
            return "A solid move. It barely changes the evaluation; the engine's top choice was " . ($best ?? '—') . ", but what you played keeps the position fine.";
        }
        if ($cls === 'inaccuracy') {
            return "Slightly inaccurate — gives away about " . number_format((float) $drop, 2) . " pawns. " .
                   ($best ? "{$best} was sharper, planning {$pvLine}." : "");
        }
        if ($cls === 'mistake') {
            return "A mistake worth around " . number_format((float) $drop, 2) . " pawns. " .
                   ($best ? "Better was {$best}, after which the engine plays {$pvLine}." : "");
        }
        if ($cls === 'blunder') {
            return "Blunder — drops " . number_format((float) $drop, 2) . " pawns. " .
                   ($best ? "{$best} was the only good move; the engine's line ran {$pvLine}. " : "") .
                   "Slow down here next time and check what the opponent is threatening.";
        }
        return "Engine view: " . ($best ? "the top move was {$best}" : "no clear preference") .
               ($pvLine ? ", continuing {$pvLine}." : ".");
    }
}

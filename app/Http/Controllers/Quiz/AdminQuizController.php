<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD over the quiz bank. Used by both admin and staff (one shared controller,
 * routed under each prefix). Coaches do NOT have access — they only run sessions.
 */
class AdminQuizController extends Controller
{
    public function index(Request $request)
    {
        $weekFilter     = $request->query('week');
        $categoryFilter = $request->query('category');

        // Stage 1 — no week selected: show week tiles with counts.
        $weekTiles = Quiz::query()
            ->selectRaw('week, COUNT(*) as cnt')
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        // Stage 2 — week selected: show categories within that week.
        $categoryTiles = collect();
        if ($weekFilter !== null && $weekFilter !== '') {
            $categoryTiles = Quiz::query()
                ->selectRaw('category, COUNT(*) as cnt')
                ->where('week', $weekFilter)
                ->groupBy('category')
                ->orderBy('category')
                ->get();
        }

        // Stage 3 — week + category: show actual quiz cards.
        $quizzes = collect();
        if ($weekFilter !== null && $weekFilter !== '' && $categoryFilter) {
            $quizzes = Quiz::with('creator')
                ->where('week', $weekFilter)
                ->where('category', $categoryFilter)
                ->orderByDesc('id')
                ->get();
        }

        $totalQuizzes = Quiz::count();

        return view('quizzes.index', [
            'weekTiles'         => $weekTiles,
            'categoryTiles'     => $categoryTiles,
            'quizzes'           => $quizzes,
            'weekFilter'        => $weekFilter,
            'categoryFilter'    => $categoryFilter,
            'totalQuizzes'      => $totalQuizzes,
            'defaultCategories' => Quiz::DEFAULT_CATEGORIES,
        ]);
    }

    public function create()
    {
        return view('quizzes.create', [
            'quiz' => null,
            'defaultCategories' => Quiz::DEFAULT_CATEGORIES,
        ]);
    }

    public function importForm()
    {
        return view('quizzes.import', [
            'defaultCategories' => Quiz::DEFAULT_CATEGORIES,
        ]);
    }

    public function downloadTemplate()
    {
        $headers = [
            'week', 'category', 'age_min', 'age_max', 'question',
            'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer',
        ];
        $example = [
            1, 'Chess', 5, 8, 'How many squares are on a chessboard?',
            '32', '48', '64', '100', 'C',
        ];

        return response()->streamDownload(function () use ($headers, $example) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fputcsv($output, $example);
            fclose($output);
        }, 'archer-quiz-upload-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'quiz_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($request->file('quiz_file')->getRealPath(), 'r');
        if (! $handle) {
            throw ValidationException::withMessages(['quiz_file' => 'The CSV file could not be opened.']);
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            throw ValidationException::withMessages(['quiz_file' => 'The CSV file is empty.']);
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header);
        $requiredHeaders = [
            'week', 'category', 'age_min', 'age_max', 'question',
            'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer',
        ];
        $missing = array_values(array_diff($requiredHeaders, $header));
        if ($missing) {
            fclose($handle);
            throw ValidationException::withMessages([
                'quiz_file' => 'Missing CSV columns: '.implode(', ', $missing).'. Please use the provided template.',
            ]);
        }

        $rows = [];
        $errors = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= 1000) {
                $errors[] = 'Maximum 1,000 questions can be uploaded at once.';
                break;
            }

            $values = array_pad($values, count($header), '');
            $row = array_combine($header, array_slice($values, 0, count($header)));
            $parsed = $this->parseCsvRow($row, $line, $errors);
            if ($parsed) {
                $rows[] = $parsed;
            }
        }
        fclose($handle);

        if ($errors) {
            throw ValidationException::withMessages([
                'quiz_file' => array_slice($errors, 0, 20),
            ]);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['quiz_file' => 'No quiz questions were found in the CSV file.']);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                Quiz::create($row + ['created_by' => Auth::id()]);
            }
        });

        $weeks = collect($rows)->pluck('week')->unique()->sort()->implode(', ');

        return redirect()->route(self::roleHome())->with(
            'success',
            count($rows).' quiz questions uploaded successfully for week'.(str_contains($weeks, ',') ? 's ' : ' ').$weeks.'.'
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateInput($request);
        $payload = $this->buildPayloadFromRequest($request, $data, null);
        $payload['created_by'] = Auth::id();

        Quiz::create($payload);

        return redirect()->route(self::roleHome())->with('success', 'Quiz added to the bank.');
    }

    public function edit(Quiz $quiz)
    {
        return view('quizzes.create', [
            'quiz' => $quiz,
            'defaultCategories' => Quiz::DEFAULT_CATEGORIES,
        ]);
    }

    public function update(Request $request, Quiz $quiz)
    {
        $data = $this->validateInput($request, $quiz);
        $payload = $this->buildPayloadFromRequest($request, $data, $quiz);
        $quiz->update($payload);

        return redirect()->route(self::roleHome())->with('success', 'Quiz updated.');
    }

    public function destroy(Quiz $quiz)
    {
        // Best-effort delete uploaded images.
        if ($quiz->image_path) {
            @Storage::disk('public')->delete($quiz->image_path);
        }
        foreach (($quiz->options ?? []) as $opt) {
            if (($opt['kind'] ?? null) === 'image' && !empty($opt['value'])) {
                @Storage::disk('public')->delete($opt['value']);
            }
        }

        try {
            $quiz->delete();
        } catch (QueryException $e) {
            Log::warning('Quiz delete blocked.', [
                'user_id' => Auth::id(),
                'action' => 'quiz.delete',
                'model_type' => Quiz::class,
                'model_id' => $quiz->id,
                'route' => request()->route()?->getName(),
                'exception' => $e::class,
            ]);

            return back()->with('error', 'This quiz cannot be deleted because it is already used by a session or historical result.');
        }

        return back()->with('success', 'Quiz deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    protected function validateInput(Request $request, ?Quiz $existing = null): array
    {
        return $request->validate([
            'week'                => 'required|integer|min:1|max:52',
            'category'            => ['required', Rule::in(Quiz::DEFAULT_CATEGORIES)],
            'age_min'             => 'required|integer|min:5|max:8',
            'age_max'             => 'required|integer|min:5|max:8|gte:age_min',
            'type'                => 'required|in:text,picture',
            'question_text'       => 'nullable|string|max:1000',
            'question_image'      => 'nullable|image|max:4096',     // 4MB
            'correct_index'       => 'required|integer|min:0|max:3',

            // Per-option fields (4 of them)
            'option_kind'         => 'required|array|size:4',
            'option_kind.*'       => 'required|in:text,image',
            'option_text'         => 'array',
            'option_text.*'       => 'nullable|string|max:300',
            'option_image'        => 'array',
            'option_image.*'      => 'nullable|image|max:4096',
        ]);
    }

    /**
     * Build the model payload from the validated request, handling file uploads.
     */
    protected function buildPayloadFromRequest(Request $request, array $data, ?Quiz $existing): array
    {
        $payload = [
            'week'               => (int) $data['week'],
            'category'           => trim($data['category']),
            'age_min'            => (int) $data['age_min'],
            'age_max'            => (int) $data['age_max'],
            'type'               => $data['type'],
            'question_text'      => $data['type'] === 'text' ? ($data['question_text'] ?? '') : null,
            'time_limit_seconds' => 120,
            'correct_index'      => (int) $data['correct_index'],
        ];

        // Question image (only when type=picture).
        if ($data['type'] === 'picture' && $request->hasFile('question_image')) {
            // Replace existing.
            if ($existing && $existing->image_path) {
                @Storage::disk('public')->delete($existing->image_path);
            }
            $payload['image_path'] = $request->file('question_image')->store('quizzes/questions', 'public');
        } elseif ($data['type'] === 'picture' && $existing) {
            $payload['image_path'] = $existing->image_path; // keep
        } elseif ($data['type'] === 'text') {
            $payload['image_path'] = null;
            // Drop old image if switching from picture → text
            if ($existing && $existing->image_path) {
                @Storage::disk('public')->delete($existing->image_path);
            }
        }

        // Build the options array.
        $options = [];
        $existingOptions = $existing ? $existing->options : [];
        for ($i = 0; $i < 4; $i++) {
            $kind = $data['option_kind'][$i];
            if ($kind === 'text') {
                $value = trim((string) ($data['option_text'][$i] ?? ''));
                if ($value === '') {
                    throw ValidationException::withMessages([
                        "option_text.$i" => 'This text option cannot be empty.',
                    ]);
                }
                // If we replaced an old image option, delete the file.
                $prev = $existingOptions[$i] ?? null;
                if ($prev && ($prev['kind'] ?? null) === 'image' && !empty($prev['value'])) {
                    @Storage::disk('public')->delete($prev['value']);
                }
                $options[] = ['kind' => 'text', 'value' => $value];
            } else {
                // image
                $upload = $request->file("option_image.$i");
                if ($upload) {
                    // Replace prior file if any.
                    $prev = $existingOptions[$i] ?? null;
                    if ($prev && ($prev['kind'] ?? null) === 'image' && !empty($prev['value'])) {
                        @Storage::disk('public')->delete($prev['value']);
                    }
                    $path = $upload->store('quizzes/options', 'public');
                    $options[] = ['kind' => 'image', 'value' => $path];
                } else {
                    // No new upload — keep the existing image (during update only).
                    $prev = $existingOptions[$i] ?? null;
                    if ($prev && ($prev['kind'] ?? null) === 'image' && !empty($prev['value'])) {
                        $options[] = $prev;
                    } else {
                        throw ValidationException::withMessages([
                            "option_image.$i" => 'Upload an image for this option.',
                        ]);
                    }
                }
            }
        }
        $payload['options'] = $options;

        return $payload;
    }

    protected function parseCsvRow(array $row, int $line, array &$errors): ?array
    {
        $row = array_map(fn ($value) => trim((string) $value), $row);
        $categories = collect(Quiz::DEFAULT_CATEGORIES)->keyBy(fn ($category) => strtolower($category));
        $category = $categories->get(strtolower($row['category'] ?? ''));
        $week = filter_var($row['week'] ?? null, FILTER_VALIDATE_INT);
        $ageMin = filter_var($row['age_min'] ?? null, FILTER_VALIDATE_INT);
        $ageMax = filter_var($row['age_max'] ?? null, FILTER_VALIDATE_INT);
        $question = $row['question'] ?? '';
        $options = [
            $row['option_a'] ?? '', $row['option_b'] ?? '',
            $row['option_c'] ?? '', $row['option_d'] ?? '',
        ];
        $answer = strtoupper($row['correct_answer'] ?? '');
        $answerMap = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, '1' => 0, '2' => 1, '3' => 2, '4' => 3];

        $lineErrors = [];
        if ($week === false || $week < 1 || $week > 52) $lineErrors[] = 'week must be 1–52';
        if (! $category) $lineErrors[] = 'category must be '.implode(', ', Quiz::DEFAULT_CATEGORIES);
        if ($ageMin === false || $ageMin < 5 || $ageMin > 8) $lineErrors[] = 'age_min must be 5–8';
        if ($ageMax === false || $ageMax < 5 || $ageMax > 8 || ($ageMin !== false && $ageMax < $ageMin)) $lineErrors[] = 'age_max must be 5–8 and not below age_min';
        if ($question === '' || mb_strlen($question) > 1000) $lineErrors[] = 'question is required (maximum 1,000 characters)';
        foreach ($options as $index => $option) {
            if ($option === '' || mb_strlen($option) > 300) $lineErrors[] = 'option '.chr(65 + $index).' is required (maximum 300 characters)';
        }
        if (! array_key_exists($answer, $answerMap)) $lineErrors[] = 'correct_answer must be A, B, C or D';

        if ($lineErrors) {
            $errors[] = 'Row '.$line.': '.implode('; ', $lineErrors).'.';
            return null;
        }

        return [
            'week' => $week,
            'category' => $category,
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'type' => 'text',
            'question_text' => $question,
            'image_path' => null,
            'options' => array_map(fn ($option) => ['kind' => 'text', 'value' => $option], $options),
            'correct_index' => $answerMap[$answer],
            'time_limit_seconds' => 120,
        ];
    }

    // ── Role-aware route names ───────────────────────────────────────────
    public static function roleHome(): string
    {
        $u = Auth::user();
        if ($u->isAdmin()) return 'admin.quizzes.index';
        if ($u->isStaff()) return 'staff.quizzes.index';
        return 'admin.quizzes.index';
    }
}

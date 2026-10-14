<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Coach's read-only PGN database.
 * Every active coach sees EVERY uploaded PGN — sorted by level then sequence.
 */
class DatabaseController extends Controller
{
    public function index(Request $request)
    {
        $level = $request->get('level', 'all');

        $query = Chapter::with('uploader')->orderBy('sequence_number');
        if ($level !== 'all') $query->where('level', $level);

        $chapters = $query->get()->groupBy('level');

        $totals = collect(Chapter::LEVELS)
            ->mapWithKeys(fn (string $chapterLevel) => [$chapterLevel => Chapter::where('level', $chapterLevel)->count()])
            ->all();

        return view('coach.database.index', compact('chapters', 'totals', 'level'));
    }

    public function viewChapter(Chapter $chapter)
    {
        abort_unless(Auth::user()->isCoach(), 403);
        $chapter->load('puzzleAnswers');
        return view('coach.database.chapter', compact('chapter'));
    }
}

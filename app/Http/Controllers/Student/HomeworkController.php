<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Services\ArcherJourney\ArcherLmsEventBridge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeworkController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::user();
        $groupIds = $student->groups->pluck('id');
        $status = $request->get('status', 'all');

        $homework = Homework::whereIn('group_id', $groupIds)
            ->with([
                'chapter',
                'group',
                'submissions' => fn ($q) => $q->where('user_id', $student->id),
            ])
            ->when($status !== 'all', function ($q) use ($status, $student) {
                $q->whereHas('submissions', fn ($sq) => $sq->where('user_id', $student->id)->where('status', $status));
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('student.homework.index', compact('homework', 'status'));
    }

    public function solve(Homework $homework)
    {
        $student = Auth::user();
        $groupIds = $student->groups->pluck('id');
        abort_unless($groupIds->contains($homework->group_id), 403);

        $submission = HomeworkSubmission::firstOrCreate(
            ['homework_id' => $homework->id, 'user_id' => $student->id],
            ['status' => 'pending']
        );

        if ($submission->status === 'pending') {
            $submission->update(['status' => 'in_progress']);
        }

        $homework->load(['chapter', 'group']);

        return view('student.homework.solve', compact('homework', 'submission'));
    }

    public function submit(Request $request, Homework $homework)
    {
        $student = Auth::user();
        abort_unless($student->groups->pluck('id')->contains($homework->group_id), 403);

        $data = $request->validate([
            'moves_played' => 'nullable|string',
            'result' => 'nullable|string|max:50',
        ]);

        $submission = HomeworkSubmission::updateOrCreate(
            ['homework_id' => $homework->id, 'user_id' => $student->id],
            [
                'status' => 'done',
                'moves_played' => $data['moves_played'] ?? null,
                'result' => $data['result'] ?? null,
                'submitted_at' => now(),
            ]
        );

        app(ArcherLmsEventBridge::class)->homeworkCompleted($submission);

        return redirect()
            ->route('student.homework')
            ->with('success', 'Homework submitted!');
    }
}

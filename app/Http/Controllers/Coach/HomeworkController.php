<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeworkController extends Controller
{
    public function index(Request $request)
    {
        $coach = Auth::user();
        $groupId = $request->get('group_id');
        $studentId = $request->get('student_id');
        $status = $request->get('status', 'all');

        $homework = Homework::where('assigned_by', $coach->id)
            ->with([
                'chapter',
                'group',
                'submissions' => fn ($submissionQuery) => $submissionQuery
                    ->when($studentId, fn ($filteredQuery) => $filteredQuery->where('user_id', $studentId)),
                'submissions.student',
            ])
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->when($studentId, fn ($q) => $q->whereHas(
                'submissions',
                fn ($submissionQuery) => $submissionQuery->where('user_id', $studentId)
            ))
            ->when(
                in_array($status, ['pending', 'in_progress', 'done'], true),
                fn ($q) => $q->whereHas(
                    'submissions',
                    fn ($submissionQuery) => $submissionQuery
                        ->where('status', $status)
                        ->when($studentId, fn ($filteredQuery) => $filteredQuery->where('user_id', $studentId))
                )
            )
            ->when(
                $status === 'awaiting_review',
                fn ($q) => $q->whereHas(
                    'submissions',
                    fn ($submissionQuery) => $submissionQuery
                        ->where('status', 'done')
                        ->whereNull('marked_correct')
                        ->when($studentId, fn ($filteredQuery) => $filteredQuery->where('user_id', $studentId))
                )
            )
            ->when(
                $status === 'reviewed',
                fn ($q) => $q->whereHas(
                    'submissions',
                    fn ($submissionQuery) => $submissionQuery
                        ->where('status', 'done')
                        ->whereNotNull('marked_correct')
                        ->when($studentId, fn ($filteredQuery) => $filteredQuery->where('user_id', $studentId))
                )
            )
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $groups = Group::where('coach_id', $coach->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $students = User::students()
            ->active()
            ->whereHas('groups', fn ($groupQuery) => $groupQuery->where('coach_id', $coach->id))
            ->whereHas('homeworkSubmissions.homework', fn ($homeworkQuery) => $homeworkQuery->where('assigned_by', $coach->id))
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.username']);

        return view('coach.homework.index', compact(
            'homework',
            'groups',
            'students',
            'groupId',
            'studentId',
            'status'
        ));
    }

    public function show(Request $request, Homework $homework)
    {
        abort_unless($homework->assigned_by === Auth::id(), 403);

        $homework->load(['chapter', 'group.students', 'submissions.student']);
        $studentOptions = $homework->group?->students
            ?->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values() ?? collect();
        $studentId = $request->input('student_id');
        $selectedStudentId = $studentOptions->contains('id', (int) $studentId)
            ? (int) $studentId
            : null;
        $visibleStudents = $selectedStudentId
            ? $studentOptions->where('id', $selectedStudentId)->values()
            : $studentOptions;

        if ($selectedStudentId) {
            $homework->setRelation(
                'submissions',
                $homework->submissions->where('user_id', $selectedStudentId)->values()
            );
        }

        $stats = $homework->stats;

        return view('coach.homework.show', compact(
            'homework',
            'stats',
            'studentOptions',
            'visibleStudents',
            'selectedStudentId'
        ));
    }

    public function markSubmission(Request $request, HomeworkSubmission $submission)
    {
        $submission->loadMissing('homework');
        abort_unless($submission->homework && $submission->homework->assigned_by === Auth::id(), 403);

        $data = $request->validate([
            'marked_correct' => 'nullable|boolean',
            'coach_note' => 'nullable|string|max:500',
        ]);

        $updates = ['coach_note' => $data['coach_note'] ?? null];

        if (array_key_exists('marked_correct', $data) && $data['marked_correct'] !== null) {
            $updates['marked_correct'] = (bool) $data['marked_correct'];
            $updates['status'] = 'done';
        }

        $submission->update($updates);

        return back()->with('success', isset($updates['marked_correct']) ? 'Homework reviewed.' : 'Feedback saved.');
    }
}

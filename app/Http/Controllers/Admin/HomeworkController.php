<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Homework;
use App\Models\User;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    public function index(Request $request)
    {
        $groupId = $request->input('group_id');
        $coachId = $request->input('coach_id');
        $studentId = $request->input('student_id');

        $query = Homework::with([
            'chapter',
            'group',
            'assigner',
            'submissions' => fn ($submissionQuery) => $submissionQuery
                ->when($studentId, fn ($filteredQuery) => $filteredQuery->where('user_id', $studentId)),
        ]);
        if ($groupId) {
            $query->where('group_id', $groupId);
        }
        if ($coachId) {
            $query->where('assigned_by', $coachId);
        }
        if ($studentId) {
            $query->whereHas('submissions', fn ($submissionQuery) => $submissionQuery->where('user_id', $studentId));
        }

        $homework = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $groups = Group::orderBy('name')->get();
        $coaches = User::coaches()->orderBy('name')->get(['id', 'name']);
        $students = User::students()
            ->active()
            ->whereHas('homeworkSubmissions')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        return view('admin.homework.index', compact(
            'homework',
            'groups',
            'coaches',
            'students',
            'groupId',
            'coachId',
            'studentId'
        ));
    }

    public function show(Request $request, Homework $homework)
    {
        $homework->load([
            'chapter',
            'group.students',
            'assigner',
            'submissions.student',
        ]);

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

        return view('admin.homework.show', compact(
            'homework',
            'stats',
            'studentOptions',
            'visibleStudents',
            'selectedStudentId'
        ));
    }
}

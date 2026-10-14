<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ClassParticipant;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LiveClass;
use App\Models\Puzzle;
use App\Models\PuzzleSet;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\QuizSessionAttendee;
use App\Models\QuizSessionQuiz;
use App\Models\QuizSessionResponse;
use App\Models\StudentPerformanceAssessment;
use App\Models\StudentPuzzleAttempt;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StudentPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 11, 12, 0, 0, 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_reports_landing_and_paginated_student_list_render_successfully(): void
    {
        $admin = $this->makeUser('admin');

        for ($studentNumber = 1; $studentNumber <= 51; $studentNumber++) {
            $this->makeUser('student', [
                'name' => 'Report Student '.$studentNumber,
                'username' => 'report-student-'.$studentNumber,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('A unified view of learning activity and academy operations.')
            ->assertSee('Report audience')
            ->assertSee('Saved Reports')
            ->assertSee('Need Assessment')
            ->assertSee('No saved reports found')
            ->assertDontSee('Active Students')
            ->assertDontSee('Back to Dashboard')
            ->assertDontSee('Open Student Reports')
            ->assertDontSee('Coach Reports');

        $this->actingAs($admin)
            ->get(route('admin.reports', ['tab' => 'student', 'status' => 'need_assessment', 'search' => 'Report Student 51']))
            ->assertOk()
            ->assertSee('Report Student 51')
            ->assertDontSee('Report Student 1</div>', false);

        $this->actingAs($admin)
            ->get(route('admin.reports.students.index', ['status' => 'need_assessment']))
            ->assertOk()
            ->assertSee('Report Student 1')
            ->assertDontSee('Students In View')
            ->assertDontSee('Active Students')
            ->assertSee('aria-label="Pagination"', false);
    }

    public function test_admin_reports_show_only_student_and_coach_activity_report_types(): void
    {
        [$admin, $coach, $student] = $this->makeCoreUsers();
        [$group, $chapters] = $this->makeGroupWithCurriculum($coach, $student);
        $scheduledStart = now()->subDays(3)->startOfHour();

        $common = [
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapters[0]->id,
            'chapter_index' => 0,
            'scheduled_for' => $scheduledStart,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledStart->copy()->addHour(),
            'duration_minutes' => 60,
        ];

        LiveClass::create($common + [
            'name' => 'On Time Class',
            'status' => 'completed',
            'room_id' => 'coach-report-on-time',
            'started_at' => $scheduledStart,
            'actual_start_at' => $scheduledStart,
            'ended_at' => $scheduledStart->copy()->addHour(),
            'actual_end_at' => $scheduledStart->copy()->addHour(),
            'completion_type' => 'manual',
            'chapter_completed' => true,
        ]);

        LiveClass::create($common + [
            'name' => 'Late Coach Class',
            'status' => 'completed',
            'room_id' => 'coach-report-late',
            'started_at' => $scheduledStart->copy()->addMinutes(12),
            'actual_start_at' => $scheduledStart->copy()->addMinutes(12),
            'ended_at' => $scheduledStart->copy()->addHour(),
            'actual_end_at' => $scheduledStart->copy()->addHour(),
            'completion_type' => 'manual',
            'chapter_completed' => true,
        ]);

        LiveClass::create($common + [
            'name' => 'Cancelled Coach Class',
            'status' => 'cancelled',
            'room_id' => 'coach-report-cancelled',
            'cancelled_at' => $scheduledStart->copy()->addMinutes(30),
            'actual_end_at' => $scheduledStart->copy()->addMinutes(30),
            'completion_type' => 'cancelled',
            'chapter_completed' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports', ['tab' => 'coach']))
            ->assertOk()
            ->assertSee('Coach Reports')
            ->assertSee('Attendance')
            ->assertSee('Cancelled Classes')
            ->assertSee('Joined Late')
            ->assertSee('2 / 3')
            ->assertDontSee('Homework reports')
            ->assertDontSee('Tournament reports')
            ->assertDontSee('Quiz reports');

        $this->actingAs($admin)
            ->get(route('admin.reports', ['tab' => 'coach', 'type' => 'cancelled']))
            ->assertOk()
            ->assertSee('Cancelled Coach Class');

        $this->actingAs($admin)
            ->get(route('admin.reports', ['tab' => 'coach', 'type' => 'late']))
            ->assertOk()
            ->assertSee('Late Coach Class')
            ->assertSee('12 min late');
    }

    public function test_admin_can_view_student_performance_report_with_aggregated_metrics(): void
    {
        [$admin, $coach, $student] = $this->makeCoreUsers();
        [$group, $chapters] = $this->makeGroupWithCurriculum($coach, $student);

        $class = LiveClass::create([
            'name' => 'Batch Performance Class',
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapters[0]->id,
            'chapter_index' => 0,
            'status' => 'completed',
            'room_id' => 'room-report-1',
            'scheduled_for' => now()->subDays(3),
            'scheduled_start_at' => now()->subDays(3),
            'scheduled_end_at' => now()->subDays(3)->addHour(),
            'actual_start_at' => now()->subDays(3),
            'actual_end_at' => now()->subDays(3)->addHour(),
            'duration_minutes' => 60,
            'completion_type' => 'manual',
            'chapter_completed' => true,
        ]);

        ClassParticipant::create([
            'live_class_id' => $class->id,
            'user_id' => $student->id,
            'joined_at' => now()->subDays(3)->addMinutes(5),
            'left_at' => now()->subDays(3)->addHour(),
            'last_seen_at' => now()->subDays(3)->addHour(),
        ]);

        $homework = Homework::create([
            'title' => 'Homework 1',
            'chapter_id' => $chapters[0]->id,
            'group_id' => $group->id,
            'assigned_by' => $coach->id,
            'source_class_id' => $class->id,
            'auto_assigned' => true,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'user_id' => $student->id,
            'status' => 'done',
            'marked_correct' => true,
            'submitted_at' => now()->subDay(),
        ]);

        $puzzleSet = PuzzleSet::create([
            'name' => 'Puzzle Set A',
            'level' => 'beginner',
            'category_name' => 'Mixed',
            'source_pgn' => '[Event "Puzzle"]',
            'total_puzzles' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $puzzle = Puzzle::create([
            'puzzle_set_id' => $puzzleSet->id,
            'puzzle_order' => 1,
            'source_pgn' => '[Event "Puzzle"]',
            'original_fen' => '8/8/8/8/8/8/8/8 w - - 0 1',
            'side_to_move' => 'w',
            'answer_pgn' => '1. Qh7#',
        ]);

        StudentPuzzleAttempt::create([
            'user_id' => $student->id,
            'puzzle_id' => $puzzle->id,
            'status' => 'completed',
            'wrong_attempts' => 1,
            'hint_count' => 2,
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2),
        ]);

        $quiz = Quiz::create([
            'week' => 1,
            'category' => 'Chess Fun',
            'type' => 'text',
            'question_text' => 'What is checkmate?',
            'options' => [
                ['kind' => 'text', 'value' => 'Answer 1'],
                ['kind' => 'text', 'value' => 'Answer 2'],
                ['kind' => 'text', 'value' => 'Answer 3'],
                ['kind' => 'text', 'value' => 'Answer 4'],
            ],
            'correct_index' => 0,
            'time_limit_seconds' => 60,
            'created_by' => $admin->id,
        ]);

        $quizSession = QuizSession::create([
            'name' => 'Quiz Session 1',
            'coach_id' => $coach->id,
            'level' => 'beginner',
            'countries' => ['IND'],
            'scheduled_at' => now()->subDays(4),
            'status' => 'ended',
            'week' => 1,
            'started_at' => now()->subDays(4),
            'ended_at' => now()->subDays(4)->addMinutes(15),
            'created_by' => $admin->id,
        ]);

        QuizSessionQuiz::create([
            'quiz_session_id' => $quizSession->id,
            'quiz_id' => $quiz->id,
            'position' => 1,
            'shown' => true,
        ]);

        QuizSessionAttendee::create([
            'quiz_session_id' => $quizSession->id,
            'user_id' => $student->id,
            'joined_at' => now()->subDays(4),
        ]);

        QuizSessionResponse::create([
            'quiz_session_id' => $quizSession->id,
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'picked_index' => 0,
            'is_correct' => true,
            'picked_at' => now()->subDays(4),
        ]);

        $tournament = Tournament::create([
            'name' => 'Student Cup',
            'format' => 'rapid',
            'time_control' => '10+0',
            'rounds' => 1,
            'scheduled_at' => now()->subDays(5),
            'round_minutes' => 30,
            'audience_type' => Tournament::AUDIENCE_ALL_STUDENTS,
            'status' => Tournament::STATUS_COMPLETED,
            'current_round' => 1,
            'created_by' => $admin->id,
        ]);

        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $student->id,
            'score' => 1.0,
            'buchholz' => 0.0,
            'withdrawn' => false,
            'bye_received' => false,
        ]);

        $opponent = $this->makeUser('student', [
            'name' => 'Opponent Student',
            'username' => 'ARCHERCHESS99',
            'country' => 'IND',
            'is_active' => true,
        ]);

        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $opponent->id,
            'score' => 0.0,
            'buchholz' => 0.0,
            'withdrawn' => false,
            'bye_received' => false,
        ]);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'round' => 1,
            'board' => 1,
            'white_user_id' => $student->id,
            'black_user_id' => $opponent->id,
            'is_bye' => false,
            'result' => '1-0',
            'winner_user_id' => $student->id,
            'white_clock_ms' => 600000,
            'black_clock_ms' => 600000,
            'last_move_at' => now()->subDays(5),
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.students.show', $student))
            ->assertOk()
            ->assertSee('Student Performance Report')
            ->assertSee('ARCHERCHESS01')
            ->assertSee('Batch Performance')
            ->assertSee('100.0%')
            ->assertSee('Puzzles Attempted')
            ->assertSee('Points')
            ->assertSee('onclick="window.history.back()"', false)
            ->assertDontSee('Back to Reports')
            ->assertDontSee('Late Joins')
            ->assertDontSee('Chapter Progress')
            ->assertDontSee('History');
    }

    public function test_coach_can_only_view_reports_for_own_students(): void
    {
        [$admin, $coachA, $studentA] = $this->makeCoreUsers();
        [, $chapters] = $this->makeGroupWithCurriculum($coachA, $studentA, 'Batch A');

        $coachB = $this->makeUser('coach', [
            'name' => 'Coach B',
            'username' => 'ARCHERCOACH02',
            'is_active' => true,
        ]);
        $studentB = $this->makeUser('student', [
            'name' => 'Student B',
            'username' => 'ARCHERCHESS02',
            'country' => 'IND',
            'is_active' => true,
        ]);
        $this->makeGroupWithCurriculum($coachB, $studentB, 'Batch B', $chapters);

        $this->actingAs($coachA)
            ->get(route('coach.reports.students.show', $studentA))
            ->assertOk();

        $this->actingAs($coachA)
            ->get(route('coach.reports.students.show', $studentB))
            ->assertForbidden();
    }

    public function test_student_report_date_filter_is_available_for_all_report_viewers(): void
    {
        [$admin, $coach, $student] = $this->makeCoreUsers();
        [$group, $chapters] = $this->makeGroupWithCurriculum($coach, $student);
        $staff = $this->makeUser('staff', [
            'name' => 'Staff One',
            'username' => 'ARCHERSTAFF01',
            'is_active' => true,
        ]);
        $olderStudent = $this->makeUser('student', [
            'name' => 'Older Report Student',
            'username' => 'ARCHERCHESS02',
            'country' => 'IND',
            'is_active' => true,
        ]);
        $group->students()->attach($olderStudent->id);

        $this->createCoachAssessment($coach, $student, '2026-08-10 10:00:00');
        $this->createCoachAssessment($coach, $olderStudent, '2026-08-05 10:00:00');

        $filteredRoutes = [
            route('admin.reports', ['tab' => 'student', 'date_from' => '2026-08-10', 'date_to' => '2026-08-10']),
            route('coach.reports', ['date_from' => '2026-08-10', 'date_to' => '2026-08-10']),
            route('staff.reports', ['date_from' => '2026-08-10', 'date_to' => '2026-08-10']),
        ];

        foreach ([[$admin, $filteredRoutes[0]], [$coach, $filteredRoutes[1]], [$staff, $filteredRoutes[2]]] as [$viewer, $url]) {
            $this->actingAs($viewer)
                ->get($url)
                ->assertOk()
                ->assertSee('name="date_from"', false)
                ->assertSee('name="date_to"', false)
                ->assertSee($student->username)
                ->assertDontSee($olderStudent->username);
        }

        $this->actingAs($student)
            ->get(route('student.reports', ['date_from' => '2026-08-05', 'date_to' => '2026-08-05']))
            ->assertOk()
            ->assertSee('No saved reports found');

        $this->actingAs($student)
            ->get(route('student.reports', ['date_from' => '2026-08-10', 'date_to' => '2026-08-10']))
            ->assertOk()
            ->assertSee('name="date_from"', false)
            ->assertSee($student->username);
    }

    public function test_staff_can_view_and_download_reports_after_admin_or_coach_publishes_them(): void
    {
        [$admin, $coach, $student] = $this->makeCoreUsers();
        $staff = $this->makeUser('staff', [
            'name' => 'Staff One',
            'username' => 'ARCHERSTAFF01',
            'is_active' => true,
        ]);

        $this->makeGroupWithCurriculum($coach, $student);

        $this->actingAs($staff)
            ->get(route('staff.reports'))
            ->assertOk()
            ->assertSee('No saved reports found')
            ->assertDontSee($student->username);

        $this->actingAs($staff)
            ->get(route('staff.reports.students.show', $student))
            ->assertNotFound();

        $this->actingAs($staff)
            ->get(route('staff.reports.students.pdf', $student))
            ->assertNotFound();

        $adminSaveResponse = $this->actingAs($admin)
            ->post(route('admin.reports.students.assessments.store', $student), $this->assessmentPayload());
        $adminAssessment = StudentPerformanceAssessment::query()->latest('id')->firstOrFail();
        $adminSaveResponse->assertRedirect(route('admin.reports.students.saved.show', [$student, $adminAssessment]));

        $this->actingAs($staff)
            ->get(route('staff.reports.students.show', $student))
            ->assertOk()
            ->assertSee('Saved Student Report')
            ->assertDontSee('Save Report');

        $this->actingAs($staff)
            ->get(route('staff.reports'))
            ->assertOk()
            ->assertSee($student->username)
            ->assertSee('Super Admin');

        Carbon::setTestNow(now()->addDay());
        $coachSaveResponse = $this->actingAs($coach)
            ->post(route('coach.reports.students.assessments.store', $student), $this->assessmentPayload());
        $coachAssessment = StudentPerformanceAssessment::query()->latest('id')->firstOrFail();
        $coachSaveResponse->assertRedirect(route('coach.reports.students.saved.show', [$student, $coachAssessment]));

        $this->actingAs($staff)
            ->get(route('staff.reports'))
            ->assertOk()
            ->assertSee($student->username)
            ->assertSee('View Report')
            ->assertSee('Download PDF')
            ->assertSee($student->username, false)
            ->assertDontSee('Create Report')
            ->assertDontSee('Need Assessment');

        $this->assertSame(2, substr_count($this->actingAs($staff)->get(route('staff.reports'))->getContent(), $student->username));

        $this->actingAs($staff)
            ->get(route('staff.reports.students.show', $student))
            ->assertOk()
            ->assertSee('Saved Student Report')
            ->assertDontSee('Save Report');

        $this->actingAs($staff)
            ->get(route('staff.reports.students.saved.show', [$student, $adminAssessment]))
            ->assertOk()
            ->assertSee($adminAssessment->assessed_at->timezone(config('app.timezone'))->format('d M Y, h:i A'));

        $this->actingAs($staff)
            ->get(route('staff.reports.students.pdf', $student))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertFalse(Route::has('staff.reports.students.assessments.store'));

        $this->actingAs($staff)
            ->post(route('admin.reports.students.assessments.store', $student), $this->assessmentPayload())
            ->assertForbidden();
    }

    public function test_assessment_save_creates_history_and_pdf_download_works(): void
    {
        [$admin, $coach, $student] = $this->makeCoreUsers();
        $this->makeGroupWithCurriculum($coach, $student);

        $payload = [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'opening_knowledge' => 8,
            'tactical_awareness' => 7,
            'calculation' => 8,
            'strategy' => 9,
            'positional_understanding' => 8,
            'endgame_knowledge' => 7,
            'board_vision' => 9,
            'decision_making' => 8,
            'time_management' => 6,
            'game_analysis' => 8,
            'strengths' => 'Strong tactical vision',
            'areas_to_improve' => 'Endgame conversion',
            'coach_remarks' => 'Good progress',
            'next_focus' => 'Rook endings',
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.reports.students.assessments.store', $student), $payload);

        $this->assertDatabaseHas('student_performance_assessments', [
            'student_id' => $student->id,
            'assessor_id' => $admin->id,
            'total_marks' => 78,
        ]);

        $assessment = StudentPerformanceAssessment::where('student_id', $student->id)->firstOrFail();
        $response->assertRedirect(route('admin.reports.students.saved.show', [$student, $assessment]));
        $this->assertSame(78.0, $assessment->percentage);
        $this->assertIsArray($assessment->report_snapshot);
        $this->assertArrayHasKey('profile', $assessment->report_snapshot);

        $this->actingAs($admin)
            ->get(route('admin.reports.students.pdf', $student))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_saved_and_need_assessment_tabs_follow_the_selected_date_range_and_coach_scope(): void
    {
        [$admin, $coach, $studentWithJulyReport] = $this->makeCoreUsers();
        [$coachGroup, $chapters] = $this->makeGroupWithCurriculum($coach, $studentWithJulyReport, 'Coach A Batch');
        $studentNeedingJulyReport = $this->makeUser('student', [
            'name' => 'Needs July Assessment',
            'username' => 'ARCHERCHESS02',
        ]);
        $coachGroup->students()->attach($studentNeedingJulyReport->id);

        $otherCoach = $this->makeUser('coach', [
            'name' => 'Coach Two',
            'username' => 'ARCHERCOACH02',
        ]);
        $otherStudent = $this->makeUser('student', [
            'name' => 'Other Coach Student',
            'username' => 'ARCHERCHESS03',
        ]);
        $this->makeGroupWithCurriculum($otherCoach, $otherStudent, 'Coach B Batch', $chapters);

        $this->createCoachAssessment(
            $coach,
            $studentWithJulyReport,
            '2026-07-15 10:00:00',
            '2026-07-01',
            '2026-07-31'
        );

        $inactiveStudent = $this->makeUser('student', [
            'name' => 'Inactive Report Student',
            'username' => 'ARCHERCHESS04',
            'is_active' => false,
        ]);
        $coachGroup->students()->attach($inactiveStudent->id);

        $july = ['date_from' => '2026-07-01', 'date_to' => '2026-07-31'];
        $august = ['date_from' => '2026-08-01', 'date_to' => '2026-08-31'];

        $this->actingAs($admin)
            ->get(route('admin.reports', ['tab' => 'student', 'status' => 'saved']))
            ->assertOk()
            ->assertSee($studentWithJulyReport->username);

        $this->actingAs($admin)
            ->get(route('admin.reports', ['tab' => 'student', 'status' => 'need_assessment']))
            ->assertOk()
            ->assertSee($studentWithJulyReport->username)
            ->assertSee($studentNeedingJulyReport->username)
            ->assertSee($otherStudent->username)
            ->assertDontSee($inactiveStudent->username);

        $this->actingAs($admin)
            ->get(route('admin.reports', array_merge(['tab' => 'student', 'status' => 'saved'], $july)))
            ->assertOk()
            ->assertSee($studentWithJulyReport->username)
            ->assertDontSee($studentNeedingJulyReport->username)
            ->assertDontSee($otherStudent->username);

        $this->actingAs($admin)
            ->get(route('admin.reports', array_merge(['tab' => 'student', 'status' => 'need_assessment'], $july)))
            ->assertOk()
            ->assertDontSee($studentWithJulyReport->username)
            ->assertSee($studentNeedingJulyReport->username)
            ->assertSee($otherStudent->username);

        $this->actingAs($admin)
            ->get(route('admin.reports', array_merge(['tab' => 'student', 'status' => 'need_assessment'], $august)))
            ->assertOk()
            ->assertSee($studentWithJulyReport->username)
            ->assertSee($studentNeedingJulyReport->username)
            ->assertSee($otherStudent->username);

        $this->actingAs($coach)
            ->get(route('coach.reports', array_merge(['status' => 'saved'], $july)))
            ->assertOk()
            ->assertSee($studentWithJulyReport->username)
            ->assertDontSee($otherStudent->username);

        $this->actingAs($coach)
            ->get(route('coach.reports', array_merge(['status' => 'need_assessment'], $july)))
            ->assertOk()
            ->assertSee($studentNeedingJulyReport->username)
            ->assertDontSee($studentWithJulyReport->username)
            ->assertDontSee($otherStudent->username);
    }

    public function test_report_metrics_are_calculated_only_inside_the_report_period(): void
    {
        [$admin, $coach, $student] = $this->makeCoreUsers();
        [$group, $chapters] = $this->makeGroupWithCurriculum($coach, $student);

        $julyClass = LiveClass::create([
            'name' => 'July Period Class',
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapters[0]->id,
            'chapter_index' => 0,
            'status' => 'completed',
            'room_id' => 'july-period-class',
            'scheduled_for' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kolkata'),
            'scheduled_start_at' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kolkata'),
            'scheduled_end_at' => Carbon::parse('2026-07-10 11:00:00', 'Asia/Kolkata'),
            'actual_start_at' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kolkata'),
            'actual_end_at' => Carbon::parse('2026-07-10 11:00:00', 'Asia/Kolkata'),
            'duration_minutes' => 60,
            'completion_type' => 'manual',
            'chapter_completed' => true,
        ]);
        ClassParticipant::create([
            'live_class_id' => $julyClass->id,
            'user_id' => $student->id,
            'joined_at' => Carbon::parse('2026-07-10 10:00:00', 'Asia/Kolkata'),
        ]);

        LiveClass::create([
            'name' => 'August Period Class',
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapters[0]->id,
            'chapter_index' => 0,
            'status' => 'completed',
            'room_id' => 'august-period-class',
            'scheduled_for' => Carbon::parse('2026-08-10 10:00:00', 'Asia/Kolkata'),
            'scheduled_start_at' => Carbon::parse('2026-08-10 10:00:00', 'Asia/Kolkata'),
            'scheduled_end_at' => Carbon::parse('2026-08-10 11:00:00', 'Asia/Kolkata'),
            'actual_start_at' => Carbon::parse('2026-08-10 10:00:00', 'Asia/Kolkata'),
            'actual_end_at' => Carbon::parse('2026-08-10 11:00:00', 'Asia/Kolkata'),
            'duration_minutes' => 60,
            'completion_type' => 'manual',
            'chapter_completed' => true,
        ]);

        $julyHomework = Homework::create([
            'title' => 'July Homework',
            'chapter_id' => $chapters[0]->id,
            'group_id' => $group->id,
            'assigned_by' => $coach->id,
            'due_date' => '2026-07-20',
        ]);
        $julyHomework->forceFill(['created_at' => '2026-07-12 10:00:00'])->saveQuietly();
        HomeworkSubmission::create([
            'homework_id' => $julyHomework->id,
            'user_id' => $student->id,
            'status' => 'done',
            'marked_correct' => true,
            'submitted_at' => Carbon::parse('2026-07-15 10:00:00', 'Asia/Kolkata'),
        ]);

        $augustHomework = Homework::create([
            'title' => 'August Homework',
            'chapter_id' => $chapters[0]->id,
            'group_id' => $group->id,
            'assigned_by' => $coach->id,
            'due_date' => '2026-08-20',
        ]);
        $augustHomework->forceFill(['created_at' => '2026-08-12 10:00:00'])->saveQuietly();
        HomeworkSubmission::create([
            'homework_id' => $augustHomework->id,
            'user_id' => $student->id,
            'status' => 'pending',
        ]);

        $julyTournament = Tournament::create([
            'name' => 'July Period Cup',
            'format' => 'rapid',
            'time_control' => '10+0',
            'rounds' => 1,
            'scheduled_at' => Carbon::parse('2026-07-25 10:00:00', 'Asia/Kolkata'),
            'round_minutes' => 30,
            'audience_type' => Tournament::AUDIENCE_ALL_STUDENTS,
            'status' => Tournament::STATUS_COMPLETED,
            'current_round' => 1,
            'created_by' => $admin->id,
        ]);
        TournamentParticipant::create([
            'tournament_id' => $julyTournament->id,
            'user_id' => $student->id,
            'score' => 1,
            'buchholz' => 0,
            'withdrawn' => false,
            'bye_received' => false,
        ]);

        $augustTournament = Tournament::create([
            'name' => 'August Period Cup',
            'format' => 'rapid',
            'time_control' => '10+0',
            'rounds' => 1,
            'scheduled_at' => Carbon::parse('2026-08-25 10:00:00', 'Asia/Kolkata'),
            'round_minutes' => 30,
            'audience_type' => Tournament::AUDIENCE_ALL_STUDENTS,
            'status' => Tournament::STATUS_COMPLETED,
            'current_round' => 1,
            'created_by' => $admin->id,
        ]);
        TournamentParticipant::create([
            'tournament_id' => $augustTournament->id,
            'user_id' => $student->id,
            'score' => 1,
            'buchholz' => 0,
            'withdrawn' => false,
            'bye_received' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.students.show', [
            'student' => $student,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));

        $response
            ->assertOk()
            ->assertSee('01 Jul 2026 to 31 Jul 2026')
            ->assertViewHas('report', function (array $report) {
                return $report['period']['label'] === '01 Jul 2026 to 31 Jul 2026'
                    && $report['classes']['total_classes_scheduled'] === 1
                    && $report['classes']['classes_attended'] === 1
                    && $report['classes']['attendance_percent'] === 100.0
                    && $report['homework']['homework_assigned'] === 1
                    && $report['homework']['homework_completed'] === 1
                    && $report['tournaments']['tournament_played'] === 1;
            });
    }

    public function test_multiple_saved_reports_remain_in_history_and_student_can_only_open_own_versions(): void
    {
        [, $coach, $student] = $this->makeCoreUsers();
        [$group, $chapters] = $this->makeGroupWithCurriculum($coach, $student);
        $otherStudent = $this->makeUser('student', [
            'name' => 'Private Other Student',
            'username' => 'ARCHERCHESS44',
        ]);
        $group->students()->attach($otherStudent->id);

        $julyReport = $this->createCoachAssessment($coach, $student, '2026-07-12 10:00:00');
        $julyReport->update(['strengths' => 'July tactical progress']);
        $augustReport = $this->createCoachAssessment($coach, $student, '2026-08-12 10:00:00');
        $augustReport->update(['strengths' => 'August strategic progress']);
        $otherReport = $this->createCoachAssessment($coach, $otherStudent, '2026-08-12 11:00:00');

        $historyResponse = $this->actingAs($student)->get(route('student.reports'));
        $historyResponse
            ->assertOk()
            ->assertSee('12 Jul 2026')
            ->assertSee('12 Aug 2026')
            ->assertSee(route('student.reports.saved.show', $julyReport), false)
            ->assertSee(route('student.reports.saved.show', $augustReport), false)
            ->assertDontSee($otherStudent->username);

        $this->actingAs($student)
            ->get(route('student.reports.saved.show', $julyReport))
            ->assertOk()
            ->assertSee('July tactical progress')
            ->assertDontSee('August strategic progress');

        $this->actingAs($student)
            ->get(route('student.reports.saved.show', $augustReport))
            ->assertOk()
            ->assertSee('August strategic progress');

        $this->actingAs($student)
            ->get(route('student.reports.saved.show', $otherReport))
            ->assertNotFound();
    }

    public function test_student_can_view_only_own_report_and_download_pdf_read_only(): void
    {
        [, $coach, $student] = $this->makeCoreUsers();
        [, $chapters] = $this->makeGroupWithCurriculum($coach, $student);
        $otherStudent = $this->makeUser('student', [
            'name' => 'Other Student',
            'username' => 'ARCHERCHESS22',
            'country' => 'USA',
            'is_active' => true,
        ]);

        $this->makeGroupWithCurriculum($coach, $otherStudent, 'Batch Other', $chapters);

        $this->actingAs($student)
            ->get(route('student.reports'))
            ->assertOk()
            ->assertSee('No saved reports found');

        $this->actingAs($student)
            ->get(route('student.reports.performance'))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('student.reports.performance.pdf'))
            ->assertNotFound();

        StudentPerformanceAssessment::create([
            'student_id' => $student->id,
            'assessor_id' => $coach->id,
            'assessor_role' => 'coach',
            'assessed_at' => now(),
            'opening_knowledge' => 8,
            'tactical_awareness' => 8,
            'calculation' => 7,
            'strategy' => 7,
            'positional_understanding' => 8,
            'endgame_knowledge' => 6,
            'board_vision' => 9,
            'decision_making' => 7,
            'time_management' => 6,
            'game_analysis' => 8,
            'total_marks' => 74,
            'percentage' => 74,
            'strengths' => 'Good board vision',
            'areas_to_improve' => 'Endgames',
            'coach_remarks' => 'Steady progress',
            'next_focus' => 'Calculation',
        ]);

        $this->actingAs($student)
            ->get(route('student.reports'))
            ->assertOk()
            ->assertSee('View Report')
            ->assertSee('Download PDF')
            ->assertDontSee('Create Report');

        $this->actingAs($student)
            ->get(route('student.reports.performance'))
            ->assertOk()
            ->assertSee('Saved Student Report')
            ->assertSee('Good board vision')
            ->assertDontSee('Save Report')
            ->assertDontSee('Back to Students');

        $this->actingAs($student)
            ->get(route('admin.reports.students.show', $otherStudent))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('admin.reports.students.assessments.store', $student), [
                'opening_knowledge' => 10,
                'tactical_awareness' => 10,
                'calculation' => 10,
                'strategy' => 10,
                'positional_understanding' => 10,
                'endgame_knowledge' => 10,
                'board_vision' => 10,
                'decision_making' => 10,
                'time_management' => 10,
                'game_analysis' => 10,
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('student.reports.performance.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    protected function assessmentPayload(): array
    {
        return [
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'opening_knowledge' => 8,
            'tactical_awareness' => 7,
            'calculation' => 8,
            'strategy' => 9,
            'positional_understanding' => 8,
            'endgame_knowledge' => 7,
            'board_vision' => 9,
            'decision_making' => 8,
            'time_management' => 6,
            'game_analysis' => 8,
            'strengths' => 'Strong tactical vision',
            'areas_to_improve' => 'Endgame conversion',
            'coach_remarks' => 'Good progress',
            'next_focus' => 'Rook endings',
        ];
    }

    protected function createCoachAssessment(
        User $coach,
        User $student,
        string $assessedAt,
        ?string $periodStart = null,
        ?string $periodEnd = null
    ): StudentPerformanceAssessment {
        $payload = $this->assessmentPayload();
        $totalMarks = collect(StudentPerformanceAssessment::scoreFields())
            ->sum(fn (string $field) => (int) $payload[$field]);

        return StudentPerformanceAssessment::create(array_merge($payload, [
            'student_id' => $student->id,
            'assessor_id' => $coach->id,
            'assessor_role' => 'coach',
            'assessed_at' => Carbon::parse($assessedAt, 'Asia/Kolkata'),
            'period_start' => $periodStart ?? Carbon::parse($assessedAt, 'Asia/Kolkata')->toDateString(),
            'period_end' => $periodEnd ?? Carbon::parse($assessedAt, 'Asia/Kolkata')->toDateString(),
            'total_marks' => $totalMarks,
            'percentage' => $totalMarks,
        ]));
    }

    protected function makeCoreUsers(): array
    {
        $admin = $this->makeUser('admin', [
            'name' => 'Admin User',
            'username' => 'admin-user',
            'is_active' => true,
        ]);

        $coach = $this->makeUser('coach', [
            'name' => 'Coach One',
            'username' => 'ARCHERCOACH01',
            'is_active' => true,
        ]);

        $student = $this->makeUser('student', [
            'name' => 'Student One',
            'username' => 'ARCHERCHESS01',
            'country' => 'IND',
            'is_active' => true,
        ]);

        return [$admin, $coach, $student];
    }

    protected function makeGroupWithCurriculum(User $coach, User $student, string $name = 'Batch Performance', ?array $chapters = null): array
    {
        $chapters = $chapters ?: [
            Chapter::create([
                'name' => 'Chapter 1',
                'pgn' => '[Event "Chapter 1"]',
                'level' => 'beginner',
                'sequence_number' => 1,
                'uploaded_by' => $coach->id,
            ]),
            Chapter::create([
                'name' => 'Chapter 2',
                'pgn' => '[Event "Chapter 2"]',
                'level' => 'beginner',
                'sequence_number' => 2,
                'uploaded_by' => $coach->id,
            ]),
        ];

        $group = Group::create([
            'name' => $name,
            'timezone' => 'Asia/Kolkata',
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'created_by' => $coach->id,
            'unlocked_chapter_index' => 0,
            'is_active' => true,
        ]);

        $group->students()->attach($student->id);
        $group->chapters()->attach([
            $chapters[0]->id => ['sequence_order' => 0],
            $chapters[1]->id => ['sequence_order' => 1],
        ]);

        return [$group->fresh()->load(['coach', 'students', 'chapters']), $chapters];
    }

    protected function makeUser(string $role, array $attributes = []): User
    {
        static $counter = 1;

        $defaults = [
            'name' => ucfirst($role).' '.$counter,
            'username' => strtolower($role).$counter,
            'password' => Hash::make('password123'),
            'role' => $role,
            'country' => $role === 'student' ? 'IND' : null,
            'is_active' => true,
        ];

        $counter++;

        return User::create(array_merge($defaults, $attributes));
    }
}

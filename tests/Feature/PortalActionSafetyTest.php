<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Group;
use App\Models\GroupSchedule;
use App\Models\LiveClass;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\QuizSessionAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalActionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_student_filter_lists_only_active_students(): void
    {
        $admin = $this->makeUser('admin');
        $activeStudent = $this->makeUser('student', [
            'name' => 'Visible Active Student',
            'username' => 'ARCHERCHESS01',
        ]);
        $inactiveStudent = $this->makeUser('student', [
            'name' => 'Hidden Inactive Student',
            'username' => 'ARCHERCHESS02',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.classes'));

        $response->assertOk()
            ->assertSee($activeStudent->name)
            ->assertDontSee($inactiveStudent->name);
    }

    public function test_student_quiz_session_join_is_idempotent(): void
    {
        $student = $this->makeUser('student', ['country' => 'IND']);
        $coach = $this->makeUser('coach');
        $group = $this->makeGroup($coach, $student);
        $session = QuizSession::create([
            'name' => 'Beginner Quiz',
            'coach_id' => $coach->id,
            'level' => $group->level,
            'scheduled_at' => now()->addHour(),
            'status' => 'scheduled',
            'created_by' => $this->makeUser('admin')->id,
        ]);

        $this->actingAs($student)
            ->post(route('student.quiz-sessions.join', $session))
            ->assertRedirect(route('student.quiz-sessions.show', $session));

        $this->actingAs($student)
            ->post(route('student.quiz-sessions.join', $session))
            ->assertRedirect(route('student.quiz-sessions.show', $session));

        $this->assertSame(1, QuizSessionAttendee::where('quiz_session_id', $session->id)->where('user_id', $student->id)->count());
    }

    public function test_quiz_session_update_rejects_non_coach_assignment_with_validation_error(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $staff = $this->makeUser('staff');
        $session = QuizSession::create([
            'name' => 'Existing Session',
            'coach_id' => $coach->id,
            'level' => 'beginner',
            'scheduled_at' => now()->addHour(),
            'status' => 'scheduled',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.quiz-sessions.edit', $session))
            ->put(route('admin.quiz-sessions.update', $session), [
                'name' => 'Existing Session',
                'coach_id' => $staff->id,
                'level' => 'beginner',
                'scheduled_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.quiz-sessions.edit', $session))
            ->assertSessionHasErrors('coach_id');
    }

    public function test_library_delete_returns_friendly_error_when_chapter_has_live_class_dependency(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $chapter = Chapter::create([
            'name' => 'Protected Chapter',
            'pgn' => '1. e4 e5 2. Nf3 Nc6',
            'level' => 'beginner',
            'sequence_number' => 1,
            'uploaded_by' => $admin->id,
            'sort_order' => 1,
            'move_count' => 2,
        ]);
        $group = $this->makeGroup($coach, $student);
        $schedule = GroupSchedule::create([
            'group_id' => $group->id,
            'day_of_week' => 2,
            'start_time' => '19:00:00',
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        LiveClass::create([
            'name' => 'Dependent Class',
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapter->id,
            'chapter_index' => 0,
            'group_schedule_id' => $schedule->id,
            'status' => 'scheduled',
            'room_id' => (string) Str::uuid(),
            'scheduled_for' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.library'))
            ->delete(route('admin.library.chapter.delete', $chapter))
            ->assertRedirect(route('admin.library'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('chapters', ['id' => $chapter->id]);
    }

    public function test_quiz_store_surfaces_field_validation_for_empty_text_option(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->from(route('admin.quizzes.create'))
            ->post(route('admin.quizzes.store'), [
                'week' => 1,
                'category' => 'Chess',
                'age_min' => 5,
                'age_max' => 8,
                'type' => 'text',
                'question_text' => 'Best move?',
                'correct_index' => 0,
                'time_limit_seconds' => 60,
                'option_kind' => ['text', 'text', 'text', 'text'],
                'option_text' => ['A', '', 'C', 'D'],
            ])
            ->assertRedirect(route('admin.quizzes.create'))
            ->assertSessionHasErrors('option_text.1');

        $this->assertSame(0, Quiz::count());
    }

    public function test_admin_can_bulk_upload_weekly_age_grouped_quizzes_from_csv(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)
            ->get(route('admin.quizzes.index'))
            ->assertOk()
            ->assertSee('Upload Weekly CSV');
        $this->actingAs($admin)
            ->get(route('admin.quizzes.import'))
            ->assertOk()
            ->assertSee('Upload Weekly Quizzes')
            ->assertSee('Download CSV Template');

        $csv = implode("\n", [
            'week,category,age_min,age_max,question,option_a,option_b,option_c,option_d,correct_answer',
            '1,Chess,5,8,How many squares are on a chessboard?,32,48,64,100,C',
            '2,Science,6,8,Which planet is our home?,Mars,Earth,Venus,Jupiter,B',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.quizzes.import.store'), [
                'quiz_file' => UploadedFile::fake()->createWithContent('quizzes.csv', $csv),
            ])
            ->assertRedirect(route('admin.quizzes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('quizzes', [
            'week' => 1,
            'category' => 'Chess',
            'age_min' => 5,
            'age_max' => 8,
            'correct_index' => 2,
            'time_limit_seconds' => 120,
        ]);
        $this->assertDatabaseHas('quizzes', [
            'week' => 2,
            'category' => 'Science',
            'correct_index' => 1,
        ]);
    }

    private function makeGroup(User $coach, User $student): Group
    {
        $group = Group::create([
            'name' => 'Safety Group ' . Group::count(),
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'created_by' => $this->makeUser('admin')->id,
            'timezone' => Group::DEFAULT_TIMEZONE,
            'is_active' => true,
        ]);

        $group->students()->attach($student->id);

        return $group;
    }

    private function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => ucfirst($role) . ' User ' . User::count(),
            'username' => $role . '_' . (User::count() + 1),
            'role' => $role,
            'is_active' => true,
            'password' => bcrypt('password'),
        ], $overrides));
    }
}

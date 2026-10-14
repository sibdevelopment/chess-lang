<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ClassQuestion;
use App\Models\ClassQuestionAttempt;
use App\Models\Group;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PastClassPuzzleAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_staff_can_view_complete_puzzle_history_for_a_past_class(): void
    {
        $admin = $this->makeUser('admin', 'admin-audit');
        $staff = $this->makeUser('staff', 'staff-audit');
        $coach = $this->makeUser('coach', 'coach-audit');
        $student = $this->makeUser('student', 'student-audit', 'Audit Student');
        $absentStudent = $this->makeUser('student', 'absent-audit', 'Absent Student');

        $group = Group::create([
            'name' => 'Audit Batch',
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);
        $group->students()->attach([$student->id, $absentStudent->id]);

        $chapter = Chapter::create([
            'name' => 'Tactical Audit',
            'pgn' => '[Event "Audit"]\n\n1. e4 e5',
            'level' => 'beginner',
            'uploaded_by' => $admin->id,
        ]);

        $class = LiveClass::create([
            'name' => 'Completed Puzzle Class',
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapter->id,
            'status' => 'completed',
            'room_id' => 'audit-room',
            'scheduled_for' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_minutes' => 60,
            'completion_type' => 'manual',
            'chapter_completed' => true,
        ]);
        $class->participants()->attach($student->id, [
            'joined_at' => now()->subMinutes(58),
            'left_at' => now()->subMinutes(2),
        ]);

        $question = ClassQuestion::create([
            'live_class_id' => $class->id,
            'coach_id' => $coach->id,
            'position_fen' => '8/8/8/8/8/8/4K3/7k w - - 0 1',
            'expected_moves' => ['e2-e3', 'h1-h2'],
            'text' => 'Find the winning king route',
            'is_active' => false,
            'ended_at' => now()->subMinutes(20),
        ]);

        ClassQuestionAttempt::create([
            'class_question_id' => $question->id,
            'user_id' => $student->id,
            'current_fen' => $question->position_fen,
            'moves_played' => ['e2-e3', 'h1-g1', 'h1-h2'],
            'correct_count' => 2,
            'wrong_count' => 1,
            'completed' => true,
            'completed_at' => now()->subMinutes(21),
        ]);

        foreach ([[$admin, 'admin'], [$staff, 'staff']] as [$viewer, $prefix]) {
            $response = $this->actingAs($viewer)
                ->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->get(route($prefix.'.classes.show', $class));

            $response->assertOk()->assertJsonPath('title', 'Completed Puzzle Class');
            $html = (string) $response->json('html');

            $this->assertStringContainsString('Classroom Materials', $html);
            $this->assertStringNotContainsString('Uploaded Files', $html);
            $this->assertStringContainsString('Find the winning king route', $html);
            $this->assertStringContainsString('Position shown to students', $html);
            $this->assertStringContainsString('8/8/8/8/8/8/4K3/7k w - - 0 1', $html);
            $this->assertStringContainsString('e2-e3', $html);
            $this->assertStringContainsString('h1-h2', $html);
            $this->assertStringContainsString('Audit Student', $html);
            $this->assertStringContainsString('2/2', $html);
            $this->assertStringContainsString('Solved', $html);
            $this->assertStringContainsString('Absent Student', $html);
            $this->assertStringContainsString('Not attempted', $html);
        }

        $this->actingAs($admin)
            ->withoutHeader('X-Requested-With')
            ->get(route('admin.classes.show', $class))
            ->assertOk()
            ->assertSee('Past Class Details')
            ->assertSee('Back to Past Classes');

        $this->actingAs($admin)
            ->get(route('admin.classes', ['section' => 'past', 'search' => 'Completed Puzzle']))
            ->assertOk()
            ->assertSee('Search Classes')
            ->assertSee($class->name);

        $this->actingAs($admin)
            ->get(route('admin.classes', ['section' => 'past', 'search' => 'No matching class']))
            ->assertOk()
            ->assertDontSee($class->name);

        $class->update(['status' => 'live', 'ended_at' => null]);

        $this->actingAs($admin)
            ->withoutHeader('X-Requested-With')
            ->get(route('admin.classes.show', $class))
            ->assertOk()
            ->assertSee('Live Class Details')
            ->assertSee('Back to Live Classes')
            ->assertDontSee('Past Class Details');
    }

    private function makeUser(string $role, string $username, ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? ucfirst($role).' User',
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }
}

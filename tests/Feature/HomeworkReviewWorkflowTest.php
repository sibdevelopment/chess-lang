<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HomeworkReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_staff_can_see_completion_review_status_and_feedback(): void
    {
        [$admin, $staff, $coach, $homework, $checked, $awaiting] = $this->makeHomeworkScenario();

        foreach ([[$admin, 'admin'], [$staff, 'staff']] as [$viewer, $prefix]) {
            $this->actingAs($viewer)
                ->get(route($prefix.'.homework'))
                ->assertOk()
                ->assertSee('67%')
                ->assertSee('2/3 solved')
                ->assertSee('Review in progress')
                ->assertSee('1/2 solved work checked')
                ->assertSee('note');

            $this->actingAs($viewer)
                ->get(route($prefix.'.homework.show', $homework))
                ->assertOk()
                ->assertSee('Coach checked')
                ->assertSee('Awaiting review')
                ->assertSee('Checked by coach')
                ->assertSee('Awaiting coach review')
                ->assertSee('Not ready for review')
                ->assertSee('Strong calculation. Keep going!')
                ->assertSee('No feedback added');

            $filteredList = $this->actingAs($viewer)
                ->get(route($prefix.'.homework', ['student_id' => $checked->user_id]))
                ->assertOk()
                ->assertSee('All students')
                ->assertSee('100%')
                ->assertSee('1/1 solved');

            $filteredList->assertViewHas('homework', function ($paginator) {
                $filteredHomework = $paginator->getCollection()->first();

                return $filteredHomework
                    && $filteredHomework->stats['total'] === 1
                    && $filteredHomework->stats['completion_percent'] === 100;
            });

            $filteredReport = $this->actingAs($viewer)
                ->get(route($prefix.'.homework.show', [
                    'homework' => $homework,
                    'student_id' => $checked->user_id,
                ]))
                ->assertOk()
                ->assertSee('Checked by coach');

            $filteredReport
                ->assertViewHas('visibleStudents', fn ($students) => $students->count() === 1
                    && $students->first()->id === $checked->user_id)
                ->assertViewHas('stats', fn ($filteredStats) => $filteredStats['total'] === 1
                    && $filteredStats['completion_percent'] === 100
                    && $filteredStats['reviewed'] === 1);
        }

        $stats = $homework->fresh('submissions')->stats;
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['done']);
        $this->assertSame(67, $stats['completion_percent']);
        $this->assertSame(1, $stats['reviewed']);
        $this->assertSame(1, $stats['awaiting_review']);
        $this->assertSame(1, $stats['feedback_count']);
        $this->assertSame(50, $stats['review_percent']);
        $this->assertSame('Review in progress', $stats['review_status']);

        $this->assertTrue($checked->fresh()->marked_correct);
        $this->assertNull($awaiting->fresh()->marked_correct);
        $this->assertFalse(Route::has('admin.homework.mark'));
        $this->assertFalse(Route::has('staff.homework.mark'));
    }

    public function test_assigned_coach_can_review_and_update_feedback(): void
    {
        [, , $coach, $homework, $checked, $awaiting] = $this->makeHomeworkScenario();

        $this->actingAs($coach)
            ->get(route('coach.homework', [
                'student_id' => $awaiting->user_id,
                'status' => 'awaiting_review',
            ]))
            ->assertOk()
            ->assertSee($homework->title)
            ->assertSee('All students')
            ->assertSee('Awaiting review')
            ->assertSee('Review')
            ->assertViewHas('homework', function ($paginator) use ($awaiting) {
                $filteredHomework = $paginator->getCollection()->first();

                return $filteredHomework
                    && $filteredHomework->submissions->count() === 1
                    && $filteredHomework->submissions->first()->user_id === $awaiting->user_id;
            });

        $this->actingAs($coach)
            ->get(route('coach.homework.show', [
                'homework' => $homework,
                'student_id' => $awaiting->user_id,
            ]))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Awaiting review')
            ->assertSee('Save feedback')
            ->assertViewHas('visibleStudents', fn ($students) => $students->count() === 1
                && $students->first()->id === $awaiting->user_id);

        $this->actingAs($coach)
            ->post(route('coach.homework.mark', $awaiting), [
                'marked_correct' => '0',
                'coach_note' => 'Practise the final mating pattern again.',
            ])
            ->assertRedirect();

        $awaiting->refresh();
        $this->assertFalse($awaiting->marked_correct);
        $this->assertSame('Practise the final mating pattern again.', $awaiting->coach_note);

        $stats = $homework->fresh('submissions')->stats;
        $this->assertSame(2, $stats['reviewed']);
        $this->assertSame(0, $stats['awaiting_review']);
        $this->assertSame('Review complete', $stats['review_status']);

        $this->actingAs($coach)
            ->postJson(route('coach.homework.mark', $checked), [
                'marked_correct' => null,
                'coach_note' => 'Updated feedback without changing the result.',
            ])
            ->assertRedirect();

        $checked->refresh();
        $this->assertTrue($checked->marked_correct);
        $this->assertSame('Updated feedback without changing the result.', $checked->coach_note);
    }

    private function makeHomeworkScenario(): array
    {
        $admin = $this->makeUser('admin', 'homework-admin');
        $staff = $this->makeUser('staff', 'homework-staff');
        $coach = $this->makeUser('coach', 'homework-coach');
        $studentOne = $this->makeUser('student', 'homework-student-one', 'Student One');
        $studentTwo = $this->makeUser('student', 'homework-student-two', 'Student Two');
        $studentThree = $this->makeUser('student', 'homework-student-three', 'Student Three');

        $group = Group::create([
            'name' => 'Homework Batch',
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);
        $group->students()->attach([$studentOne->id, $studentTwo->id, $studentThree->id]);

        $chapter = Chapter::create([
            'name' => 'Homework Tactics',
            'pgn' => '[Event "Homework"]\n\n1. e4 e5',
            'level' => 'beginner',
            'uploaded_by' => $admin->id,
        ]);

        $homework = Homework::create([
            'title' => 'Calculation Practice',
            'chapter_id' => $chapter->id,
            'group_id' => $group->id,
            'assigned_by' => $coach->id,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $checked = HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'user_id' => $studentOne->id,
            'status' => 'done',
            'marked_correct' => true,
            'coach_note' => 'Strong calculation. Keep going!',
            'submitted_at' => now()->subHour(),
        ]);
        $awaiting = HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'user_id' => $studentTwo->id,
            'status' => 'done',
            'submitted_at' => now()->subMinutes(30),
        ]);
        HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'user_id' => $studentThree->id,
            'status' => 'pending',
        ]);

        return [$admin, $staff, $coach, $homework, $checked, $awaiting];
    }

    private function makeUser(string $role, string $username, ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? ucfirst($role).' Homework User',
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }
}

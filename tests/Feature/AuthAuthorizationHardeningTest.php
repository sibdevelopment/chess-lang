<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\LiveClass;
use App\Models\StudentAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('admin|127.0.0.1');
        parent::tearDown();
    }

    public function test_guest_is_redirected_from_protected_portal_landing_pages(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect('/login');
        $this->get(route('staff.dashboard'))->assertRedirect('/login');
        $this->get(route('coach.classes'))->assertRedirect('/login');
        $this->get(route('student.classes'))->assertRedirect('/login');
    }

    public function test_guest_json_request_gets_unauthenticated_response(): void
    {
        $class = $this->makeLiveClass($this->makeUser('coach'), $this->makeGroup($this->makeUser('admin'), $this->makeUser('coach')));

        $this->getJson(route('coach.class.roster', $class))
            ->assertStatus(401);
    }

    public function test_login_redirects_each_role_to_its_landing_page(): void
    {
        foreach (['admin', 'staff', 'coach', 'student'] as $role) {
            $user = $this->makeUser($role, ['password' => Hash::make('secret123')]);

            $response = $this->post('/login', [
                'username' => $user->username,
                'password' => 'secret123',
            ]);

            $response->assertRedirect($user->landingPath());
            $this->post('/logout');
        }
    }

    public function test_coach_and_student_dashboards_are_removed(): void
    {
        $this->assertFalse(Route::has('coach.dashboard'));
        $this->assertFalse(Route::has('student.dashboard'));

        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');

        $this->actingAs($coach)
            ->get('/coach/dashboard')
            ->assertNotFound();

        $this->actingAs($student)
            ->get('/student/dashboard')
            ->assertNotFound();

        $this->actingAs($coach)
            ->get(route('coach.classes'))
            ->assertOk()
            ->assertDontSee('Dashboard');

        $this->actingAs($student)
            ->get(route('student.classes'))
            ->assertOk()
            ->assertDontSee('Dashboard');
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $student = $this->makeUser('student');

        $this->actingAs($student)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = $this->makeUser('admin', ['password' => Hash::make('right-pass')]);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'username' => $user->username,
                'password' => 'wrong-pass',
            ])->assertSessionHasErrors('username');
        }

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-pass',
        ])->assertSessionHasErrors('username');

        $errors = session('errors')->get('username', []);
        $this->assertNotEmpty($errors);
        $this->assertStringStartsWith('Too many login attempts. Try again in ', $errors[0]);
    }

    public function test_non_admin_roles_cannot_access_admin_routes(): void
    {
        foreach (['staff', 'coach', 'student'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get(route('admin.dashboard'))
                ->assertForbidden();
        }
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_student_creation_uses_archerchess_sequence_and_default_password(): void
    {
        $admin = $this->makeUser('admin');

        User::create([
            'name' => 'Student One',
            'username' => 'ARCHERCHESS01',
            'password' => Hash::make('old-pass'),
            'role' => 'student',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Student Two',
            'username' => 'ARCHERCHESS02',
            'password' => Hash::make('old-pass'),
            'role' => 'student',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.students.store'), [
                'name' => 'Next Student',
                'username' => 'SHOULDNOTUSE',
                'password' => 'something-else',
                'country' => '',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.students'));

        $student = User::where('role', 'student')
            ->where('name', 'Next Student')
            ->firstOrFail();

        $this->assertSame('ARCHERCHESS03', $student->username);
        $this->assertFalse($student->is_active);
        $this->assertNull($student->country);
        $this->assertTrue(Hash::check('ARCHERCHESS', $student->password));
    }

    public function test_student_moves_from_new_id_to_active_when_country_is_saved(): void
    {
        $admin = $this->makeUser('admin');
        $student = User::create([
            'name' => 'Needs Country',
            'username' => 'ARCHERCHESS01',
            'password' => Hash::make('ARCHERCHESS'),
            'role' => 'student',
            'country' => null,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.students.update', $student), [
                'name' => 'Needs Country',
                'username' => 'ARCHERCHESS01',
                'password' => '',
                'country' => 'IND',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.students'));

        $student->refresh();

        $this->assertSame('IND', $student->country);
        $this->assertTrue($student->is_active);
    }

    public function test_student_password_changes_only_when_new_password_is_submitted(): void
    {
        $admin = $this->makeUser('admin');
        $student = User::create([
            'name' => 'Password Student',
            'username' => 'ARCHERCHESS01',
            'password' => Hash::make('ARCHERCHESS'),
            'role' => 'student',
            'country' => 'IND',
            'is_active' => true,
        ]);

        $originalHash = $student->password;

        $this->actingAs($admin)
            ->put(route('admin.students.update', $student), [
                'name' => 'Password Student',
                'username' => 'ARCHERCHESS01',
                'password' => '',
                'country' => 'IND',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.students'));

        $student->refresh();
        $this->assertSame($originalHash, $student->password);

        $this->actingAs($admin)
            ->put(route('admin.students.update', $student), [
                'name' => 'Password Student',
                'username' => 'ARCHERCHESS01',
                'password' => 'NewSecure123',
                'country' => 'IND',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.students'));

        $student->refresh();

        $this->assertTrue(Hash::check('NewSecure123', $student->password));
        $this->assertFalse(Hash::check('ARCHERCHESS', $student->password));
    }

    public function test_coach_creation_uses_archercoach_sequence_and_first_name_password(): void
    {
        $admin = $this->makeUser('admin');

        User::create([
            'name' => 'Coach One',
            'username' => 'ARCHERCOACH01',
            'password' => Hash::make('old-pass'),
            'role' => 'coach',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Coach Two',
            'username' => 'ARCHERCOACH02',
            'password' => Hash::make('old-pass'),
            'role' => 'coach',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.coaches.store'), [
                'name' => 'Rahul Sharma',
                'username' => 'SHOULDNOTUSE',
                'password' => 'something-else',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.coaches'));

        $coach = User::where('role', 'coach')
            ->where('name', 'Rahul Sharma')
            ->firstOrFail();

        $this->assertSame('ARCHERCOACH03', $coach->username);
        $this->assertTrue(Hash::check('Rahul@1', $coach->password));
        $this->assertSame('Rahul@1', $coach->credentialHint());
    }

    public function test_coach_edit_does_not_change_username_or_password(): void
    {
        $admin = $this->makeUser('admin');
        $coach = User::create([
            'name' => 'Rahul Sharma',
            'username' => 'ARCHERCOACH01',
            'password' => Hash::make('Rahul@1'),
            'role' => 'coach',
            'is_active' => true,
        ]);
        $coach->storeCredentialHint('Rahul@1');

        $this->actingAs($admin)
            ->put(route('admin.coaches.update', $coach), [
                'name' => 'Amit Sharma',
                'username' => 'ARCHERCOACH99',
                'password' => 'Amit@1',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.coaches'));

        $coach->refresh();

        $this->assertSame('ARCHERCOACH01', $coach->username);
        $this->assertFalse($coach->is_active);
        $this->assertTrue(Hash::check('Rahul@1', $coach->password));
        $this->assertFalse(Hash::check('Amit@1', $coach->password));
        $this->assertSame('Rahul@1', $coach->credentialHint());
    }

    public function test_staff_creation_uses_archerstaff_sequence_and_first_name_password(): void
    {
        $admin = $this->makeUser('admin');

        User::create([
            'name' => 'Staff One',
            'username' => 'ARCHERSTAFF01',
            'password' => Hash::make('old-pass'),
            'role' => 'staff',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Neha Gupta',
                'username' => 'SHOULDNOTUSE',
                'password' => 'something-else',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.staff'));

        $staff = User::where('role', 'staff')
            ->where('name', 'Neha Gupta')
            ->firstOrFail();

        $this->assertSame('ARCHERSTAFF02', $staff->username);
        $this->assertTrue(Hash::check('Neha@1', $staff->password));
        $this->assertSame('Neha@1', $staff->credentialHint());
    }

    public function test_admin_can_open_past_classes_without_avatar_color_column(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $group = $this->makeGroup($admin, $coach, [$student]);
        $chapter = $this->makeChapter($admin);

        $class = LiveClass::create([
            'name' => 'Completed Class',
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapter->id,
            'chapter_index' => 0,
            'status' => 'ended',
            'room_id' => (string) Str::uuid(),
            'scheduled_for' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classes', ['tab' => 'past']))
            ->assertOk()
            ->assertSee('Completed Class');

        $this->actingAs($admin)
            ->getJson(route('admin.classes.show', $class), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonStructure(['html', 'title']);
    }

    public function test_coach_cannot_access_another_coachs_group_chapters(): void
    {
        $ownerCoach = $this->makeUser('coach');
        $otherCoach = $this->makeUser('coach');
        $group = $this->makeGroup($this->makeUser('admin'), $ownerCoach);

        $this->actingAs($otherCoach)
            ->get(route('coach.groups.chapters', $group))
            ->assertForbidden();
    }

    public function test_student_cannot_access_coach_live_class_route(): void
    {
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $group = $this->makeGroup($this->makeUser('admin'), $coach, [$student]);
        $class = $this->makeLiveClass($coach, $group);

        $this->actingAs($student)
            ->get(route('coach.class.live', $class))
            ->assertForbidden();
    }

    public function test_student_can_only_open_own_analysis(): void
    {
        $owner = $this->makeUser('student');
        $other = $this->makeUser('student');
        $analysis = StudentAnalysis::create([
            'user_id' => $owner->id,
            'name' => 'My Study',
            'pgn' => '[Event "Study"]',
            'reviews' => [],
            'commentary' => [],
        ]);

        $this->actingAs($other)
            ->get(route('student.analyse.show', $analysis))
            ->assertNotFound();
    }

    public function test_unassigned_student_cannot_join_live_class(): void
    {
        $coach = $this->makeUser('coach');
        $assigned = $this->makeUser('student');
        $other = $this->makeUser('student');
        $group = $this->makeGroup($this->makeUser('admin'), $coach, [$assigned]);
        $class = $this->makeLiveClass($coach, $group);

        $this->actingAs($other)
            ->get(route('student.class.join', $class))
            ->assertForbidden();
    }

    public function test_coach_cannot_mark_submission_for_another_coachs_homework(): void
    {
        $admin = $this->makeUser('admin');
        $coachA = $this->makeUser('coach');
        $coachB = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $group = $this->makeGroup($admin, $coachA, [$student]);
        $chapter = $this->makeChapter($admin);

        $homework = Homework::create([
            'title' => 'Homework 1',
            'chapter_id' => $chapter->id,
            'group_id' => $group->id,
            'assigned_by' => $coachA->id,
            'due_date' => now()->addDay(),
        ]);

        $submission = HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'user_id' => $student->id,
            'status' => 'pending',
        ]);

        $this->actingAs($coachB)
            ->post(route('coach.homework.mark', $submission), [
                'marked_correct' => true,
                'coach_note' => 'Nice work',
            ])
            ->assertForbidden();
    }

    public function test_admin_group_add_student_rejects_non_student_users(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $group = $this->makeGroup($admin, $coach);

        $this->actingAs($admin)
            ->from(route('admin.groups.show', $group))
            ->post(route('admin.groups.add-student', $group), [
                'student_id' => $coach->id,
            ])
            ->assertRedirect(route('admin.groups.show', $group))
            ->assertSessionHasErrors('student_id');
    }

    private function makeUser(string $role, array $overrides = []): User
    {
        static $counter = 1;

        $user = User::create(array_merge([
            'name' => ucfirst($role).' User '.$counter,
            'username' => $role.$counter,
            'email' => $role.$counter.'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ], $overrides));

        $counter++;

        return $user;
    }

    private function makeChapter(User $uploader, array $overrides = []): Chapter
    {
        static $sequence = 1;

        $chapter = Chapter::create(array_merge([
            'name' => 'Chapter '.$sequence,
            'pgn' => '[Event "Chapter '.$sequence.'"] 1. e4 e5 *',
            'level' => 'beginner',
            'sequence_number' => $sequence,
            'uploaded_by' => $uploader->id,
            'sort_order' => $sequence,
        ], $overrides));

        $sequence++;

        return $chapter;
    }

    private function makeGroup(User $admin, User $coach, array $students = []): Group
    {
        $group = Group::create([
            'name' => 'Group '.Str::random(5),
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'created_by' => $admin->id,
            'unlocked_chapter_index' => 0,
        ]);

        if ($students !== []) {
            $group->students()->attach(collect($students)->pluck('id'));
        }

        return $group;
    }

    private function makeLiveClass(User $coach, Group $group): LiveClass
    {
        $creator = $group->creator()->first() ?? $this->makeUser('admin');
        $chapter = $this->makeChapter($creator);

        return LiveClass::create([
            'name' => 'Live Class '.Str::random(4),
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapter->id,
            'chapter_index' => 0,
            'status' => 'live',
            'room_id' => (string) Str::uuid(),
            'scheduled_for' => now()->subMinute(),
            'started_at' => now()->subMinute(),
        ]);
    }
}

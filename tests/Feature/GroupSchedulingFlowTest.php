<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Group;
use App\Models\GroupSchedule;
use App\Models\Homework;
use App\Models\LiveClass;
use App\Models\User;
use App\Support\ClassScheduler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroupSchedulingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 23, 10, 0, 0, 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_schedule_creates_correct_next_class(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $chapters = $this->makeChapters(4);

        $response = $this->actingAs($admin)->post(route('admin.groups.store'), [
            'name' => 'Batch A',
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'students' => [$student->id],
            'first_chapter_id' => $chapters[1]->id,
            'schedules' => [
                ['day' => 5, 'time' => '19:00'],
            ],
        ]);

        $response->assertRedirect(route('admin.groups'));

        $group = Group::with(['chapters', 'schedules', 'liveClasses'])->firstOrFail();

        $this->assertSame(0, (int) $group->unlocked_chapter_index);
        $this->assertSame(Group::DEFAULT_TIMEZONE, $group->timezone);
        $this->assertEquals([$chapters[1]->id, $chapters[2]->id, $chapters[3]->id], $group->chapters->pluck('id')->all());
        $this->assertSame(Group::DEFAULT_DURATION_MINUTES, (int) $group->schedules->first()->duration_minutes);

        $scheduled = LiveClass::where('group_id', $group->id)->where('status', 'scheduled')->firstOrFail();
        $this->assertSame($chapters[1]->id, $scheduled->chapter_id);
        $this->assertSame(0, (int) $scheduled->chapter_index);
        $this->assertSame('2026-07-24 19:00:00', $scheduled->scheduled_for->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
    }

    public function test_assigned_coach_can_start(): void
    {
        [$coach, $group, $class] = $this->makeScheduledClass(minutesFromNow: 4);

        $this->actingAs($coach)
            ->post(route('coach.class.start', $class))
            ->assertRedirect(route('coach.class.live', $class));

        $this->assertDatabaseHas('live_classes', [
            'id' => $class->id,
            'status' => 'live',
        ]);
    }

    public function test_another_coach_cannot_start(): void
    {
        [$coach, $group, $class] = $this->makeScheduledClass(minutesFromNow: 4);
        $otherCoach = $this->makeUser('coach');

        $this->actingAs($otherCoach)
            ->post(route('coach.class.start', $class))
            ->assertForbidden();
    }

    public function test_assigned_student_can_join(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass();
        $student = $group->students()->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.class.join', $class))
            ->assertOk();

        $this->assertDatabaseHas('class_participants', [
            'live_class_id' => $class->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_unrelated_student_cannot_join(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass();
        $otherStudent = $this->makeUser('student');

        $this->actingAs($otherStudent)
            ->get(route('student.class.join', $class))
            ->assertForbidden();
    }

    public function test_coach_cannot_start_too_early(): void
    {
        [$coach, $group, $class] = $this->makeScheduledClass(minutesFromNow: 6);

        $this->actingAs($coach)
            ->from('/coach/classes')
            ->post(route('coach.class.start', $class))
            ->assertRedirect('/coach/classes')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('live_classes', [
            'id' => $class->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_chapter_completion_schedules_the_next_chapter(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass(scheduleSpecs: [
            ['day_of_week' => 5, 'start_time' => '19:00:00'],
            ['day_of_week' => 1, 'start_time' => '18:00:00'],
        ]);

        $this->actingAs($coach)
            ->post(route('coach.class.end', $class))
            ->assertRedirect(route('coach.homework'));

        $group->refresh();
        $this->assertSame(1, (int) $group->unlocked_chapter_index);
        $this->assertDatabaseHas('live_classes', [
            'id' => $class->id,
            'status' => 'completed',
            'completion_type' => 'manual',
            'chapter_completed' => 1,
        ]);

        $nextClass = LiveClass::where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_for')
            ->firstOrFail();

        $this->assertSame(1, (int) $nextClass->chapter_index);
        $this->assertSame($group->chapters()->wherePivot('sequence_order', 1)->value('chapters.id'), $nextClass->chapter_id);
    }

    public function test_chapter_four_cannot_be_skipped(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass(chapterIndex: 2);

        $this->actingAs($coach)
            ->from(route('coach.class.live', $class))
            ->post(route('coach.class.end', $class))
            ->assertStatus(422);

        $group->refresh();
        $class->refresh();

        $this->assertSame(0, (int) $group->unlocked_chapter_index);
        $this->assertSame('live', $class->status);
    }

    public function test_duplicate_end_request_advances_only_once(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass();

        $this->actingAs($coach)->post(route('coach.class.end', $class))->assertRedirect(route('coach.homework'));
        $this->actingAs($coach)->post(route('coach.class.end', $class))->assertRedirect(route('coach.homework'));

        $group->refresh();

        $this->assertSame(1, (int) $group->unlocked_chapter_index);
        $this->assertSame(1, Homework::where('source_class_id', $class->id)->count());
    }

    public function test_cancelled_class_does_not_advance(): void
    {
        [$coach, $group, $class] = $this->makeScheduledClass(minutesFromNow: 30);

        $class->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'schedules']));

        $group->refresh();
        $replacement = LiveClass::where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->firstOrFail();

        $this->assertSame(0, (int) $group->unlocked_chapter_index);
        $this->assertSame(0, (int) $replacement->chapter_index);
    }

    public function test_auto_end_marks_class_completed_without_advancing_chapter(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass(scheduleSpecs: [
            ['day_of_week' => 5, 'start_time' => '19:00:00'],
            ['day_of_week' => 1, 'start_time' => '18:00:00'],
        ]);

        $class->update([
            'scheduled_start_at' => now()->subMinutes(60),
            'scheduled_end_at' => now(),
            'actual_start_at' => now()->subMinutes(60),
            'duration_minutes' => 60,
        ]);

        Artisan::call('classes:auto-end-live');

        $group->refresh();
        $class->refresh();

        $this->assertSame('completed', $class->status);
        $this->assertSame('automatic', $class->completion_type);
        $this->assertFalse((bool) $class->chapter_completed);
        $this->assertSame(0, (int) $group->unlocked_chapter_index);

        $nextClass = LiveClass::where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_for')
            ->firstOrFail();

        $this->assertSame($class->chapter_id, $nextClass->chapter_id);
        $this->assertSame(0, (int) $nextClass->chapter_index);
    }

    public function test_multiple_weekly_slots_receive_sequential_chapters_in_chronological_order(): void
    {
        [$group, $chapters] = $this->makeGroupWithCurriculum(scheduleSpecs: [
            ['day_of_week' => 5, 'start_time' => '19:00:00'],
            ['day_of_week' => 1, 'start_time' => '18:00:00'],
        ]);

        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'schedules']));

        $scheduled = LiveClass::where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_for')
            ->get();

        $this->assertCount(4, $scheduled);
        $this->assertSame(0, (int) $scheduled[0]->chapter_index);
        $this->assertSame(1, (int) $scheduled[1]->chapter_index);
        $this->assertSame($chapters[0]->id, $scheduled[0]->chapter_id);
        $this->assertSame($chapters[1]->id, $scheduled[1]->chapter_id);
        $this->assertTrue($scheduled[0]->scheduled_for->lt($scheduled[1]->scheduled_for));
    }

    public function test_missed_class_is_cancelled_after_30_minutes_and_next_slot_keeps_same_chapter(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 27, 19, 29, 0, 'Asia/Kolkata'));

        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $admin = $this->makeUser('admin');
        $chapters = $this->makeChapters(4);

        $this->actingAs($admin)->post(route('admin.groups.store'), [
            'name' => 'Monday Thursday Group',
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'students' => [$student->id],
            'first_chapter_id' => $chapters[2]->id,
            'schedules' => [
                ['day' => 1, 'time' => '19:00'],
                ['day' => 4, 'time' => '19:00'],
            ],
        ])->assertRedirect(route('admin.groups'));

        $group = Group::with(['chapters', 'scheduleSlots'])->firstOrFail();

        Artisan::call('classes:generate-schedule');
        $mondaySchedule = $group->scheduleSlots()->where('day_of_week', 1)->firstOrFail();
        LiveClass::create([
            'name' => 'Missed Monday class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $mondaySchedule->id,
            'chapter_id' => $chapters[2]->id,
            'chapter_index' => 0,
            'status' => 'scheduled',
            'room_id' => (string) Str::uuid(),
            'scheduled_for' => Carbon::create(2026, 7, 27, 13, 30, 0, 'UTC'),
        ]);
        Carbon::setTestNow(Carbon::create(2026, 7, 27, 19, 31, 0, 'Asia/Kolkata'));
        Artisan::call('classes:auto-cancel-late');

        $cancelledMonday = LiveClass::where('group_id', $group->id)
            ->where('status', 'cancelled')
            ->orderBy('scheduled_for')
            ->firstOrFail();

        $thursday = LiveClass::where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_for')
            ->firstOrFail();

        $this->assertSame($chapters[2]->id, $cancelledMonday->chapter_id);
        $this->assertSame(0, (int) $group->fresh()->unlocked_chapter_index);
        $this->assertSame($chapters[2]->id, $thursday->chapter_id);
        $this->assertSame(0, (int) $thursday->chapter_index);

        Carbon::setTestNow(Carbon::create(2026, 7, 30, 19, 2, 0, 'Asia/Kolkata'));

        $this->actingAs($coach)
            ->post(route('coach.class.start', $thursday->fresh()))
            ->assertRedirect(route('coach.class.live', $thursday));

        $this->actingAs($coach)
            ->post(route('coach.class.end', $thursday->fresh()))
            ->assertRedirect(route('coach.homework'));

        $group->refresh();
        $nextMonday = LiveClass::where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_for')
            ->firstOrFail();

        $this->assertSame(1, (int) $group->unlocked_chapter_index);
        $this->assertSame($chapters[3]->id, $nextMonday->chapter_id);
        $this->assertSame(1, (int) $nextMonday->chapter_index);
    }

    public function test_schedule_edit_does_not_alter_ended_classes(): void
    {
        $admin = $this->makeUser('admin');
        [$group, $chapters] = $this->makeGroupWithCurriculum();

        $originalSchedule = $group->schedules()->firstOrFail();
        $ended = LiveClass::create([
            'name' => 'Ended class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $originalSchedule->id,
            'chapter_id' => $chapters[0]->id,
            'chapter_index' => 0,
            'status' => 'ended',
            'room_id' => (string) Str::uuid(),
            'scheduled_for' => now()->subWeek(),
            'started_at' => now()->subWeek(),
            'ended_at' => now()->subWeek()->addHour(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.groups.update', $group), [
                'name' => $group->name,
                'level' => $group->level,
                'timezone' => $group->timezone ?: 'Asia/Kolkata',
                'coach_id' => $group->coach_id,
                'schedules' => [
                    ['day' => 2, 'time' => '18:30', 'duration' => 60],
                ],
            ])
            ->assertRedirect();

        $ended->refresh();
        $group->refresh();

        $this->assertSame('ended', $ended->status);
        $this->assertSame($originalSchedule->id, $ended->group_schedule_id);
        $this->assertSame(0, (int) $ended->chapter_index);
        $this->assertDatabaseHas('group_schedules', [
            'id' => $originalSchedule->id,
            'is_active' => 0,
        ]);
    }

    protected function makeScheduledClass(int $minutesFromNow = 30): array
    {
        [$group, $chapters] = $this->makeGroupWithCurriculum();

        $class = LiveClass::create([
            'name' => 'Scheduled class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $group->schedules()->first()->id,
            'chapter_id' => $chapters[0]->id,
            'chapter_index' => 0,
            'status' => 'scheduled',
            'room_id' => (string) Str::uuid(),
            'scheduled_for' => now()->addMinutes($minutesFromNow),
        ]);

        return [$group->coach, $group, $class];
    }

    protected function makeLiveClass(array $scheduleSpecs = [['day_of_week' => 5, 'start_time' => '19:00:00']], int $chapterIndex = 0): array
    {
        [$group, $chapters] = $this->makeGroupWithCurriculum(scheduleSpecs: $scheduleSpecs);

        $class = LiveClass::create([
            'name' => 'Live class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $group->schedules()->first()->id,
            'chapter_id' => $chapters[$chapterIndex]->id,
            'chapter_index' => $chapterIndex,
            'status' => 'live',
            'room_id' => (string) Str::uuid(),
            'scheduled_for' => now()->subMinutes(2),
            'started_at' => now()->subMinute(),
        ]);

        return [$group->coach, $group, $class];
    }

    protected function makeGroupWithCurriculum(array $scheduleSpecs = [['day_of_week' => 5, 'start_time' => '19:00:00']]): array
    {
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $chapters = $this->makeChapters(4);

        $group = Group::create([
            'name' => 'Training Group',
            'level' => 'beginner',
            'timezone' => 'Asia/Kolkata',
            'coach_id' => $coach->id,
            'created_by' => $this->makeUser('admin')->id,
            'unlocked_chapter_index' => 0,
        ]);

        $group->students()->attach([$student->id]);
        $group->chapters()->sync(
            $chapters->mapWithKeys(fn (Chapter $chapter, int $index) => [$chapter->id => ['sequence_order' => $index]])->all()
        );

        foreach ($scheduleSpecs as $spec) {
            GroupSchedule::create([
                'group_id' => $group->id,
                'day_of_week' => $spec['day_of_week'],
                'start_time' => $spec['start_time'],
                'duration_minutes' => $spec['duration_minutes'] ?? 60,
                'is_active' => true,
            ]);
        }

        return [$group->fresh()->load(['coach', 'students', 'chapters', 'schedules']), $chapters];
    }

    protected function makeChapters(int $count, string $level = 'beginner'): Collection
    {
        $uploader = User::query()->where('role', 'admin')->first() ?? $this->makeUser('admin');

        return collect(range(1, $count))->map(function (int $offset) use ($level, $uploader) {
            return Chapter::create([
                'name' => 'Chapter '.$offset,
                'pgn' => '1. e4 e5 2. Nf3 Nc6',
                'level' => $level,
                'sequence_number' => Chapter::max('sequence_number') + 1,
                'uploaded_by' => $uploader->id,
                'sort_order' => $offset,
                'move_count' => 2,
            ]);
        });
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'name' => ucfirst($role).' User '.User::count(),
            'username' => $role.'_'.(User::count() + 1),
            'role' => $role,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }
}

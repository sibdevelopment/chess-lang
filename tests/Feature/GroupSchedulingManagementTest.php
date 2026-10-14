<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Group;
use App\Models\GroupChapterCompletion;
use App\Models\GroupSchedule;
use App\Models\LiveClass;
use App\Models\User;
use App\Support\ClassScheduler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GroupSchedulingManagementTest extends TestCase
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

    public function test_staff_can_create_group_if_permitted(): void
    {
        $staff = $this->makeUser('staff');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $chapters = $this->makeChapters(3);

        $this->actingAs($staff)
            ->post(route('staff.groups.store'), [
                'name' => 'Staff Batch',
                'level' => 'beginner',
                'coach_id' => $coach->id,
                'students' => [$student->id],
                'first_chapter_id' => $chapters[0]->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00'],
                ],
            ])
            ->assertRedirect(route('staff.groups'));

        $this->assertDatabaseHas('groups', ['name' => 'Staff Batch']);
        $group = Group::firstOrFail();
        $this->assertSame(Group::DEFAULT_TIMEZONE, $group->timezone);
        $this->assertSame(Group::DEFAULT_DURATION_MINUTES, (int) $group->scheduleSlots()->first()->duration_minutes);
    }

    public function test_group_can_be_created_without_students_and_uses_first_level_chapter_by_default(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $chapters = $this->makeChapters(3);

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Fast Create Batch',
                'level' => 'beginner',
                'coach_id' => $coach->id,
                'is_active' => 1,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00'],
                ],
            ])
            ->assertRedirect(route('admin.groups'));

        $group = Group::with('chapters')->where('name', 'Fast Create Batch')->firstOrFail();

        $this->assertSame(0, $group->students()->count());
        $this->assertSame($chapters[0]->id, $group->chapters->sortBy('pivot.sequence_order')->first()->id);
    }

    public function test_coach_and_student_cannot_create_admin_groups(): void
    {
        $chapter = $this->makeChapters(1)->first();

        foreach (['coach', 'student'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->post(route('admin.groups.store'), [
                    'name' => 'Blocked Group',
                    'level' => 'beginner',
                    'timezone' => 'Asia/Kolkata',
                    'first_chapter_id' => $chapter->id,
                    'schedules' => [
                        ['day' => 5, 'time' => '19:00', 'duration' => 60],
                    ],
                ])
                ->assertForbidden();
        }
    }

    public function test_group_rejects_non_coach_and_non_student_assignments(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        $coach = $this->makeUser('coach');
        $chapter = $this->makeChapters(1)->first();

        $this->actingAs($admin)
            ->from(route('admin.groups.create'))
            ->post(route('admin.groups.store'), [
                'name' => 'Bad Roles',
                'level' => 'beginner',
                'timezone' => 'Asia/Kolkata',
                'coach_id' => $staff->id,
                'students' => [$coach->id],
                'first_chapter_id' => $chapter->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00', 'duration' => 60],
                ],
            ])
            ->assertRedirect(route('admin.groups.create'))
            ->assertSessionHasErrors(['coach_id', 'students.0']);
    }

    public function test_duplicate_student_assignment_is_prevented(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $chapter = $this->makeChapters(1)->first();

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Unique Students',
                'level' => 'beginner',
                'timezone' => 'Asia/Kolkata',
                'coach_id' => $coach->id,
                'students' => [$student->id, $student->id],
                'first_chapter_id' => $chapter->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00', 'duration' => 60],
                ],
            ])
            ->assertRedirect(route('admin.groups'));

        $group = Group::firstOrFail();
        $this->assertSame(1, $group->students()->count());
    }

    public function test_start_chapter_must_belong_to_selected_level(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $chapter = $this->makeChapters(1, 'advanced')->first();

        $this->actingAs($admin)
            ->from(route('admin.groups.create'))
            ->post(route('admin.groups.store'), [
                'name' => 'Wrong Chapter',
                'level' => 'beginner',
                'timezone' => 'Asia/Kolkata',
                'coach_id' => $coach->id,
                'first_chapter_id' => $chapter->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00', 'duration' => 60],
                ],
            ])
            ->assertRedirect(route('admin.groups.create'))
            ->assertSessionHasErrors('first_chapter_id');
    }

    public function test_group_curriculum_starts_once_and_continues_across_future_levels(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $beginner = $this->makeChapters(3, 'beginner', [1, 2, 3]);
        $intermediate = $this->makeChapters(2, 'intermediate', [1, 2]);
        $advanced = $this->makeChapters(1, 'advanced', [1]);

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Sequence Group',
                'level' => 'beginner',
                'coach_id' => $coach->id,
                'students' => [$student->id],
                'first_chapter_id' => $beginner[1]->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00'],
                ],
            ])
            ->assertRedirect(route('admin.groups'));

        $group = Group::with('chapters')->where('name', 'Sequence Group')->firstOrFail();

        $this->assertSame(
            [
                $beginner[1]->id,
                $beginner[2]->id,
                $intermediate[0]->id,
                $intermediate[1]->id,
                $advanced[0]->id,
            ],
            $group->chapters->sortBy('pivot.sequence_order')->pluck('id')->values()->all()
        );
        $this->assertSame('beginner', $group->level);
        $this->assertSame($beginner[1]->id, $group->getCurrentChapter()->id);
        $this->assertSame($beginner[2]->id, $group->getUpcomingChapter()->id);
    }

    public function test_last_chapter_completion_promotes_group_to_the_next_level(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $beginner = $this->makeChapters(1, 'beginner', [1]);
        $intermediate = $this->makeChapters(1, 'intermediate', [1]);

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Promotion Group',
                'level' => 'beginner',
                'coach_id' => $coach->id,
                'students' => [$student->id],
                'first_chapter_id' => $beginner[0]->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00'],
                ],
            ])
            ->assertRedirect(route('admin.groups'));

        $group = Group::with(['chapters', 'scheduleSlots', 'students'])->where('name', 'Promotion Group')->firstOrFail();

        $liveClass = LiveClass::create([
            'name' => 'Promotion Group Live',
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'group_schedule_id' => $group->scheduleSlots()->first()->id,
            'chapter_id' => $beginner[0]->id,
            'chapter_index' => 0,
            'status' => 'live',
            'room_id' => (string) \Illuminate\Support\Str::uuid(),
            'scheduled_for' => now()->subMinutes(2),
            'started_at' => now()->subMinute(),
        ]);

        $this->actingAs($coach)
            ->post(route('coach.class.end', $liveClass))
            ->assertRedirect(route('coach.homework'));

        $group->refresh()->load('chapters');
        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'schedules', 'scheduleSlots']));

        $nextScheduled = LiveClass::where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_for')
            ->firstOrFail();

        $this->assertSame(1, (int) $group->unlocked_chapter_index);
        $this->assertSame('intermediate', $group->level);
        $this->assertSame($intermediate[0]->id, $group->getCurrentChapter()->id);
        $this->assertSame($intermediate[0]->id, $nextScheduled->chapter_id);
    }

    public function test_invalid_timezone_duplicate_slot_and_end_date_are_rejected_but_optional_fields_are_not_required(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $chapter = $this->makeChapters(1)->first();

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Defaults Batch',
                'level' => 'beginner',
                'coach_id' => $coach->id,
                'first_chapter_id' => $chapter->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00'],
                ],
            ])
            ->assertRedirect(route('admin.groups'));

        $group = Group::where('name', 'Defaults Batch')->firstOrFail();
        $this->assertSame(Group::DEFAULT_TIMEZONE, $group->timezone);
        $this->assertTrue($group->is_active);
        $this->assertNull($group->start_date);
        $this->assertNull($group->end_date);
        $this->assertSame(Group::DEFAULT_DURATION_MINUTES, (int) $group->scheduleSlots()->first()->duration_minutes);

        $this->actingAs($admin)
            ->from(route('admin.groups.create'))
            ->post(route('admin.groups.store'), [
                'name' => 'Bad Schedules',
                'level' => 'beginner',
                'timezone' => 'Bad/Timezone',
                'coach_id' => $coach->id,
                'start_date' => '2026-07-25',
                'end_date' => '2026-07-24',
                'first_chapter_id' => $chapter->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00'],
                    ['day' => 5, 'time' => '19:00'],
                ],
            ])
            ->assertRedirect(route('admin.groups.create'))
            ->assertSessionHasErrors(['timezone', 'end_date']);

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Overlap',
                'level' => 'beginner',
                'coach_id' => $coach->id,
                'first_chapter_id' => $chapter->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00', 'duration' => 90],
                    ['day' => 5, 'time' => '20:00', 'duration' => 60],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('schedules');
    }

    public function test_inactivating_group_detaches_students_without_deleting_group(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $chapter = $this->makeChapters(1)->first();

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Detach Batch',
                'level' => 'beginner',
                'coach_id' => $coach->id,
                'students' => [$student->id],
                'first_chapter_id' => $chapter->id,
                'is_active' => 1,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00'],
                ],
            ])
            ->assertRedirect(route('admin.groups'));

        $group = Group::where('name', 'Detach Batch')->firstOrFail();
        $this->assertSame(1, $group->students()->count());

        $this->actingAs($admin)
            ->put(route('admin.groups.update', $group), [
                'name' => 'Detach Batch',
                'level' => 'beginner',
                'coach_id' => $coach->id,
                'is_active' => 0,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00'],
                ],
            ])
            ->assertRedirect();

        $group->refresh();
        $this->assertFalse($group->is_active);
        $this->assertSame(0, $group->students()->count());
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Detach Batch']);
    }

    public function test_missed_class_command_observes_30_minute_deadline_and_is_idempotent(): void
    {
        [$group] = $this->makeGroupWithCurriculum(scheduleSpecs: [
            ['day_of_week' => 2, 'start_time' => '19:00:00', 'duration_minutes' => 60],
        ]);
        $chapter = $group->chapters()->orderByPivot('sequence_order')->firstOrFail();
        $schedule = $group->scheduleSlots()->firstOrFail();

        $class = LiveClass::create([
            'name' => 'Missable class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $schedule->id,
            'chapter_id' => $chapter->id,
            'chapter_index' => 0,
            'status' => 'scheduled',
            'room_id' => (string) \Illuminate\Support\Str::uuid(),
            'scheduled_for' => Carbon::create(2026, 7, 28, 13, 30, 0, 'UTC'),
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 28, 19, 29, 0, 'Asia/Kolkata'));
        Artisan::call('classes:auto-cancel-late');
        $this->assertSame('scheduled', $class->fresh()->status);

        Carbon::setTestNow(Carbon::create(2026, 7, 28, 19, 31, 0, 'Asia/Kolkata'));
        Artisan::call('classes:auto-cancel-late');
        Artisan::call('classes:auto-cancel-late');

        $class->refresh();
        $group->refresh();

        $this->assertSame('cancelled', $class->status);
        $this->assertNotNull($class->cancelled_at);
        $this->assertSame(0, (int) $group->unlocked_chapter_index);
        $this->assertSame(0, GroupChapterCompletion::where('group_id', $group->id)->count());
    }

    public function test_started_and_completed_classes_are_not_cancelled_by_the_missed_class_command(): void
    {
        [$group] = $this->makeGroupWithCurriculum();
        $chapter = $group->chapters()->orderByPivot('sequence_order')->firstOrFail();
        $schedule = $group->scheduleSlots()->firstOrFail();
        $scheduledFor = Carbon::create(2026, 7, 28, 13, 30, 0, 'UTC');

        $live = LiveClass::create([
            'name' => 'Started class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $schedule->id,
            'chapter_id' => $chapter->id,
            'chapter_index' => 0,
            'status' => 'live',
            'room_id' => (string) \Illuminate\Support\Str::uuid(),
            'scheduled_for' => $scheduledFor,
            'started_at' => $scheduledFor->copy()->addMinutes(2),
        ]);

        $ended = LiveClass::create([
            'name' => 'Completed class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $schedule->id,
            'chapter_id' => $chapter->id,
            'chapter_index' => 0,
            'status' => 'ended',
            'room_id' => (string) \Illuminate\Support\Str::uuid(),
            'scheduled_for' => $scheduledFor,
            'started_at' => $scheduledFor->copy()->addMinutes(2),
            'ended_at' => $scheduledFor->copy()->addHour(),
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 28, 20, 0, 0, 'Asia/Kolkata'));
        Artisan::call('classes:auto-cancel-late');

        $this->assertSame('live', $live->fresh()->status);
        $this->assertSame('ended', $ended->fresh()->status);
    }

    public function test_inactive_group_and_inactive_schedule_generate_no_future_classes(): void
    {
        [$group] = $this->makeGroupWithCurriculum(isActive: false);
        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'scheduleSlots']));
        $this->assertSame(0, LiveClass::where('group_id', $group->id)->where('status', 'scheduled')->count());

        [$group] = $this->makeGroupWithCurriculum();
        $schedule = $group->scheduleSlots()->firstOrFail();
        $schedule->update(['is_active' => false]);

        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'scheduleSlots']));
        $this->assertSame(0, LiveClass::where('group_id', $group->id)->where('status', 'scheduled')->count());
    }

    public function test_schedule_command_is_idempotent_and_stores_utc_timestamps(): void
    {
        [$group] = $this->makeGroupWithCurriculum();

        Artisan::call('classes:generate-schedule');
        Artisan::call('classes:generate-schedule');

        $scheduled = LiveClass::where('group_id', $group->id)->where('status', 'scheduled')->orderBy('scheduled_for')->get();
        $this->assertCount(3, $scheduled);
        $this->assertSame(3, $scheduled->map(fn ($class) => $class->group_schedule_id . '|' . $class->scheduled_for->format('Y-m-d H:i:s'))->unique()->count());
        $this->assertSame('2026-07-24 13:30:00', DB::table('live_classes')->where('id', $scheduled->first()->id)->value('scheduled_for'));
    }

    public function test_local_time_remains_correct_across_timezone_conversion_and_group_end_date_is_respected(): void
    {
        [$group] = $this->makeGroupWithCurriculum(
            timezone: 'America/New_York',
            startDate: '2026-07-23',
            endDate: '2026-07-24',
            scheduleSpecs: [
                ['day_of_week' => 5, 'start_time' => '18:00:00', 'duration_minutes' => 60],
            ],
        );

        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'scheduleSlots']));

        $scheduled = LiveClass::where('group_id', $group->id)->where('status', 'scheduled')->orderBy('scheduled_for')->get();
        $this->assertCount(1, $scheduled);
        $this->assertSame('18:00', $scheduled->first()->scheduled_for->copy()->timezone('America/New_York')->format('H:i'));
    }

    public function test_changing_coach_updates_only_future_unstarted_classes(): void
    {
        $admin = $this->makeUser('admin');
        [$group, $chapters] = $this->makeGroupWithCurriculum();
        $newCoach = $this->makeUser('coach');
        $schedule = $group->scheduleSlots()->firstOrFail();

        $ended = LiveClass::create([
            'name' => 'Ended class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $schedule->id,
            'chapter_id' => $chapters[0]->id,
            'chapter_index' => 0,
            'status' => 'ended',
            'room_id' => (string) \Illuminate\Support\Str::uuid(),
            'scheduled_for' => now()->subWeek(),
            'started_at' => now()->subWeek(),
            'ended_at' => now()->subWeek()->addHour(),
        ]);

        LiveClass::create([
            'name' => 'Future class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $schedule->id,
            'chapter_id' => $chapters[0]->id,
            'chapter_index' => 0,
            'status' => 'scheduled',
            'room_id' => (string) \Illuminate\Support\Str::uuid(),
            'scheduled_for' => now()->addWeek(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.groups.update', $group), [
                'name' => $group->name,
                'level' => $group->level,
                'timezone' => $group->timezone,
                'coach_id' => $newCoach->id,
                'schedules' => [
                    ['day' => 5, 'time' => '19:00', 'duration' => 60],
                ],
            ])
            ->assertRedirect();

        $ended->refresh();
        $future = LiveClass::where('group_id', $group->id)->where('status', 'scheduled')->firstOrFail();

        $this->assertSame($group->coach_id, $ended->coach_id);
        $this->assertSame($newCoach->id, $future->coach_id);
    }

    public function test_assigned_and_unassigned_coach_access_and_chapter_completion_rules(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass();
        $otherCoach = $this->makeUser('coach');

        $this->actingAs($coach)
            ->get(route('coach.groups.chapters', $group))
            ->assertOk();

        $this->actingAs($otherCoach)
            ->get(route('coach.groups.chapters', $group))
            ->assertForbidden();

        $this->actingAs($otherCoach)
            ->post(route('coach.class.end', $class))
            ->assertForbidden();
    }

    public function test_enrolled_and_unenrolled_student_access_rules_and_student_cannot_end_class(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass();
        $student = $group->students()->firstOrFail();
        $otherStudent = $this->makeUser('student');

        $this->actingAs($student)
            ->get(route('student.class.join', $class))
            ->assertOk();

        $this->actingAs($otherStudent)
            ->get(route('student.class.join', $class))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('coach.class.end', $class))
            ->assertForbidden();
    }

    public function test_group_begins_at_selected_starting_chapter_and_progresses_sequentially(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $chapters = $this->makeChapters(4);

        $this->actingAs($admin)->post(route('admin.groups.store'), [
            'name' => 'Sequence Group',
            'level' => 'beginner',
            'timezone' => 'Asia/Kolkata',
            'coach_id' => $coach->id,
            'students' => [$student->id],
            'first_chapter_id' => $chapters[1]->id,
            'schedules' => [
                ['day' => 5, 'time' => '19:00', 'duration' => 60],
            ],
        ])->assertRedirect(route('admin.groups'));

        $group = Group::with('chapters')->firstOrFail();
        $this->assertSame($chapters[1]->id, $group->getNextChapter()->id);
        $this->assertEquals([$chapters[1]->id, $chapters[2]->id, $chapters[3]->id], $group->chapters->pluck('id')->all());
    }

    public function test_completing_current_chapter_unlocks_next_with_sequence_gaps_and_final_completion_marks_curriculum_complete(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass(chapterSequences: [10, 20]);

        $this->actingAs($coach)
            ->post(route('coach.class.end', $class))
            ->assertRedirect(route('coach.homework'));

        $group->refresh();
        $this->assertSame(1, (int) $group->unlocked_chapter_index);
        $this->assertSame(1, GroupChapterCompletion::where('group_id', $group->id)->count());

        $nextScheduled = LiveClass::where('group_id', $group->id)->where('status', 'scheduled')->firstOrFail();
        $nextScheduled->update([
            'status' => 'live',
            'started_at' => now(),
        ]);

        $this->actingAs($coach)
            ->post(route('coach.class.end', $nextScheduled))
            ->assertRedirect(route('coach.homework'));

        $group->refresh();
        $this->assertTrue($group->hasFinishedLevel());
        $this->assertSame(0, LiveClass::where('group_id', $group->id)->where('status', 'scheduled')->count());
    }

    public function test_missing_sequence_consumes_a_slot_before_the_next_available_chapter_is_scheduled(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 29, 10, 0, 0, 'Asia/Kolkata'));

        [$group, $chapters] = $this->makeGroupWithCurriculum(
            scheduleSpecs: [
                ['day_of_week' => 1, 'start_time' => '19:00:00', 'duration_minutes' => 60],
                ['day_of_week' => 4, 'start_time' => '19:00:00', 'duration_minutes' => 60],
            ],
            chapterSequences: [1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
        );

        $group->update(['unlocked_chapter_index' => 9]);

        GroupChapterCompletion::create([
            'group_id' => $group->id,
            'chapter_id' => $chapters[8]->id,
            'completed_by' => $this->makeUser('admin')->id,
            'completed_at' => Carbon::create(2026, 7, 27, 20, 0, 0, 'Asia/Kolkata'),
        ]);

        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'scheduleSlots', 'schedules', 'chapterCompletions']));

        $scheduled = LiveClass::where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_for')
            ->get();

        $this->assertCount(10, $scheduled);
        $this->assertSame($chapters[9]->id, $scheduled[0]->chapter_id);
        $this->assertSame('2026-08-03 19:00:00', $scheduled[0]->scheduled_for->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
        $this->assertSame($chapters[10]->id, $scheduled[1]->chapter_id);
        $this->assertSame('2026-08-06 19:00:00', $scheduled[1]->scheduled_for->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
        $this->assertSame($chapters[18]->id, $scheduled[9]->chapter_id);
        $this->assertSame('2026-09-03 19:00:00', $scheduled[9]->scheduled_for->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
    }

    public function test_re_running_generation_and_completion_stays_idempotent(): void
    {
        [$coach, $group, $class] = $this->makeLiveClass();

        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'scheduleSlots']));
        ClassScheduler::syncForGroup($group->fresh()->load(['chapters', 'scheduleSlots']));

        $this->actingAs($coach)->post(route('coach.class.end', $class))->assertRedirect(route('coach.homework'));
        $this->actingAs($coach)->post(route('coach.class.end', $class))->assertRedirect(route('coach.homework'));

        $this->assertSame(1, GroupChapterCompletion::where('group_id', $group->id)->count());
    }

    protected function makeLiveClass(array $scheduleSpecs = [['day_of_week' => 5, 'start_time' => '19:00:00', 'duration_minutes' => 60]], array $chapterSequences = [1, 2, 3], int $chapterIndex = 0): array
    {
        [$group, $chapters] = $this->makeGroupWithCurriculum(scheduleSpecs: $scheduleSpecs, chapterSequences: $chapterSequences);

        $class = LiveClass::create([
            'name' => 'Live class',
            'coach_id' => $group->coach_id,
            'group_id' => $group->id,
            'group_schedule_id' => $group->scheduleSlots()->first()->id,
            'chapter_id' => $chapters[$chapterIndex]->id,
            'chapter_index' => $chapterIndex,
            'status' => 'live',
            'room_id' => (string) \Illuminate\Support\Str::uuid(),
            'scheduled_for' => now()->subMinutes(2),
            'started_at' => now()->subMinute(),
        ]);

        return [$group->coach, $group, $class];
    }

    protected function makeGroupWithCurriculum(
        array $scheduleSpecs = [['day_of_week' => 5, 'start_time' => '19:00:00', 'duration_minutes' => 60]],
        string $timezone = 'Asia/Kolkata',
        ?string $startDate = null,
        ?string $endDate = null,
        bool $isActive = true,
        array $chapterSequences = [1, 2, 3]
    ): array {
        $coach = $this->makeUser('coach');
        $student = $this->makeUser('student');
        $chapters = $this->makeChapters(count($chapterSequences), 'beginner', $chapterSequences);

        $group = Group::create([
            'name' => 'Training Group ' . Group::count(),
            'level' => 'beginner',
            'timezone' => $timezone,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => $isActive,
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
                'is_active' => $spec['is_active'] ?? true,
            ]);
        }

        return [$group->fresh()->load(['coach', 'students', 'chapters', 'scheduleSlots', 'schedules']), $chapters];
    }

    protected function makeChapters(int $count, string $level = 'beginner', ?array $sequenceNumbers = null): Collection
    {
        $uploader = User::query()->where('role', 'admin')->first() ?? $this->makeUser('admin');
        $sequenceNumbers = $sequenceNumbers ?: range(1, $count);
        $base = (int) (Chapter::max('sequence_number') ?? 0);

        return collect(range(1, $count))->map(function (int $offset) use ($level, $uploader, $sequenceNumbers, $base) {
            return Chapter::create([
                'name' => 'Chapter ' . $offset,
                'pgn' => '1. e4 e5 2. Nf3 Nc6',
                'level' => $level,
                'sequence_number' => $base + $sequenceNumbers[$offset - 1],
                'uploaded_by' => $uploader->id,
                'sort_order' => $offset,
                'move_count' => 2,
            ]);
        });
    }

    protected function makeUser(string $role): User
    {
        return User::factory()->create([
            'name' => ucfirst($role) . ' User ' . User::count(),
            'username' => $role . '_' . (User::count() + 1),
            'role' => $role,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }
}

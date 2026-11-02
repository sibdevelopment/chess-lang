<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupSchedule;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ErpSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp-sync.enabled' => true,
            'erp-sync.connection' => 'erp_test',
            'erp-sync.sync_passwords' => true,
            'database.connections.erp_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('erp_test');
        $this->createErpSchema();
        $this->seedErp();

        User::factory()->create([
            'name' => 'Local Admin',
            'username' => 'admin',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_dry_run_rolls_back_the_complete_sync(): void
    {
        $this->artisan('erp:sync', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run complete')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['erp_student_id' => 101]);
        $this->assertDatabaseMissing('users', ['erp_coach_id' => 201]);
        $this->assertDatabaseMissing('groups', ['erp_batch_id' => 301]);
    }

    public function test_sync_is_idempotent_and_maps_users_groups_schedules_and_memberships(): void
    {
        $this->artisan('erp:sync')->assertSuccessful();

        $student = User::where('erp_student_id', 101)->firstOrFail();
        $coach = User::where('erp_coach_id', 201)->firstOrFail();
        $group = Group::where('erp_batch_id', 301)->firstOrFail();

        $this->assertSame('student', $student->role);
        $this->assertSame('ARCHER1001', $student->username);
        $this->assertTrue(Hash::check('StudentSecret', $student->password));
        $this->assertSame('coach', $coach->role);
        $this->assertTrue(Hash::check('CoachSecret', $coach->password));
        $this->assertSame($coach->id, $group->coach_id);
        $this->assertTrue($group->students()->whereKey($student->id)->exists());

        $schedule = GroupSchedule::where('erp_batch_schedule_id', 401)->firstOrFail();
        $this->assertSame(1, $schedule->day_of_week);
        $this->assertSame(60, $schedule->duration_minutes);
        $this->assertTrue($schedule->is_active);

        $this->artisan('erp:sync')->assertSuccessful();

        $this->assertSame(1, User::where('erp_student_id', 101)->count());
        $this->assertSame(1, User::where('erp_coach_id', 201)->count());
        $this->assertSame(1, Group::where('erp_batch_id', 301)->count());
        $this->assertSame(1, GroupSchedule::where('erp_batch_schedule_id', 401)->count());
    }

    public function test_one_erp_account_can_sync_multiple_student_profiles(): void
    {
        DB::connection('erp_test')->table('students')->insert([
            'id' => 102, 'user_id' => 1, 'first_name' => 'Second', 'last_name' => 'Student',
            'student_id' => 'ARCHER1002', 'portal_password' => 'StudentSecretTwo',
            'email' => 'student@example.test', 'mobile' => '9000000003',
            'country' => 'INDIA', 'timezone' => 'Asia/Kolkata', 'status' => 'ACTIVE',
        ]);

        $this->artisan('erp:sync', ['--users-only' => true])->assertSuccessful();

        $this->assertSame(2, User::where('erp_user_id', 1)->where('role', 'student')->count());
        $this->assertDatabaseHas('users', ['erp_student_id' => 101, 'username' => 'ARCHER1001']);
        $this->assertDatabaseHas('users', ['erp_student_id' => 102, 'username' => 'ARCHER1002']);
    }

    private function createErpSchema(): void
    {
        $schema = Schema::connection('erp_test');
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('status')->nullable();
        });
        $schema->create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('student_id')->nullable();
            $table->string('portal_password')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('country')->nullable();
            $table->string('timezone')->nullable();
            $table->string('status')->nullable();
        });
        $schema->create('coachs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('portal_id')->nullable();
            $table->string('portal_password')->nullable();
            $table->string('country')->nullable();
            $table->string('status')->nullable();
        });
        $schema->create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        $schema->create('batchs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('coach_id')->nullable();
            $table->unsignedBigInteger('level_id')->nullable();
            $table->string('status')->nullable();
            $table->string('country')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
        $schema->create('batch_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->string('weekday')->nullable();
            $table->time('from_time')->nullable();
            $table->time('to_time')->nullable();
            $table->string('status')->nullable();
        });
        $schema->create('student_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('batch_id');
            $table->string('status')->nullable();
        });
    }

    private function seedErp(): void
    {
        DB::connection('erp_test')->table('users')->insert([
            ['id' => 1, 'first_name' => 'Test', 'last_name' => 'Student', 'email' => 'student@example.test', 'mobile' => '9000000001', 'status' => 'ACTIVE'],
            ['id' => 2, 'first_name' => 'Test', 'last_name' => 'Coach', 'email' => 'coach@example.test', 'mobile' => '9000000002', 'status' => 'ACTIVE'],
        ]);
        DB::connection('erp_test')->table('students')->insert([
            'id' => 101, 'user_id' => 1, 'first_name' => 'Test', 'last_name' => 'Student',
            'student_id' => 'ARCHER1001', 'portal_password' => 'StudentSecret',
            'email' => 'student@example.test', 'mobile' => '9000000001',
            'country' => 'INDIA', 'timezone' => 'Asia/Kolkata', 'status' => 'ACTIVE',
        ]);
        DB::connection('erp_test')->table('coachs')->insert([
            'id' => 201, 'user_id' => 2, 'portal_id' => 'COACH1001',
            'portal_password' => 'CoachSecret', 'country' => '["INDIA"]', 'status' => 'ACTIVE',
        ]);
        DB::connection('erp_test')->table('levels')->insert(['id' => 1, 'name' => 'Beginner']);
        DB::connection('erp_test')->table('batchs')->insert([
            'id' => 301, 'name' => 'ERP Test Batch', 'coach_id' => 201, 'level_id' => 1,
            'status' => 'ACTIVE', 'country' => '["INDIA"]', 'start_date' => '2026-11-01', 'end_date' => '2027-01-31',
        ]);
        DB::connection('erp_test')->table('batch_schedules')->insert([
            'id' => 401, 'batch_id' => 301, 'weekday' => 'Monday',
            'from_time' => '10:00:00', 'to_time' => '11:00:00', 'status' => 'ACTIVE',
        ]);
        DB::connection('erp_test')->table('student_batches')->insert([
            'id' => 501, 'student_id' => 101, 'batch_id' => 301, 'status' => 'ACTIVE',
        ]);
    }
}

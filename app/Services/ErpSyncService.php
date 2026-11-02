<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ErpSyncService
{
    private ConnectionInterface $erp;

    private array $report = [];

    public function sync(bool $dryRun = false, bool $usersOnly = false, bool $syncPasswords = false): array
    {
        $this->erp = DB::connection(config('erp-sync.connection', 'erp'));
        $this->report = [
            'students_created' => 0,
            'students_updated' => 0,
            'coaches_created' => 0,
            'coaches_updated' => 0,
            'groups_created' => 0,
            'groups_updated' => 0,
            'schedules_created' => 0,
            'schedules_updated' => 0,
            'memberships_synced' => 0,
            'conflicts' => [],
            'missing_passwords' => [],
        ];

        $destination = DB::connection();
        $destination->beginTransaction();

        try {
            $this->syncStudents($syncPasswords);
            $this->syncCoaches($syncPasswords);

            if (! $usersOnly) {
                $this->syncGroups();
            }

            if ($dryRun) {
                $destination->rollBack();
            } else {
                $destination->commit();
            }
        } catch (Throwable $exception) {
            if ($destination->transactionLevel() > 0) {
                $destination->rollBack();
            }

            throw $exception;
        }

        return $this->report;
    }

    private function syncStudents(bool $syncPasswords): void
    {
        $linkedIds = User::whereNotNull('erp_student_id')->pluck('erp_student_id');
        $rows = $this->erp->table('students as student')
            ->join('users as account', 'account.id', '=', 'student.user_id')
            ->select([
                'student.id as erp_student_id',
                'account.id as erp_user_id',
                'student.first_name',
                'student.last_name',
                'student.student_id as portal_id',
                'student.portal_password',
                'student.email',
                'student.mobile',
                'student.country',
                'student.timezone',
                'student.status as entity_status',
                'account.status as account_status',
            ])
            ->where(function ($query) use ($linkedIds) {
                $query->where('student.status', 'ACTIVE')
                    ->orWhereIn('student.id', $linkedIds);
            })
            ->orderBy('student.id')
            ->get();

        foreach ($rows as $row) {
            $username = trim((string) $row->portal_id);
            if ($username === '') {
                $this->conflict('student', $row->erp_student_id, 'missing portal username');

                continue;
            }

            $this->upsertUser($row, 'student', $username, $syncPasswords);
        }
    }

    private function syncCoaches(bool $syncPasswords): void
    {
        $linkedIds = User::whereNotNull('erp_coach_id')->pluck('erp_coach_id');
        $rows = $this->erp->table('coachs as coach')
            ->join('users as account', 'account.id', '=', 'coach.user_id')
            ->select([
                'coach.id as erp_coach_id',
                'account.id as erp_user_id',
                'account.first_name',
                'account.last_name',
                'coach.portal_id',
                'coach.portal_password',
                'account.email',
                'account.mobile',
                'coach.country',
                'coach.status as entity_status',
                'account.status as account_status',
            ])
            ->where(function ($query) use ($linkedIds) {
                $query->where('coach.status', 'ACTIVE')
                    ->orWhereIn('coach.id', $linkedIds);
            })
            ->orderBy('coach.id')
            ->get();

        foreach ($rows as $row) {
            $username = trim((string) ($row->portal_id ?: $row->email));
            if ($username === '') {
                $this->conflict('coach', $row->erp_coach_id, 'missing portal username and email');

                continue;
            }

            $this->upsertUser($row, 'coach', $username, $syncPasswords);
        }
    }

    private function upsertUser(object $row, string $role, string $username, bool $syncPasswords): void
    {
        $reportPrefix = $role === 'coach' ? 'coaches' : 'students';
        $erpRoleId = (int) ($role === 'student' ? $row->erp_student_id : $row->erp_coach_id);
        $erpColumn = $role === 'student' ? 'erp_student_id' : 'erp_coach_id';
        $user = User::where($erpColumn, $erpRoleId)->first();

        if (! $user) {
            $user = User::where('username', $username)->first();
        }

        if ($user && $user->role !== $role) {
            $this->conflict($role, $erpRoleId, "username {$username} belongs to {$user->role}");

            return;
        }

        $email = $this->availableEmail($row->email ?? null, $user?->id);
        $attributes = [
            'erp_user_id' => (int) $row->erp_user_id,
            $erpColumn => $erpRoleId,
            'managed_by_erp' => true,
            'name' => trim((string) $row->first_name.' '.(string) $row->last_name) ?: $username,
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'phone' => trim((string) ($row->mobile ?? '')) ?: null,
            'country' => $this->countryCode($row->country ?? null),
            'timezone' => $this->timezone($row->timezone ?? null),
            'is_active' => $this->isActive($row->entity_status ?? null, $row->account_status ?? null),
        ];

        $plainPassword = trim((string) ($row->portal_password ?? ''));
        if (! $user) {
            $attributes['password'] = $plainPassword !== '' && $syncPasswords
                ? Hash::make($plainPassword)
                : Hash::make(Str::random(48));
            $user = User::create($attributes);
            $this->report["{$reportPrefix}_created"]++;
        } else {
            if ($plainPassword !== '' && $syncPasswords && ! Hash::check($plainPassword, $user->password)) {
                $attributes['password'] = Hash::make($plainPassword);
            }
            $user->update($attributes);
            $this->report["{$reportPrefix}_updated"]++;
        }

        if ($plainPassword === '') {
            $this->report['missing_passwords'][] = "{$role}:{$erpRoleId}";
        }
    }

    private function syncGroups(): void
    {
        $adminId = User::where('role', 'admin')->value('id');
        if (! $adminId) {
            throw new RuntimeException('At least one local Chesslang admin is required before syncing ERP batches.');
        }

        $linkedIds = Group::whereNotNull('erp_batch_id')->pluck('erp_batch_id');
        $batches = $this->erp->table('batchs as batch')
            ->leftJoin('levels as level', 'level.id', '=', 'batch.level_id')
            ->select([
                'batch.id as erp_batch_id',
                'batch.name',
                'batch.coach_id as erp_coach_id',
                'batch.status',
                'batch.country',
                'batch.start_date',
                'batch.end_date',
                'level.name as level_name',
            ])
            ->where(function ($query) use ($linkedIds) {
                $query->where('batch.status', 'ACTIVE')
                    ->orWhereIn('batch.id', $linkedIds);
            })
            ->orderBy('batch.id')
            ->get();

        foreach ($batches as $batch) {
            $name = trim((string) $batch->name) ?: "ERP Batch {$batch->erp_batch_id}";
            $group = Group::where('erp_batch_id', $batch->erp_batch_id)->first();
            if (! $group) {
                $sameName = Group::where('name', $name)->get();
                if ($sameName->count() === 1 && ! $sameName->first()->erp_batch_id) {
                    $group = $sameName->first();
                } elseif ($sameName->isNotEmpty()) {
                    $this->conflict('batch', $batch->erp_batch_id, "group name {$name} is already in use");

                    continue;
                }
            }

            $coachId = User::where('erp_coach_id', $batch->erp_coach_id)->value('id');
            $attributes = [
                'erp_batch_id' => (int) $batch->erp_batch_id,
                'managed_by_erp' => true,
                'name' => $name,
                'country' => $this->firstCountry($batch->country),
                'timezone' => Group::DEFAULT_TIMEZONE,
                'start_date' => $batch->start_date,
                'end_date' => $batch->end_date,
                'level' => $this->level($batch->level_name),
                'coach_id' => $coachId,
                'created_by' => $group?->created_by ?: $adminId,
                'is_active' => strtoupper((string) $batch->status) === 'ACTIVE',
            ];

            if ($group) {
                $group->update($attributes);
                $this->report['groups_updated']++;
            } else {
                $group = Group::create($attributes);
                $this->report['groups_created']++;
            }

            $this->syncSchedules($group);
            $this->syncMemberships($group);
        }
    }

    private function syncSchedules(Group $group): void
    {
        $linkedIds = GroupSchedule::where('group_id', $group->id)
            ->whereNotNull('erp_batch_schedule_id')
            ->pluck('erp_batch_schedule_id');
        $rows = $this->erp->table('batch_schedules')
            ->where('batch_id', $group->erp_batch_id)
            ->where(function ($query) use ($linkedIds) {
                $query->where('status', 'ACTIVE')
                    ->orWhereIn('id', $linkedIds);
            })
            ->orderBy('id')
            ->get(['id', 'weekday', 'from_time', 'to_time', 'status']);
        $seen = [];

        foreach ($rows as $row) {
            $day = $this->dayOfWeek($row->weekday);
            if ($day === null || ! $row->from_time) {
                $this->conflict('schedule', $row->id, 'invalid weekday or start time');

                continue;
            }

            $schedule = GroupSchedule::where('erp_batch_schedule_id', $row->id)->first();
            $attributes = [
                'group_id' => $group->id,
                'erp_batch_schedule_id' => (int) $row->id,
                'managed_by_erp' => true,
                'day_of_week' => $day,
                'start_time' => $row->from_time,
                'duration_minutes' => $this->duration($row->from_time, $row->to_time),
                'is_active' => strtoupper((string) $row->status) === 'ACTIVE',
            ];

            if ($schedule) {
                $schedule->update($attributes);
                $this->report['schedules_updated']++;
            } else {
                GroupSchedule::create($attributes);
                $this->report['schedules_created']++;
            }
            $seen[] = (int) $row->id;
        }

        GroupSchedule::where('group_id', $group->id)
            ->where('managed_by_erp', true)
            ->when($seen !== [], fn ($query) => $query->whereNotIn('erp_batch_schedule_id', $seen))
            ->update(['is_active' => false]);
    }

    private function syncMemberships(Group $group): void
    {
        $erpStudentIds = $this->erp->table('student_batches')
            ->where('batch_id', $group->erp_batch_id)
            ->where('status', 'ACTIVE')
            ->pluck('student_id');
        $studentIds = User::whereIn('erp_student_id', $erpStudentIds)->pluck('id')->all();

        $group->students()->sync($studentIds);
        $this->report['memberships_synced'] += count($studentIds);
    }

    private function availableEmail(mixed $email, ?int $currentUserId): ?string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $used = User::where('email', $email)
            ->when($currentUserId, fn ($query) => $query->whereKeyNot($currentUserId))
            ->exists();

        return $used ? null : $email;
    }

    private function isActive(mixed $entityStatus, mixed $accountStatus): bool
    {
        return strtoupper((string) $entityStatus) === 'ACTIVE'
            && strtoupper((string) $accountStatus) !== 'INACTIVE';
    }

    private function timezone(mixed $timezone): string
    {
        $timezone = trim((string) $timezone);

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : Group::DEFAULT_TIMEZONE;
    }

    private function countryCode(mixed $country): ?string
    {
        $country = strtoupper(trim((string) $this->firstCountry($country)));

        return match ($country) {
            'INDIA', 'IND' => 'IND',
            'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA' => 'USA',
            'CANADA', 'CA' => 'CA',
            'UK', 'UNITED KINGDOM' => 'UK',
            'EU', 'EUROPE', 'EUROPEAN UNION' => 'EU',
            'SINGAPORE', 'SG' => 'SG',
            'AUSTRALIA', 'AUS' => 'AUS',
            'NEW ZEALAND', 'NZ' => 'NZ',
            'SOUTH AFRICA', 'ZA' => 'ZA',
            'UAE', 'UNITED ARAB EMIRATES', 'QATAR', 'BAHRAIN', 'KUWAIT', 'OMAN', 'SAUDI ARABIA' => 'ME',
            default => $country !== '' ? $country : null,
        };
    }

    private function firstCountry(mixed $country): ?string
    {
        if (is_string($country)) {
            $decoded = json_decode($country, true);
            if (is_array($decoded)) {
                $country = $decoded;
            }
        }

        if (is_array($country)) {
            return isset($country[0]) ? trim((string) $country[0]) : null;
        }

        $country = trim((string) $country);

        return $country !== '' ? $country : null;
    }

    private function level(mixed $level): string
    {
        $level = strtolower(trim((string) $level));

        return match (true) {
            str_contains($level, 'expert') => 'expert',
            str_contains($level, 'advanced') => 'advanced',
            str_contains($level, 'intermediate') => 'intermediate',
            default => 'beginner',
        };
    }

    private function dayOfWeek(mixed $weekday): ?int
    {
        $days = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        return $days[strtolower(trim((string) $weekday))] ?? null;
    }

    private function duration(mixed $from, mixed $to): int
    {
        if (! $to) {
            return Group::DEFAULT_DURATION_MINUTES;
        }

        $start = Carbon::createFromFormat('H:i:s', (string) $from);
        $end = Carbon::createFromFormat('H:i:s', (string) $to);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return max(1, (int) $start->diffInMinutes($end));
    }

    private function conflict(string $type, int|string $id, string $reason): void
    {
        $this->report['conflicts'][] = "{$type}:{$id} {$reason}";
    }
}

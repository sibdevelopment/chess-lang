<?php

namespace App\Console\Commands;

use App\Models\Chapter;
use App\Models\ClassChatMessage;
use App\Models\ClassParticipant;
use App\Models\ClassQuestion;
use App\Models\Group;
use App\Models\LiveClass;
use App\Models\User;
use App\Support\LiveClassChess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrepareLiveClassDemo extends Command
{
    protected $signature = 'demo:live-class {--students=1 : Number of demo students to place in the class}';

    protected $description = 'Prepare a one-coach live classroom with configurable demo students for local QA';

    public function handle(LiveClassChess $chess): int
    {
        $studentCount = max(1, min(10, (int) $this->option('students')));

        $result = DB::transaction(function () use ($chess, $studentCount) {
            $creator = User::query()->where('role', 'admin')->first()
                ?? User::query()->where('role', 'staff')->firstOrFail();

            $students = User::query()
                ->where('role', 'student')
                ->orderBy('id')
                ->limit($studentCount)
                ->get();

            if ($students->count() !== $studentCount) {
                throw new \RuntimeException("The demo needs {$studentCount} student accounts, but only {$students->count()} are available.");
            }

            foreach ($students as $student) {
                $student->forceFill([
                    'country' => $student->country ?: 'IND',
                    'is_active' => true,
                    'password' => Hash::make('ARCHERCHESS'),
                ])->save();
            }

            $coach = User::query()->updateOrCreate(
                ['username' => 'ARCHERCOACHDEMO'],
                [
                    'name' => 'Demo Coach',
                    'password' => Hash::make('Demo@123'),
                    'role' => 'coach',
                    'is_active' => true,
                ],
            );

            $chapter = Chapter::query()
                ->where('level', 'beginner')
                ->orderedForLevel()
                ->firstOrFail();

            $group = Group::query()->updateOrCreate(
                ['name' => 'DEMO - Live Classroom QA'],
                [
                    'country' => 'IND',
                    'timezone' => 'Asia/Kolkata',
                    'start_date' => today(),
                    'end_date' => today()->addMonth(),
                    'level' => 'beginner',
                    'coach_id' => $coach->id,
                    'created_by' => $creator->id,
                    'unlocked_chapter_index' => 0,
                    'description' => "Local one-coach, {$studentCount}-student demo for board, chat, curriculum puzzle and leaderboard QA.",
                    'is_active' => true,
                ],
            );

            // This changes only the dedicated demo group. Other batch
            // memberships remain untouched.
            $group->students()->sync($students->pluck('id'));
            $group->chapters()->sync([$chapter->id => ['sequence_order' => 0]]);

            $positionFen = '8/8/8/8/8/8/8/R6R w - - 0 1';
            $solution = $chess->normalizeSolution($positionFen, ['a1-a5']);
            $now = now()->utc();

            $liveClass = LiveClass::query()->updateOrCreate(
                ['room_id' => 'archer-live-class-demo'],
                [
                    'name' => 'LIVE DEMO — Curriculum Puzzle & Sync',
                    'coach_id' => $coach->id,
                    'group_id' => $group->id,
                    'chapter_id' => $chapter->id,
                    'chapter_index' => 0,
                    'status' => 'live',
                    'current_move_index' => 0,
                    'board_state' => $positionFen,
                    'arrows' => json_encode([]),
                    'highlights' => json_encode([]),
                    'students_joined' => 0,
                    'scheduled_for' => $now,
                    'started_at' => $now,
                    'ended_at' => null,
                    'scheduled_start_at' => $now,
                    'scheduled_end_at' => $now->copy()->addMinutes(60),
                    'actual_start_at' => $now,
                    'actual_end_at' => null,
                    'duration_minutes' => 60,
                    'completion_type' => null,
                    'chapter_completed' => false,
                ],
            );

            ClassQuestion::query()->where('live_class_id', $liveClass->id)->delete();
            ClassParticipant::query()->where('live_class_id', $liveClass->id)->delete();
            ClassChatMessage::query()->where('live_class_id', $liveClass->id)->delete();

            $question = ClassQuestion::query()->create([
                'live_class_id' => $liveClass->id,
                'coach_id' => $coach->id,
                'position_fen' => $positionFen,
                'expected_moves' => $solution,
                'arrows' => [],
                'highlights' => [],
                'text' => 'Curriculum demo: move the rook from a1 to a5.',
                'is_active' => true,
            ]);

            return compact('students', 'coach', 'group', 'chapter', 'liveClass', 'question');
        }, 5);

        $base = rtrim((string) config('app.url'), '/');
        $classId = $result['liveClass']->id;

        $this->newLine();
        $this->info('Live classroom demo is ready.');
        $rows = [
            ['Coach', $result['coach']->username, 'Demo@123', "{$base}/coach/classes/{$classId}/live"],
        ];
        foreach ($result['students'] as $index => $student) {
            $rows[] = ['Student '.($index + 1), $student->username, 'ARCHERCHESS', "{$base}/student/classes/{$classId}/join"];
        }

        $this->table(['Role', 'Username', 'Password', 'Open'], $rows);
        $this->line('Group: '.$result['group']->name.' ('.$result['students']->count().' students)');
        $this->line('Curriculum: '.$result['chapter']->name);
        $this->line('Active answer: a1 → a5 (+1 point)');

        return self::SUCCESS;
    }
}

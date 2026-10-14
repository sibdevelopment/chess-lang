<?php

namespace Tests\Feature;

use App\Events\LiveClassChatMessageSent;
use App\Events\LiveClassQuestionChanged;
use App\Models\Chapter;
use App\Models\ClassQuestion;
use App\Models\Group;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LiveClassRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    public function test_student_move_is_checked_exactly_and_browser_fen_is_never_trusted(): void
    {
        Event::fake([LiveClassQuestionChanged::class]);
        [$class, $question, , $student] = $this->makeLiveClass();

        $this->actingAs($student)
            ->postJson(route('student.class.question.move', [$class, $question]), [
                'from' => 'd2',
                'to' => 'e4',
                'resulting_fen' => 'malicious fen',
            ])
            ->assertOk()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('correct_count', 0)
            ->assertJsonPath('wrong_count', 1)
            ->assertJsonPath('next_fen', self::START_FEN);

        $response = $this->actingAs($student)
            ->postJson(route('student.class.question.move', [$class, $question]), [
                'from' => 'e2',
                'to' => 'e4',
                'resulting_fen' => 'malicious fen',
            ])
            ->assertOk()
            ->assertJsonPath('correct', true)
            ->assertJsonPath('completed', true)
            ->assertJsonPath('correct_count', 1);

        $this->assertSame(
            'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1',
            $response->json('next_fen'),
        );

        $this->actingAs($student)
            ->getJson(route('student.class.leaderboard', $class))
            ->assertOk()
            ->assertJsonPath('students.0.total_points', 1)
            ->assertJsonPath('students.0.total_wrong', 1)
            ->assertJsonPath('students.0.questions_solved', 1);

        Event::assertDispatched(LiveClassQuestionChanged::class, fn ($event) =>
            $event->action === 'attempted' && $event->studentId === $student->id
        );
    }

    public function test_coach_solution_must_be_a_legal_line_and_realtime_question_event_is_sent(): void
    {
        Event::fake([LiveClassQuestionChanged::class]);
        [$class, , $coach] = $this->makeLiveClass(createQuestion: false);

        $this->actingAs($coach)
            ->postJson(route('coach.class.question.store', $class), [
                'position_fen' => self::START_FEN,
                'expected_moves' => ['e2-e5'],
                'text' => 'Illegal line',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_moves');

        $this->actingAs($coach)
            ->postJson(route('coach.class.question.store', $class), [
                'position_fen' => self::START_FEN,
                'expected_moves' => ['e2-e4', 'e7-e5'],
                'text' => 'Play the opening line',
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_questions', [
            'live_class_id' => $class->id,
            'text' => 'Play the opening line',
            'is_active' => true,
        ]);
        Event::assertDispatched(LiveClassQuestionChanged::class, fn ($event) => $event->action === 'started');
    }

    public function test_chat_message_persists_and_emits_realtime_payload(): void
    {
        Event::fake([LiveClassChatMessageSent::class]);
        [$class, , , $student] = $this->makeLiveClass();

        $this->actingAs($student)
            ->postJson(route('student.class.chat.send', $class), ['body' => 'Hello coach'])
            ->assertOk()
            ->assertJsonPath('message.body', 'Hello coach');

        $this->assertDatabaseHas('class_chat_messages', [
            'live_class_id' => $class->id,
            'user_id' => $student->id,
            'body' => 'Hello coach',
        ]);
        Event::assertDispatched(LiveClassChatMessageSent::class, fn ($event) =>
            $event->message['body'] === 'Hello coach'
        );
    }

    public function test_student_login_lands_on_classes_instead_of_removed_journey_dashboard(): void
    {
        $student = $this->makeUser('student', 'student-landing');

        $this->assertSame('/student/classes', $student->landingPath());
        $this->assertSame('student.classes', $student->landingRouteName());
    }

    /** @return array{LiveClass, ClassQuestion|null, User, User} */
    private function makeLiveClass(bool $createQuestion = true): array
    {
        $admin = $this->makeUser('admin', 'admin-live');
        $coach = $this->makeUser('coach', 'coach-live');
        $student = $this->makeUser('student', 'student-live');

        $group = Group::create([
            'name' => 'Live QA Group',
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'created_by' => $admin->id,
            'unlocked_chapter_index' => 0,
            'is_active' => true,
        ]);
        $group->students()->attach($student->id);

        $chapter = Chapter::create([
            'name' => 'Live QA Chapter',
            'pgn' => '[Event "Opening"]\n\n1. e4 e5',
            'level' => 'beginner',
            'sequence_number' => 1,
            'uploaded_by' => $admin->id,
        ]);
        $group->chapters()->attach($chapter->id, ['sequence_order' => 0]);

        $class = LiveClass::create([
            'name' => 'Live QA Class',
            'coach_id' => $coach->id,
            'group_id' => $group->id,
            'chapter_id' => $chapter->id,
            'chapter_index' => 0,
            'status' => 'live',
            'room_id' => 'live-qa-room',
            'board_state' => self::START_FEN,
            'scheduled_for' => now(),
            'started_at' => now(),
        ]);

        $question = $createQuestion ? ClassQuestion::create([
            'live_class_id' => $class->id,
            'coach_id' => $coach->id,
            'position_fen' => self::START_FEN,
            'expected_moves' => ['e2-e4'],
            'text' => 'Find the best move',
            'is_active' => true,
        ]) : null;

        return [$class, $question, $coach, $student];
    }

    private function makeUser(string $role, string $username): User
    {
        return User::create([
            'name' => ucfirst($role).' Live User',
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ArcherActivityEvent;
use App\Models\ArcherBadgeAward;
use App\Models\ArcherMission;
use App\Models\ArcherMissionProgress;
use App\Models\ArcherStarBalance;
use App\Models\ArcherStarTransaction;
use App\Models\Chapter;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\StudentChessRating;
use App\Models\StudentChessRatingPeriod;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Services\ArcherJourney\ArcherJourneyEngine;
use App\Services\ArcherJourney\ArcherLmsEventBridge;
use App\Services\ArcherJourney\FideRatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArcherJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_lms_event_awards_stars_and_badge_only_once(): void
    {
        $student = $this->user('student');
        $engine = app(ArcherJourneyEngine::class);

        $engine->record(ArcherJourneyEngine::CLASS_ATTENDED, $student, 'class', ['scope_key' => 88], eventKey: 'class_attended:test:88');
        $engine->record(ArcherJourneyEngine::CLASS_ATTENDED, $student, 'class', ['scope_key' => 88], eventKey: 'class_attended:test:88');

        $this->assertSame(1, ArcherActivityEvent::where('event_key', 'class_attended:test:88')->count());
        $this->assertSame(1, ArcherStarTransaction::where('user_id', $student->id)->where('stars', 5)->count());
        $this->assertSame(5, ArcherStarBalance::findOrFail($student->id)->lifetime_stars);
        $this->assertSame(1, ArcherBadgeAward::where('user_id', $student->id)->count());
    }

    public function test_puzzle_reward_respects_daily_limit(): void
    {
        $student = $this->user('student');
        $engine = app(ArcherJourneyEngine::class);

        foreach (range(1, 4) as $puzzleId) {
            $engine->record(
                ArcherJourneyEngine::PUZZLE_COMPLETED,
                $student,
                'puzzle',
                ['scope_key' => $puzzleId],
                eventKey: 'puzzle_completed:test:'.$puzzleId,
            );
        }

        $this->assertSame(3, ArcherStarTransaction::where('user_id', $student->id)->where('stars', 3)->count());
        $this->assertSame(9, ArcherStarBalance::findOrFail($student->id)->lifetime_stars);
    }

    public function test_mission_progress_and_reward_are_processed_automatically(): void
    {
        $student = $this->user('student');
        ArcherMission::query()->update(['is_active' => false]);
        $mission = ArcherMission::create([
            'name' => 'Test Homework Mission',
            'starts_at' => now()->utc()->subHour(),
            'ends_at' => now()->utc()->addHour(),
            'criteria_event_type' => ArcherJourneyEngine::HOMEWORK_COMPLETED,
            'target' => 1,
            'reward_stars' => 15,
            'is_active' => true,
        ]);

        app(ArcherJourneyEngine::class)->record(
            ArcherJourneyEngine::HOMEWORK_COMPLETED,
            $student,
            'homework',
            ['scope_key' => 55],
            eventKey: 'homework_completed:test:55',
        );

        $progress = ArcherMissionProgress::where('mission_id', $mission->id)->where('user_id', $student->id)->firstOrFail();
        $this->assertSame('completed', $progress->status);
        $this->assertNotNull($progress->rewarded_at);
        $this->assertSame(20, ArcherStarBalance::findOrFail($student->id)->lifetime_stars);
    }

    public function test_homework_bridge_creates_one_completion_event(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('student');
        $chapterId = Chapter::query()->insertGetId([
            'name' => 'Tactics', 'pgn' => '*', 'level' => 'beginner', 'sequence_number' => 1,
            'uploaded_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $homework = Homework::create([
            'title' => 'Tactics homework', 'chapter_id' => $chapterId, 'assigned_by' => $admin->id,
        ]);
        $submission = HomeworkSubmission::create([
            'homework_id' => $homework->id, 'user_id' => $student->id, 'status' => 'done', 'submitted_at' => now(),
        ]);

        $bridge = app(ArcherLmsEventBridge::class);
        $bridge->homeworkCompleted($submission);
        $bridge->homeworkCompleted($submission);

        $this->assertSame(1, ArcherActivityEvent::where('event_key', 'homework_completed:'.$student->id.':'.$homework->id)->count());
        $this->assertDatabaseHas('archer_star_transactions', [
            'user_id' => $student->id,
            'reward_rule_id' => DB::table('archer_reward_rules')->where('code', 'homework_completed')->value('id'),
            'stars' => 5,
        ]);
        $this->assertDatabaseHas('archer_badge_awards', [
            'user_id' => $student->id,
            'badge_id' => DB::table('archer_badges')->where('code', 'homework_hero')->value('id'),
        ]);
    }

    public function test_fide_style_tournament_rating_is_separate_and_idempotent(): void
    {
        $admin = $this->user('admin');
        $white = $this->user('student');
        $black = $this->user('student');
        foreach ([$white, $black] as $student) {
            StudentChessRating::create([
                'user_id' => $student->id, 'rating_type' => 'rapid', 'initial_rating' => 1500,
                'rating' => 1500, 'peak_rating' => 1500, 'games_played' => 0,
                'is_provisional' => true, 'source' => 'erp',
            ]);
        }
        $tournament = Tournament::create([
            'name' => 'Rated Rapid', 'format' => 'rapid', 'time_control' => '15+10', 'rounds' => 1,
            'minimum_players' => 2, 'scheduled_at' => now()->subHour(), 'tournament_timezone' => 'UTC',
            'audience_type' => Tournament::AUDIENCE_ALL_STUDENTS,
            'status' => Tournament::STATUS_COMPLETED, 'current_round' => 1, 'completed_at' => now(),
            'created_by' => $admin->id,
        ]);
        foreach ([$white, $black] as $student) {
            TournamentParticipant::create(['tournament_id' => $tournament->id, 'user_id' => $student->id]);
        }
        TournamentPairing::create([
            'tournament_id' => $tournament->id, 'round' => 1, 'board' => 1,
            'white_user_id' => $white->id, 'black_user_id' => $black->id,
            'result' => '1-0', 'winner_user_id' => $white->id, 'move_count' => 2,
            'moves_pgn' => 'e4 e5', 'status' => 'completed', 'completed_at' => now(),
            'white_clock_ms' => 0, 'black_clock_ms' => 0,
        ]);

        $service = app(FideRatingService::class);
        $service->processTournament($tournament);
        $service->processTournament($tournament);

        $this->assertSame(1520, StudentChessRating::where('user_id', $white->id)->where('rating_type', 'rapid')->value('rating'));
        $this->assertSame(1480, StudentChessRating::where('user_id', $black->id)->where('rating_type', 'rapid')->value('rating'));
        $this->assertSame(2, StudentChessRatingPeriod::where('tournament_id', $tournament->id)->count());
        $this->assertSame(0.5, $service->expectedScore(1500, 1500));
        $this->assertSame(0.92, $service->expectedScore(1900, 1500));
        $this->assertSame(5, ArcherStarBalance::findOrFail($white->id)->lifetime_stars);
    }

    public function test_tournament_participation_win_and_first_checkmate_reward_automatically(): void
    {
        $admin = $this->user('admin');
        $white = $this->user('student');
        $black = $this->user('student');
        $tournament = Tournament::create([
            'name' => 'Automatic Rewards Cup', 'format' => 'blitz', 'time_control' => '5+3', 'rounds' => 1,
            'minimum_players' => 2, 'scheduled_at' => now(), 'tournament_timezone' => 'UTC',
            'audience_type' => Tournament::AUDIENCE_ALL_STUDENTS,
            'status' => Tournament::STATUS_LIVE, 'current_round' => 1,
            'created_by' => $admin->id,
        ]);
        foreach ([$white, $black] as $student) {
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'user_id' => $student->id,
            ]);
        }

        $bridge = app(ArcherLmsEventBridge::class);
        $bridge->tournamentStarted($tournament);
        $pairing = TournamentPairing::create([
            'tournament_id' => $tournament->id, 'round' => 1, 'board' => 1,
            'white_user_id' => $white->id, 'black_user_id' => $black->id,
            'result' => '1-0', 'winner_user_id' => $white->id, 'move_count' => 7,
            'moves_pgn' => 'e4 e5 Qh5 Nc6 Bc4 Nf6 Qxf7#', 'status' => 'completed',
            'current_fen' => 'r1bqkb1r/pppp1Qpp/2n2n2/4p3/2B1P3/8/PPPP1PPP/RNB1K1NR b KQkq - 0 4',
            'completed_at' => now(), 'white_clock_ms' => 0, 'black_clock_ms' => 0,
        ]);

        $bridge->tournamentGameCompleted($pairing);
        $bridge->tournamentGameCompleted($pairing);

        $this->assertSame(20, ArcherStarBalance::findOrFail($white->id)->lifetime_stars);
        $this->assertSame(10, ArcherStarBalance::findOrFail($black->id)->lifetime_stars);
        $this->assertDatabaseHas('archer_badge_awards', [
            'user_id' => $white->id,
            'badge_id' => DB::table('archer_badges')->where('code', 'first_checkmate')->value('id'),
        ]);
        $this->assertSame(1, ArcherActivityEvent::where('event_key', 'tournament_checkmate:'.$white->id.':'.$pairing->id)->count());
    }

    public function test_archer_pages_render_and_are_role_protected(): void
    {
        $admin = $this->user('admin');
        $coach = $this->user('coach');
        $student = $this->user('student');
        $group = Group::create([
            'name' => 'Archer Batch', 'level' => 'beginner', 'coach_id' => $coach->id,
            'created_by' => $admin->id, 'unlocked_chapter_index' => 0, 'is_active' => true,
        ]);
        $group->students()->attach($student->id);

        $this->actingAs($student)->get(route('student.archer-journey'))->assertOk()->assertSee('My Archer Journey');
        $this->actingAs($student)->get(route('student.archer-journey', ['tab' => 'passport']))->assertOk()->assertSee('Archer Passport');
        $this->actingAs($student)->get(route('student.archer-journey', ['tab' => 'leaderboards']))->assertOk()->assertSee('Leaderboards');
        foreach (['overview', 'rewards', 'levels', 'badges', 'missions', 'challenge', 'ratings', 'houses', 'audit'] as $tab) {
            $this->actingAs($admin)->get(route('admin.archer-journey.index', ['tab' => $tab]))->assertOk()->assertSee('Archer Gamification Admin');
        }
        $this->actingAs($student)->get(route('admin.archer-journey.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.archer-discovery.index'))->assertNotFound();
        $this->actingAs($coach)->get(route('coach.archer-discovery.index'))->assertNotFound();
    }

    protected function user(string $role): User
    {
        static $sequence = 1;

        return User::create([
            'name' => ucfirst($role).' Archer '.$sequence,
            'username' => $role.'_archer_'.$sequence++,
            'password' => bcrypt('secret123'),
            'role' => $role,
            'is_active' => true,
            'country' => 'IND',
            'timezone' => 'Asia/Kolkata',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Support\TournamentScheduler;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class TournamentBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 7, 28, 18, 55, 0, 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_create_tournament(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->post(route('admin.tournaments.store'), $this->tournamentPayload())
            ->assertRedirect(route('admin.tournaments.index'));

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Summer Cup',
            'event_series' => 'standard',
            'status' => Tournament::STATUS_UPCOMING,
            'minimum_players' => 4,
        ]);
    }

    public function test_staff_can_create_and_edit_tournament(): void
    {
        $staff = $this->makeUser('staff');
        $tournament = $this->makeTournament(createdBy: $staff->id);

        $this->actingAs($staff)
            ->put(route('staff.tournaments.update', $tournament), array_merge($this->tournamentPayload(), [
                'name' => 'Edited Cup',
            ]))
            ->assertRedirect(route('staff.tournaments.show', $tournament));

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'name' => 'Edited Cup',
        ]);
    }

    public function test_coach_can_create_and_edit_own_tournament_but_not_admin_tournament(): void
    {
        $coach = $this->makeUser('coach');
        $coachGroup = $this->makeGroup($coach);
        $student = $this->makeUser('student');
        $coachGroup->students()->attach($student->id);

        $this->actingAs($coach)
            ->post(route('coach.tournaments.store'), array_merge($this->tournamentPayload(), [
                'audience_type' => 'specific_groups',
                'group_ids' => [$coachGroup->id],
            ]))
            ->assertRedirect(route('coach.tournaments.index'));

        $ownTournament = Tournament::where('created_by', $coach->id)->latest('id')->firstOrFail();

        $this->actingAs($coach)
            ->put(route('coach.tournaments.update', $ownTournament), array_merge($this->tournamentPayload(), [
                'name' => 'Coach Updated',
                'audience_type' => 'specific_groups',
                'group_ids' => [$coachGroup->id],
            ]))
            ->assertRedirect(route('coach.tournaments.show', $ownTournament));

        $admin = $this->makeUser('admin');
        $adminTournament = $this->makeTournament(createdBy: $admin->id);

        $this->actingAs($coach)
            ->get(route('coach.tournaments.edit', $adminTournament))
            ->assertForbidden();
    }

    public function test_tournament_pages_hide_management_actions_that_would_be_forbidden(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        $coach = $this->makeUser('coach');
        $scheduled = $this->makeTournament(createdBy: $admin->id);

        $this->actingAs($coach)
            ->get(route('coach.tournaments.show', $scheduled))
            ->assertOk()
            ->assertDontSee(route('coach.tournaments.edit', $scheduled), false)
            ->assertDontSee(route('coach.tournaments.cancel', $scheduled), false);

        $this->actingAs($coach)
            ->get(route('coach.tournaments.index'))
            ->assertOk()
            ->assertDontSee(route('coach.tournaments.edit', $scheduled), false)
            ->assertDontSee(route('coach.tournaments.cancel', $scheduled), false);

        $scheduled->update([
            'status' => Tournament::STATUS_LIVE,
            'current_round' => 1,
            'round_started_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tournaments.show', $scheduled))
            ->assertOk()
            ->assertDontSee(route('admin.tournaments.edit', $scheduled), false)
            ->assertSee(route('admin.tournaments.cancel', $scheduled), false);

        $this->actingAs($staff)
            ->get(route('staff.tournaments.show', $scheduled))
            ->assertOk()
            ->assertDontSee(route('staff.tournaments.edit', $scheduled), false)
            ->assertDontSee(route('staff.tournaments.cancel', $scheduled), false);
    }

    public function test_student_sees_only_eligible_tournaments_and_duplicate_join_is_prevented(): void
    {
        $coach = $this->makeUser('coach');
        $group = $this->makeGroup($coach);
        $student = $this->makeUser('student', ['name' => 'Eligible Student']);
        $other = $this->makeUser('student', ['name' => 'Other Student']);
        $group->students()->attach($student->id);

        $visible = $this->makeTournament(audience: Tournament::AUDIENCE_SPECIFIC_GROUPS);
        $visible->targetGroups()->sync([$group->id]);

        $hidden = $this->makeTournament(name: 'Hidden Cup', audience: Tournament::AUDIENCE_SPECIFIC_STUDENTS);
        $hidden->targetUsers()->sync([$other->id]);

        $this->actingAs($student)
            ->get(route('student.tournaments.index'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);

        $this->actingAs($student)->post(route('student.tournaments.join', $visible))->assertRedirect();
        $this->actingAs($student)->post(route('student.tournaments.join', $visible))->assertRedirect();

        $this->assertSame(1, TournamentParticipant::where('tournament_id', $visible->id)->where('user_id', $student->id)->count());
    }

    public function test_student_cannot_join_after_start(): void
    {
        $student = $this->makeUser('student');
        $tournament = $this->makeTournament(status: Tournament::STATUS_LIVE, audience: Tournament::AUDIENCE_ALL_STUDENTS);

        $this->actingAs($student)
            ->post(route('student.tournaments.join', $tournament))
            ->assertForbidden();
    }

    public function test_tournament_with_three_players_cancels_for_insufficient_players(): void
    {
        $tournament = $this->makeTournament(scheduledAt: now()->subMinute());
        $this->joinPlayers($tournament, 3);

        Artisan::call('tournaments:process');

        $tournament->refresh();
        $this->assertSame(Tournament::STATUS_CANCELLED_INSUFFICIENT_PLAYERS, $tournament->status);
        $this->assertStringContainsString('minimum 4 required', $tournament->cancellation_reason);
        $this->assertSame(0, $tournament->pairings()->count());
    }

    public function test_tournament_with_four_players_starts_only_once_and_round_one_pairings_are_created_once(): void
    {
        $tournament = $this->makeTournament(scheduledAt: now()->subMinute());
        $this->joinPlayers($tournament, 4);

        Artisan::call('tournaments:process');
        Artisan::call('tournaments:process');

        $tournament->refresh();
        $this->assertSame(Tournament::STATUS_LIVE, $tournament->status);
        $this->assertSame(1, $tournament->current_round);
        $this->assertSame(2, $tournament->pairings()->where('round', 1)->count());
    }

    public function test_repeat_opponents_are_avoided_where_alternatives_exist(): void
    {
        $tournament = $this->makeTournament(scheduledAt: now()->subMinute(), rounds: 2);
        $players = $this->joinPlayers($tournament, 4);

        TournamentScheduler::startTournament($tournament);

        $roundOne = $tournament->fresh()->pairings()->where('round', 1)->get();
        $roundOneSets = $roundOne->map(fn ($p) => collect([$p->white_user_id, $p->black_user_id])->sort()->values()->all());

        foreach ($roundOne as $pairing) {
            TournamentScheduler::setResult($pairing, '1-0', $pairing->white_user_id);
        }

        $tournament->refresh();
        $tournament->update(['next_round_at' => now()->subSecond()]);
        TournamentScheduler::advance($tournament);

        $roundTwo = $tournament->fresh()->pairings()->where('round', 2)->get();
        foreach ($roundTwo as $pairing) {
            $set = collect([$pairing->white_user_id, $pairing->black_user_id])->sort()->values()->all();
            $this->assertFalse($roundOneSets->contains(fn ($prior) => $prior === $set));
        }
    }

    public function test_odd_player_bye_works_and_same_player_does_not_receive_repeated_bye_when_avoidable(): void
    {
        $tournament = $this->makeTournament(scheduledAt: now()->subMinute(), rounds: 2);
        $this->joinPlayers($tournament, 5);

        TournamentScheduler::startTournament($tournament);
        $roundOneBye = $tournament->fresh()->pairings()->where('round', 1)->where('is_bye', true)->firstOrFail();
        $byeUserId = $roundOneBye->white_user_id;

        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament->id,
            'user_id' => $byeUserId,
            'bye_received' => 1,
        ]);

        $activeRoundOne = $tournament->fresh()->pairings()->where('round', 1)->where('is_bye', false)->get();
        foreach ($activeRoundOne as $pairing) {
            TournamentScheduler::setResult($pairing, '1-0', $pairing->white_user_id);
        }

        $tournament->refresh();
        $tournament->update(['next_round_at' => now()->subSecond()]);
        TournamentScheduler::advance($tournament);

        $roundTwoBye = $tournament->fresh()->pairings()->where('round', 2)->where('is_bye', true)->firstOrFail();
        $this->assertNotSame($byeUserId, $roundTwoBye->white_user_id);
    }

    public function test_unauthorised_student_cannot_submit_a_move(): void
    {
        [$tournament, $pairing, $white, $black] = $this->makeLiveGame();
        $other = $this->makeUser('student');

        $this->actingAs($other)
            ->postJson(route('student.tournaments.move', [$tournament, $pairing]), [
                'from' => 'e2',
                'to' => 'e4',
                'resulting_fen' => 'fake fen',
            ])
            ->assertForbidden();
    }

    public function test_illegal_move_is_rejected_and_fake_fen_is_ignored_but_legal_move_updates_authoritative_fen(): void
    {
        [$tournament, $pairing, $white] = $this->makeLiveGame();

        $this->actingAs($white)
            ->postJson(route('student.tournaments.move', [$tournament, $pairing]), [
                'from' => 'e2',
                'to' => 'e5',
                'resulting_fen' => 'malicious fen',
            ])
            ->assertStatus(422);

        $this->actingAs($white)
            ->postJson(route('student.tournaments.move', [$tournament, $pairing]), [
                'from' => 'e2',
                'to' => 'e4',
                'resulting_fen' => 'malicious fen',
            ])
            ->assertOk();

        $pairing->refresh();
        $this->assertSame('rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1', $pairing->current_fen);
    }

    public function test_increment_is_added_correctly_and_reconnect_poll_restores_trusted_state(): void
    {
        [$tournament, $pairing, $white] = $this->makeLiveGame(timeControl: '5+3');

        $this->actingAs($white)
            ->postJson(route('student.tournaments.move', [$tournament, $pairing]), [
                'from' => 'e2',
                'to' => 'e4',
            ])
            ->assertOk();

        $pairing->refresh();
        $this->assertSame(303000, (int) $pairing->white_clock_ms);

        $poll = $this->actingAs($white)
            ->getJson(route('student.tournaments.poll', [$tournament, $pairing]))
            ->assertOk()
            ->json();

        $this->assertSame($pairing->current_fen, $poll['fen']);
        $this->assertSame(303000, $poll['white_clock_ms']);
    }

    public function test_timeout_is_decided_server_side(): void
    {
        [$tournament, $pairing, $white] = $this->makeLiveGame(
            currentFen: 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR b KQkq - 0 1',
            moveCount: 1,
            movesPgn: 'e4',
            lastMoveAt: now()->subMinutes(6)
        );

        $this->actingAs($white)
            ->getJson(route('student.tournaments.poll', [$tournament, $pairing]))
            ->assertOk();

        $pairing->refresh();
        $this->assertSame('completed', $pairing->status);
        $this->assertSame('1-0', $pairing->result);
    }

    public function test_resign_and_draw_acceptance_are_idempotent_and_scores_apply_once(): void
    {
        [$tournament, $pairing, $white, $black] = $this->makeLiveGame();

        $this->actingAs($white)->postJson(route('student.tournaments.resign', [$tournament, $pairing]))->assertOk();
        $this->actingAs($white)->postJson(route('student.tournaments.resign', [$tournament, $pairing]))->assertOk();

        $pairing->refresh();
        $this->assertSame('0-1', $pairing->result);
        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament->id,
            'user_id' => $black->id,
            'score' => 1.0,
        ]);

        [$tournament2, $pairing2, $white2, $black2] = $this->makeLiveGame(name: 'Draw Cup');
        $this->actingAs($white2)->postJson(route('student.tournaments.draw', [$tournament2, $pairing2]))->assertOk();
        $this->actingAs($black2)->postJson(route('student.tournaments.draw', [$tournament2, $pairing2]))->assertOk();
        $this->actingAs($black2)->postJson(route('student.tournaments.draw', [$tournament2, $pairing2]))->assertOk();

        $pairing2->refresh();
        $this->assertSame('0.5-0.5', $pairing2->result);
        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament2->id,
            'user_id' => $white2->id,
            'score' => 0.5,
        ]);
        $this->assertDatabaseHas('tournament_participants', [
            'tournament_id' => $tournament2->id,
            'user_id' => $black2->id,
            'score' => 0.5,
        ]);
    }

    public function test_next_round_and_final_completion_happen_only_once_and_buchholz_is_computed(): void
    {
        $tournament = $this->makeTournament(scheduledAt: now()->subMinute(), rounds: 2);
        $this->joinPlayers($tournament, 4);

        TournamentScheduler::startTournament($tournament);
        $roundOne = $tournament->fresh()->pairings()->where('round', 1)->get();
        foreach ($roundOne as $pairing) {
            TournamentScheduler::setResult($pairing, '1-0', $pairing->white_user_id);
        }

        $tournament->refresh();
        $tournament->update(['next_round_at' => now()->subSecond()]);
        TournamentScheduler::advance($tournament);
        TournamentScheduler::advance($tournament);

        $this->assertSame(2, $tournament->fresh()->current_round);
        $this->assertSame(2, $tournament->fresh()->pairings()->where('round', 2)->count());

        $roundTwo = $tournament->fresh()->pairings()->where('round', 2)->get();
        foreach ($roundTwo as $pairing) {
            TournamentScheduler::setResult($pairing, '0.5-0.5', null);
        }

        TournamentScheduler::advance($tournament);
        TournamentScheduler::advance($tournament);

        $tournament->refresh();
        $this->assertSame(Tournament::STATUS_COMPLETED, $tournament->status);
        $this->assertNotNull($tournament->completed_at);
        $this->assertTrue($tournament->participants()->where('buchholz', '>', 0)->exists());

        TournamentScheduler::advance($tournament);
        $this->assertSame(Tournament::STATUS_COMPLETED, $tournament->fresh()->status);
    }

    protected function tournamentPayload(): array
    {
        return [
            'name' => 'Summer Cup',
            'event_series' => 'standard',
            'format' => 'blitz',
            'time_control' => '5+3',
            'rounds' => 5,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'audience_type' => 'all_students',
        ];
    }

    protected function makeUser(string $role, array $attrs = []): User
    {
        static $i = 1;

        return User::create(array_merge([
            'name' => ucfirst($role).' User '.$i,
            'username' => $role.'_'.$i++,
            'password' => bcrypt('secret123'),
            'role' => $role,
            'is_active' => true,
        ], $attrs));
    }

    protected function makeGroup(User $coach): Group
    {
        return Group::create([
            'name' => 'Group '.Str::random(4),
            'level' => 'beginner',
            'coach_id' => $coach->id,
            'created_by' => $coach->id,
            'unlocked_chapter_index' => 0,
            'is_active' => true,
        ]);
    }

    protected function makeTournament(
        ?string $name = 'Summer Cup',
        ?string $status = Tournament::STATUS_UPCOMING,
        ?string $audience = Tournament::AUDIENCE_ALL_STUDENTS,
        ?string $timeControl = '5+3',
        ?int $rounds = 3,
        ?CarbonInterface $scheduledAt = null,
        ?int $createdBy = null
    ): Tournament {
        $creator = $createdBy ? User::findOrFail($createdBy) : $this->makeUser('admin');

        return Tournament::create([
            'name' => $name,
            'format' => 'blitz',
            'time_control' => $timeControl,
            'rounds' => $rounds,
            'minimum_players' => 4,
            'scheduled_at' => $scheduledAt ?: now()->addHour(),
            'tournament_timezone' => 'Asia/Kolkata',
            'round_minutes' => 15,
            'pairing_window_minutes' => 0,
            'round_break_minutes' => 1,
            'audience_type' => $audience,
            'status' => $status,
            'current_round' => $status === Tournament::STATUS_LIVE ? 1 : 0,
            'round_started_at' => $status === Tournament::STATUS_LIVE ? now() : null,
            'created_by' => $creator->id,
        ]);
    }

    protected function joinPlayers(Tournament $tournament, int $count): array
    {
        $players = [];
        for ($x = 0; $x < $count; $x++) {
            $player = $this->makeUser('student');
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'user_id' => $player->id,
                'score' => 0.0,
                'buchholz' => 0.0,
                'withdrawn' => false,
                'bye_received' => false,
            ]);
            $players[] = $player;
        }

        return $players;
    }

    protected function makeLiveGame(
        string $name = 'Live Cup',
        string $timeControl = '5+3',
        ?string $currentFen = null,
        int $moveCount = 0,
        ?string $movesPgn = null,
        $lastMoveAt = null
    ): array {
        $tournament = $this->makeTournament(name: $name, status: Tournament::STATUS_LIVE, timeControl: $timeControl, rounds: 1);
        [$white, $black] = $this->joinPlayers($tournament, 2);

        $pairing = TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'round' => 1,
            'board' => 1,
            'white_user_id' => $white->id,
            'black_user_id' => $black->id,
            'is_bye' => false,
            'current_fen' => $currentFen ?: 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'move_count' => $moveCount,
            'moves_pgn' => $movesPgn,
            'white_clock_ms' => 300000,
            'black_clock_ms' => 300000,
            'last_move_at' => $lastMoveAt ?: now(),
            'status' => 'active',
        ]);

        return [$tournament, $pairing, $white, $black];
    }
}

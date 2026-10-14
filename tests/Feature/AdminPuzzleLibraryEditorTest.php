<?php

namespace Tests\Feature;

use App\Models\Puzzle;
use App\Models\PuzzleSet;
use App\Models\User;
use App\Services\PgnChapterDocumentService;
use App\Services\PuzzleLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPuzzleLibraryEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_puzzle_bank_view_uses_the_shared_curriculum_puzzle_editor(): void
    {
        $admin = $this->makeUser('admin');
        [$set] = $this->makePuzzleSet($admin, "[Event \"Source Puzzle\"]\n\n1. e4 e5 *");

        $this->actingAs($admin)
            ->get(route('admin.puzzle-library.show', $set))
            ->assertOk()
            ->assertSee('id="ape-root"', false)
            ->assertSee('id="ape-board"', false)
            ->assertSee('id="ape-tree"', false)
            ->assertSee('Save Answer')
            ->assertSee('id="ape-save-chapter-topbar"', false)
            ->assertSee('Save Chapter')
            ->assertSee('id="chapter-import-trigger"', false)
            ->assertSee('Import PGN')
            ->assertSee(route('admin.puzzle-library.import', $set), false)
            ->assertSee('Moves')
            ->assertSee('Comments')
            ->assertSee('Source Puzzle')
            ->assertDontSee('>Edit<', false)
            ->assertDontSee('Deactivate')
            ->assertDontSee('Activate')
            ->assertDontSee('pl-admin-wrap');
    }

    public function test_import_pgn_appends_puzzles_without_changing_existing_puzzle_data(): void
    {
        $admin = $this->makeUser('admin');
        $originalPgn = "[Event \"Original Puzzle\"]\n\n1. e4 e5 *";
        [$set, $existingPuzzle] = $this->makePuzzleSet($admin, $originalPgn);
        $savedAnswer = "[Event \"Saved Admin Answer\"]\n\n1. Nf3 Nc6 *";
        $existingPuzzle->update(['answer_pgn' => $savedAnswer]);
        $incomingPgn = "[Event \"Imported Two\"]\n\n1. d4 d5 *\n\n[Event \"Imported Three\"]\n\n1. c4 e5 *";

        $this->actingAs($admin)
            ->put(route('admin.puzzle-library.import', $set), [
                'pgn_text' => $incomingPgn,
            ])
            ->assertRedirect(route('admin.puzzle-library.show', $set))
            ->assertSessionHas('success', '2 puzzle(s) imported successfully.');

        $set->refresh();
        $puzzles = $set->puzzles()->get();

        $this->assertSame(3, $set->total_puzzles);
        $this->assertCount(3, $puzzles);
        $this->assertSame($originalPgn, $puzzles[0]->source_pgn);
        $this->assertSame($savedAnswer, $puzzles[0]->answer_pgn);
        $this->assertSame('Imported Two', $this->eventName($puzzles[1]->source_pgn));
        $this->assertSame('Imported Three', $this->eventName($puzzles[2]->source_pgn));
        $this->assertStringContainsString($originalPgn, $set->source_pgn);
        $this->assertStringContainsString($incomingPgn, $set->source_pgn);
    }

    public function test_invalid_import_does_not_append_any_puzzles(): void
    {
        $admin = $this->makeUser('admin');
        [$set] = $this->makePuzzleSet($admin, "[Event \"Original Puzzle\"]\n\n1. e4 e5 *");
        $invalidImport = "[Event \"Valid First\"]\n\n1. d4 d5 *\n\n[Event \"Invalid Second\"]\n\n1. e4 e5 2. Bh6 *";

        $this->actingAs($admin)
            ->from(route('admin.puzzle-library.show', $set))
            ->put(route('admin.puzzle-library.import', $set), [
                'pgn_text' => $invalidImport,
            ])
            ->assertRedirect(route('admin.puzzle-library.show', $set))
            ->assertSessionHasErrors('pgn_file');

        $this->assertSame(1, $set->fresh()->total_puzzles);
        $this->assertSame(1, $set->puzzles()->count());
    }

    public function test_admin_moves_are_saved_as_answer_without_changing_original_puzzle(): void
    {
        $admin = $this->makeUser('admin');
        $originalPgn = "[Event \"Original Puzzle\"]\n\n1. e4 e5 *";
        [$set, $puzzle] = $this->makePuzzleSet($admin, $originalPgn);
        $originalFen = $puzzle->original_fen;
        $checksum = app(PgnChapterDocumentService::class)->checksum($originalPgn);

        $firstAnswer = "[Event \"Admin Answer\"]\n\n1. Nf3 {Main idea} (1. d4 d5) Nc6 2. d4 d5 *";
        $response = $this->actingAs($admin)
            ->putJson(route('admin.puzzle-library.puzzle.update', [$set, 0]), $this->payload($checksum, $firstAnswer))
            ->assertOk()
            ->assertJsonPath('message', 'Answer saved successfully.')
            ->assertJsonPath('checksum', $checksum);

        $puzzle->refresh();
        $this->assertSame($originalPgn, $puzzle->source_pgn);
        $this->assertSame($originalFen, $puzzle->original_fen);
        $this->assertStringContainsString('{Main idea}', $puzzle->answer_pgn);
        $this->assertStringContainsString('(1. d4 d5)', $puzzle->answer_pgn);
        $this->assertSame(['Nf3', 'Nc6', 'd4', 'd5'], $puzzle->main_line);
        $this->assertSame($originalPgn, $set->fresh()->source_pgn);

        $secondAnswer = "[Event \"Admin Answer Two\"]\n\n1. c4 e5 2. Nc3 Nf6 *";
        $this->actingAs($admin)
            ->putJson(
                route('admin.puzzle-library.puzzle.update', [$set, 0]),
                $this->payload($response->json('checksum'), $secondAnswer)
            )
            ->assertOk()
            ->assertJsonPath('checksum', $checksum);

        $puzzle->refresh();
        $this->assertSame($originalPgn, $puzzle->source_pgn);
        $this->assertSame($originalFen, $puzzle->original_fen);
        $this->assertStringContainsString('1. c4 e5 2. Nc3 Nf6 *', $puzzle->answer_pgn);
    }

    public function test_answer_with_a_different_original_position_is_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $originalPgn = "[Event \"Original Puzzle\"]\n\n1. e4 e5 *";
        [$set, $puzzle] = $this->makePuzzleSet($admin, $originalPgn);
        $checksum = app(PgnChapterDocumentService::class)->checksum($originalPgn);
        $differentPosition = "[Event \"Changed Position\"]\n[SetUp \"1\"]\n[FEN \"8/8/8/8/8/8/4K3/6k1 w - - 0 1\"]\n\n*";

        $this->actingAs($admin)
            ->putJson(
                route('admin.puzzle-library.puzzle.update', [$set, 0]),
                $this->payload($checksum, $differentPosition)
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.single_game_pgn.0',
                'The original puzzle position cannot be changed.'
            );

        $puzzle->refresh();
        $this->assertSame($originalPgn, $puzzle->source_pgn);
        $this->assertSame($originalPgn, $puzzle->answer_pgn);
    }

    public function test_stale_source_checksum_is_rejected(): void
    {
        $admin = $this->makeUser('admin');
        [$set, $puzzle] = $this->makePuzzleSet($admin, "[Event \"Original Puzzle\"]\n\n1. e4 e5 *");

        $this->actingAs($admin)
            ->putJson(
                route('admin.puzzle-library.puzzle.update', [$set, 0]),
                $this->payload(str_repeat('0', 64), "[Event \"Answer\"]\n\n1. d4 d5 *")
            )
            ->assertStatus(409);

        $this->assertSame(
            "[Event \"Original Puzzle\"]\n\n1. e4 e5 *",
            $puzzle->fresh()->answer_pgn
        );
    }

    public function test_non_admin_cannot_save_puzzle_bank_answer(): void
    {
        $admin = $this->makeUser('admin');
        $coach = $this->makeUser('coach');
        [$set] = $this->makePuzzleSet($admin, "[Event \"Original Puzzle\"]\n\n1. e4 e5 *");
        $checksum = app(PgnChapterDocumentService::class)
            ->checksum("[Event \"Original Puzzle\"]\n\n1. e4 e5 *");

        $this->actingAs($coach)
            ->putJson(
                route('admin.puzzle-library.puzzle.update', [$set, 0]),
                $this->payload($checksum, "[Event \"Answer\"]\n\n1. d4 d5 *")
            )
            ->assertForbidden();
    }

    private function makePuzzleSet(User $admin, string $sourcePgn): array
    {
        $payload = app(PuzzleLibraryService::class)->buildPuzzlePayload($sourcePgn, 1);
        $set = PuzzleSet::create([
            'name' => 'Test Puzzle Set',
            'level' => 'beginner',
            'category_name' => 'Mixed',
            'source_pgn' => $sourcePgn,
            'source_file_name' => 'test.pgn',
            'total_puzzles' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $puzzle = Puzzle::create([
            'puzzle_set_id' => $set->id,
            'puzzle_order' => 1,
            'source_pgn' => $payload['source_pgn'],
            'original_fen' => $payload['original_fen'],
            'side_to_move' => $payload['side_to_move'],
            'answer_pgn' => $payload['answer_pgn'],
            'main_line' => $payload['main_line'],
            'solution_uci' => $payload['solution_uci'],
            'final_fen' => $payload['final_fen'],
            'comments' => $payload['comments'],
        ]);

        return [$set, $puzzle];
    }

    private function payload(string $checksum, string $answerPgn): array
    {
        return [
            'original_checksum' => $checksum,
            'single_game_pgn' => $answerPgn,
            'puzzle_name' => '',
            'question' => '',
            'answer' => '',
            'hint' => '',
            'explanation' => '',
            'general_notes' => '',
            'annotation' => '',
        ];
    }

    private function eventName(string $pgn): string
    {
        preg_match('/^\[Event "([^"]+)"\]/m', $pgn, $matches);

        return $matches[1] ?? '';
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'name' => ucfirst($role) . ' User ' . (User::count() + 1),
            'username' => $role . '_' . (User::count() + 1),
            'role' => $role,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }
}

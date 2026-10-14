<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ChapterPuzzleAnswer;
use App\Models\User;
use App\Services\PgnChapterDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminChapterPuzzleSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_answer_creates_separate_answer_record_and_keeps_original_pgn(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $originalPgn = "[Event \"One\"]\n\n1. e4 e5 *";
        $chapter = $this->makeChapter($admin, $originalPgn);
        $service = app(PgnChapterDocumentService::class);

        $response = $this->actingAs($admin)
            ->putJson(route('admin.library.chapter.puzzle.update', [$chapter, 0]), $this->payload(
                $service,
                $chapter,
                0,
                "[Event \"Puzzle 1\"]\n\n1. e4 e5 2. Nf3 Nc6 3. Bb5 *"
            ))
            ->assertOk();

        $response->assertJsonPath('message', 'Answer saved successfully.');
        $this->assertSame($originalPgn, $chapter->fresh()->pgn);
        $this->assertDatabaseHas('chapter_puzzle_answers', [
            'chapter_id' => $chapter->id,
            'puzzle_index' => 0,
            'saved_by' => $admin->id,
        ]);
        $this->assertStringContainsString('1. e4 e5 2. Nf3 Nc6 3. Bb5 *', ChapterPuzzleAnswer::firstOrFail()->answer_pgn);
    }

    public function test_resaving_updates_answer_only_and_preserves_original_position(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $chapter = $this->makeChapter($admin, "[Event \"One\"]\n\n1. e4 e5 *");
        $service = app(PgnChapterDocumentService::class);

        $this->actingAs($admin)
            ->putJson(route('admin.library.chapter.puzzle.update', [$chapter, 0]), $this->payload(
                $service,
                $chapter,
                0,
                "[Event \"Puzzle 1\"]\n\n1. Nf3 Nc6 *"
            ))
            ->assertOk();

        $this->actingAs($admin)
            ->putJson(route('admin.library.chapter.puzzle.update', [$chapter, 0]), $this->payload(
                $service,
                $chapter,
                0,
                "[Event \"Puzzle 1\"]\n\n1. d4 d5 2. c4 *"
            ))
            ->assertOk()
            ->assertJsonPath('move_count', 2);

        $chapter->refresh();
        $answer = ChapterPuzzleAnswer::where('chapter_id', $chapter->id)->where('puzzle_index', 0)->firstOrFail();

        $this->assertSame("[Event \"One\"]\n\n1. e4 e5 *", $chapter->pgn);
        $this->assertStringContainsString('1. d4 d5 2. c4 *', $answer->answer_pgn);
    }

    public function test_saved_answer_must_start_from_original_fen(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $chapter = $this->makeChapter(
            $admin,
            "[Event \"Fen\"]\n[SetUp \"1\"]\n[FEN \"8/8/8/8/8/8/4K3/4k3 b - - 0 1\"]\n\n*"
        );
        $service = app(PgnChapterDocumentService::class);

        $this->actingAs($admin)
            ->putJson(route('admin.library.chapter.puzzle.update', [$chapter, 0]), $this->payload(
                $service,
                $chapter,
                0,
                "[Event \"Fen\"]\n[SetUp \"1\"]\n[FEN \"8/8/8/8/8/8/4K3/3K4 b - - 0 1\"]\n\n*"
            ))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->putJson(route('admin.library.chapter.puzzle.update', [$chapter, 0]), $this->payload(
                $service,
                $chapter,
                0,
                "[Event \"Fen\"]\n[SetUp \"1\"]\n[FEN \"8/8/8/8/8/8/4K3/4k3 b - - 0 1\"]\n\n*"
            ))
            ->assertOk();
    }

    public function test_download_still_returns_original_chapter_pgn_not_saved_answer(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $originalPgn = "[Event \"Original\"]\n\n1. e4 e5 *";
        $chapter = $this->makeChapter($admin, $originalPgn);
        $service = app(PgnChapterDocumentService::class);

        $this->actingAs($admin)
            ->putJson(route('admin.library.chapter.puzzle.update', [$chapter, 0]), $this->payload(
                $service,
                $chapter,
                0,
                "[Event \"Answer\"]\n\n1. Nf3 d5 *"
            ))
            ->assertOk();

        $download = $this->actingAs($admin)->get(route('admin.library.chapter.download', $chapter->fresh()));
        $download->assertOk();
        $this->assertStringContainsString($originalPgn, $download->streamedContent());
        $this->assertStringNotContainsString('1. Nf3 d5 *', $download->streamedContent());
    }

    public function test_non_admin_cannot_save_answer(): void
    {
        Storage::fake('local');

        $coach = $this->makeUser('coach');
        $admin = $this->makeUser('admin');
        $chapter = $this->makeChapter($admin, "[Event \"One\"]\n\n1. e4 e5 *");
        $service = app(PgnChapterDocumentService::class);

        $this->actingAs($coach)
            ->putJson(route('admin.library.chapter.puzzle.update', [$chapter, 0]), $this->payload(
                $service,
                $chapter,
                0,
                "[Event \"Puzzle 1\"]\n\n1. Nf3 *"
            ))
            ->assertForbidden();
    }

    public function test_answer_backup_file_is_created(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $chapter = $this->makeChapter($admin, "[Event \"One\"]\n\n1. e4 e5 *");
        $service = app(PgnChapterDocumentService::class);

        $response = $this->actingAs($admin)
            ->putJson(route('admin.library.chapter.puzzle.update', [$chapter, 0]), $this->payload(
                $service,
                $chapter,
                0,
                "[Event \"Answer\"]\n\n1. Nf3 Nc6 *"
            ))
            ->assertOk();

        Storage::disk('local')->assertExists($response->json('backup_path'));
    }

    public function test_import_pgn_appends_new_puzzles_to_existing_chapter(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $originalPgn = "[Event \"One\"]\n\n1. e4 e5 *\n\n[Event \"Two\"]\n\n1. d4 d5 *";
        $chapter = $this->makeChapter($admin, $originalPgn);
        $importPgn = "[Event \"Three\"]\n\n1. c4 e5 *\n\n[Event \"Four\"]\n\n1. Nf3 d5 *";

        $response = $this->actingAs($admin)->from(route('admin.library.chapter', $chapter))
            ->put(route('admin.library.chapter.update', $chapter), [
                'name' => $chapter->name,
                'level' => $chapter->level,
                'sequence_number' => $chapter->sequence_number,
                'pgn_file' => UploadedFile::fake()->createWithContent('append.pgn', $importPgn),
            ]);

        $response->assertRedirect(route('admin.library.chapter', $chapter));

        $chapter->refresh();
        $games = app(PgnChapterDocumentService::class)->splitGames($chapter->pgn);

        $this->assertCount(4, $games);
        $this->assertSame("[Event \"One\"]\n\n1. e4 e5 *", $games[0]);
        $this->assertSame("[Event \"Two\"]\n\n1. d4 d5 *", $games[1]);
        $this->assertSame("[Event \"Three\"]\n\n1. c4 e5 *", $games[2]);
        $this->assertSame("[Event \"Four\"]\n\n1. Nf3 d5 *", $games[3]);
    }

    public function test_save_chapter_can_append_staged_pgn_text(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $originalPgn = "[Event \"One\"]\n\n1. e4 e5 *";
        $chapter = $this->makeChapter($admin, $originalPgn);
        $stagedPgn = "[Event \"Two\"]\n\n1. d4 d5 *";

        $response = $this->actingAs($admin)->from(route('admin.library.chapter', $chapter))
            ->put(route('admin.library.chapter.update', $chapter), [
                'name' => $chapter->name,
                'level' => $chapter->level,
                'sequence_number' => $chapter->sequence_number,
                'pgn_text' => $stagedPgn,
            ]);

        $response->assertRedirect(route('admin.library.chapter', $chapter));

        $chapter->refresh();
        $games = app(PgnChapterDocumentService::class)->splitGames($chapter->pgn);

        $this->assertCount(2, $games);
        $this->assertSame("[Event \"One\"]\n\n1. e4 e5 *", $games[0]);
        $this->assertSame("[Event \"Two\"]\n\n1. d4 d5 *", $games[1]);
    }

    private function payload(PgnChapterDocumentService $service, Chapter $chapter, int $index, string $replacement): array
    {
        $games = $service->splitGames($chapter->pgn);

        return [
            'original_checksum' => $service->checksum($games[$index]),
            'single_game_pgn' => $replacement,
            'puzzle_name' => '',
            'question' => '',
            'answer' => '',
            'hint' => '',
            'explanation' => '',
            'annotation' => '',
        ];
    }

    private function makeChapter(User $admin, string $pgn): Chapter
    {
        $path = 'pgn/beginner/test-' . Str::uuid() . '.pgn';
        Storage::disk('local')->put($path, $pgn);

        return Chapter::create([
            'name' => 'Chapter ' . (Chapter::count() + 1),
            'pgn' => $pgn,
            'level' => 'beginner',
            'sequence_number' => Chapter::count() + 1,
            'uploaded_by' => $admin->id,
            'sort_order' => Chapter::count() + 1,
            'move_count' => 1,
            'file_path' => $path,
        ]);
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

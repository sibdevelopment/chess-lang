<?php

namespace Tests\Unit;

use App\Services\PgnChapterDocumentService;
use Tests\TestCase;

class PgnChapterDocumentServiceTest extends TestCase
{
    private PgnChapterDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PgnChapterDocumentService();
    }

    public function test_split_games_handles_single_game(): void
    {
        $games = $this->service->splitGames("[Event \"One\"]\n\n1. e4 e5 *");
        $this->assertCount(1, $games);
    }

    public function test_split_games_handles_multiple_games(): void
    {
        $games = $this->service->splitGames("[Event \"One\"]\n\n1. e4 e5 *\n\n[Event \"Two\"]\n\n1. d4 d5 *");
        $this->assertCount(2, $games);
    }

    public function test_replace_game_only_updates_selected_index(): void
    {
        $full = "[Event \"One\"]\n\n1. e4 e5 *\n\n[Event \"Two\"]\n\n1. d4 d5 *\n\n[Event \"Three\"]\n\n1. c4 e5 *";
        $updated = $this->service->replaceGame($full, 1, "[Event \"Two\"]\n\n1. d4 Nf6 *");
        $games = $this->service->splitGames($updated);
        $this->assertSame("[Event \"One\"]\n\n1. e4 e5 *", $games[0]);
        $this->assertSame("[Event \"Two\"]\n\n1. d4 Nf6 *", $games[1]);
        $this->assertSame("[Event \"Three\"]\n\n1. c4 e5 *", $games[2]);
    }

    public function test_append_games_adds_new_puzzles_after_existing_ones(): void
    {
        $existing = "[Event \"One\"]\n\n1. e4 e5 *\n\n[Event \"Two\"]\n\n1. d4 d5 *";
        $incoming = "[Event \"Three\"]\n\n1. c4 e5 *\n\n[Event \"Four\"]\n\n1. Nf3 d5 *";

        $result = $this->service->appendGames($existing, $incoming);
        $games = $this->service->splitGames($result['pgn']);

        $this->assertSame(2, $result['existing_count']);
        $this->assertSame(2, $result['appended_count']);
        $this->assertCount(4, $games);
        $this->assertSame("[Event \"One\"]\n\n1. e4 e5 *", $games[0]);
        $this->assertSame("[Event \"Two\"]\n\n1. d4 d5 *", $games[1]);
        $this->assertSame("[Event \"Three\"]\n\n1. c4 e5 *", $games[2]);
        $this->assertSame("[Event \"Four\"]\n\n1. Nf3 d5 *", $games[3]);
    }

    public function test_checksum_is_stable_for_same_game(): void
    {
        $game = "[Event \"One\"]\n\n1. e4 e5 *";
        $this->assertSame($this->service->checksum($game), $this->service->checksum($game . "\n"));
    }

    public function test_validate_single_game_accepts_custom_fen(): void
    {
        $result = $this->service->validateSingleGame("[Event \"FEN\"]\n[SetUp \"1\"]\n[FEN \"8/8/8/8/8/8/4K3/4k3 w - - 0 1\"]\n\n*");
        $this->assertTrue($result['ok']);
    }

    public function test_validate_single_game_rejects_invalid_fen(): void
    {
        $result = $this->service->validateSingleGame("[Event \"Bad\"]\n[FEN \"bad fen\"]\n\n*");
        $this->assertFalse($result['ok']);
    }

    public function test_validate_single_game_rejects_multiple_games(): void
    {
        $result = $this->service->validateSingleGame("[Event \"One\"]\n\n1. e4 e5 *\n\n[Event \"Two\"]\n\n1. d4 d5 *");
        $this->assertFalse($result['ok']);
    }

    public function test_validate_single_game_rejects_illegal_move_sequence(): void
    {
        $result = $this->service->validateSingleGame("[Event \"Bad\"]\n\n1. e5 *");
        $this->assertFalse($result['ok']);
    }

    public function test_validate_single_game_preserves_comments_and_variations(): void
    {
        $game = "[Event \"Var\"]\n\n1. e4 {Idea} e5 (1... c5) 2. Nf3 *";
        $result = $this->service->validateSingleGame($game);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('{Idea}', $result['normalized']);
        $this->assertStringContainsString('(1... c5)', $result['normalized']);
    }

    public function test_validate_single_game_enforces_archer_metadata_limits(): void
    {
        $game = "[Event \"Meta\"]\n[ArcherPuzzleName \"" . str_repeat('a', 256) . "\"]\n\n*";
        $result = $this->service->validateSingleGame($game);
        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('puzzle_name', $result['errors']);
    }
}

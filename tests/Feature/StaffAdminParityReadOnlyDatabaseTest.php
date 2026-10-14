<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Puzzle;
use App\Models\PuzzleSet;
use App\Models\User;
use App\Services\PuzzleLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAdminParityReadOnlyDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_uses_the_admin_lms_navigation_and_people_tabs(): void
    {
        $staff = $this->makeUser('staff');

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk()
            ->assertSee('Students')
            ->assertSee('Coaches')
            ->assertSee('Staff')
            ->assertSee('Batches')
            ->assertSee('Dashboard')
            ->assertDontSee('Archer Journey')
            ->assertDontSee('Archer Discovery')
            ->assertSee('Database')
            ->assertSee('Curriculum')
            ->assertSee('Quiz Bank')
            ->assertSee('Puzzle Bank')
            ->assertSee('Classes')
            ->assertSee('Homework')
            ->assertSee('Operations')
            ->assertSee('Reports')
            ->assertDontSee('Settings')
            ->assertDontSee('Logout')
            ->assertSee(route('staff.library'), false)
            ->assertSee(route('staff.quizzes.index'), false)
            ->assertSee(route('staff.puzzle-library.index'), false);
    }

    public function test_staff_can_open_every_shared_admin_lms_section(): void
    {
        $staff = $this->makeUser('staff');

        foreach ([
            'staff.classes',
            'staff.homework',
            'staff.tournaments.index',
            'staff.quiz-sessions.index',
            'staff.reports',
            'staff.settings',
        ] as $routeName) {
            $this->actingAs($staff)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_staff_curriculum_is_view_only(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        $chapter = $this->makeChapter($admin);

        $this->actingAs($staff)
            ->get(route('staff.library'))
            ->assertOk()
            ->assertSee($chapter->name)
            ->assertSee('View')
            ->assertSee(route('staff.library.chapter', $chapter), false)
            ->assertDontSee('+ Add Chapter')
            ->assertDontSee('data-lib-open-modal', false)
            ->assertDontSee('data-edit-chapter', false)
            ->assertDontSee('Download')
            ->assertDontSee('Delete');

        $this->actingAs($staff)
            ->get(route('staff.library.chapter', $chapter))
            ->assertOk()
            ->assertSee('id="ape-root"', false)
            ->assertSee('id="ape-board"', false)
            ->assertSee('id="ape-tree"', false)
            ->assertSee('Moves')
            ->assertSee('Comments')
            ->assertSee('Comments are read-only.')
            ->assertSee('const EDITOR_READ_ONLY = true', false)
            ->assertDontSee('id="ape-save-answer-inline"', false)
            ->assertDontSee('data-ape-act="reset-puzzle"', false)
            ->assertDontSee('id="ape-save-chapter-topbar"', false)
            ->assertDontSee('id="chapter-import-trigger"', false)
            ->assertDontSee('id="chapter-import-form"', false)
            ->assertDontSee('id="ape-move-menu"', false);
    }

    public function test_staff_puzzle_bank_is_view_only(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        [$set] = $this->makePuzzleSet($admin);

        $this->actingAs($staff)
            ->get(route('staff.puzzle-library.index'))
            ->assertOk()
            ->assertSee($set->name)
            ->assertSee('View')
            ->assertSee(route('staff.puzzle-library.show', $set), false)
            ->assertDontSee('Create Puzzle Set')
            ->assertDontSee('Edit')
            ->assertDontSee('Deactivate')
            ->assertDontSee('Activate');

        $this->actingAs($staff)
            ->get(route('staff.puzzle-library.show', $set))
            ->assertOk()
            ->assertSee('id="ape-root"', false)
            ->assertSee('id="ape-board"', false)
            ->assertSee('id="ape-tree"', false)
            ->assertSee('const EDITOR_READ_ONLY = true', false)
            ->assertDontSee('id="ape-save-answer-inline"', false)
            ->assertDontSee('data-ape-act="reset-puzzle"', false)
            ->assertDontSee('id="ape-save-chapter-topbar"', false)
            ->assertDontSee('id="chapter-import-trigger"', false)
            ->assertDontSee('id="chapter-import-form"', false)
            ->assertDontSee('id="ape-move-menu"', false);
    }

    public function test_staff_quiz_bank_is_view_only(): void
    {
        $staff = $this->makeUser('staff');

        $this->actingAs($staff)
            ->get(route('staff.quizzes.index'))
            ->assertOk()
            ->assertSee('Quiz Bank')
            ->assertDontSee('+ New quiz')
            ->assertDontSee('>Edit<', false);
    }

    public function test_staff_database_registers_only_get_and_head_routes(): void
    {
        $expectedRouteNames = [
            'staff.database',
            'staff.library',
            'staff.library.chapter',
            'staff.puzzle-library.index',
            'staff.puzzle-library.show',
            'staff.quizzes.index',
        ];

        $databaseRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array($route->getName(), $expectedRouteNames, true))
            ->values();

        $this->assertEqualsCanonicalizing(
            $expectedRouteNames,
            $databaseRoutes->map(fn ($route) => $route->getName())->all()
        );

        $databaseRoutes->each(function ($route): void {
            $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->getName());
        });

        $this->assertFalse(Route::has('staff.database.assign'));
        $this->assertFalse(Route::has('staff.library.upload'));
        $this->assertFalse(Route::has('staff.library.chapter.update'));
        $this->assertFalse(Route::has('staff.quizzes.create'));
        $this->assertFalse(Route::has('staff.quizzes.store'));
        $this->assertFalse(Route::has('staff.puzzle-library.store'));
        $this->assertFalse(Route::has('staff.puzzle-library.update'));
    }

    public function test_staff_cannot_use_admin_database_mutation_routes(): void
    {
        Storage::fake('local');

        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        $chapter = $this->makeChapter($admin);
        [$set] = $this->makePuzzleSet($admin);

        $this->actingAs($staff)
            ->post(route('admin.library.upload'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('admin.library.chapter.update', $chapter))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.puzzle-library.toggle-status', $set))
            ->assertForbidden();
    }

    private function makeChapter(User $admin): Chapter
    {
        $pgn = "[Event \"Staff Read Only\"]\n\n1. e4 e5 *";
        $path = 'pgn/beginner/staff-read-only-'.Str::uuid().'.pgn';
        Storage::disk('local')->put($path, $pgn);

        return Chapter::create([
            'name' => 'Staff Read-only Chapter',
            'pgn' => $pgn,
            'level' => 'beginner',
            'sequence_number' => 1,
            'uploaded_by' => $admin->id,
            'sort_order' => 1,
            'move_count' => 1,
            'file_path' => $path,
        ]);
    }

    private function makePuzzleSet(User $admin): array
    {
        $sourcePgn = "[Event \"Staff Read Only Puzzle\"]\n\n1. d4 d5 *";
        $payload = app(PuzzleLibraryService::class)->buildPuzzlePayload($sourcePgn, 1);

        $set = PuzzleSet::create([
            'name' => 'Staff Read-only Puzzle Set',
            'level' => 'beginner',
            'category_name' => 'Mixed',
            'source_pgn' => $sourcePgn,
            'source_file_name' => 'staff-read-only.pgn',
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

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'name' => ucfirst($role).' User '.(User::count() + 1),
            'username' => $role.'_'.(User::count() + 1),
            'role' => $role,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }
}

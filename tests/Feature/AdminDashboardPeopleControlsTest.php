<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardPeopleControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_tab_has_no_create_import_export_or_edit_controls(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.dashboard', ['tab' => 'students']))
            ->assertOk();

        $response
            ->assertDontSee('Archer Journey')
            ->assertDontSee('Archer Discovery')
            ->assertDontSee('data-student-create-open', false)
            ->assertDontSee('data-student-import-open', false)
            ->assertDontSee('edit-student-modal', false)
            ->assertDontSee('>Export<', false);
    }

    public function test_coaches_tab_has_no_create_or_edit_controls(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.dashboard', ['tab' => 'coaches']))
            ->assertOk();

        $response
            ->assertDontSee('data-coach-create-open', false)
            ->assertDontSee('data-coach-edit', false)
            ->assertDontSee('create-coach-modal', false)
            ->assertDontSee('edit-coach-modal', false);
    }

    public function test_staff_tab_has_no_create_import_or_edit_controls(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.dashboard', ['tab' => 'staff']))
            ->assertOk();

        $response
            ->assertDontSee('create-staff-modal', false)
            ->assertDontSee('edit-staff-modal', false)
            ->assertDontSee('data-staff-create', false)
            ->assertDontSee('data-staff-edit', false);
    }

    public function test_batches_tab_does_not_show_upcoming_chapter_column(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard', ['tab' => 'groups']))
            ->assertOk()
            ->assertDontSee('Upcoming Chapter');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'name' => 'Admin User '.(User::count() + 1),
            'username' => 'admin_'.(User::count() + 1),
            'role' => 'admin',
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);
    }
}

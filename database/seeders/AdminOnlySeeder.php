<?php namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds ONLY the Super Admin account — nothing else.
 *
 * Use this when you want an empty academy so you can create your own
 * staff / coaches / students / groups / PGNs from scratch:
 *
 *   php artisan migrate:fresh --seeder=Database\\Seeders\\AdminOnlySeeder
 *
 * Set INITIAL_ADMIN_PASSWORD in the deployment environment before running.
 */
class AdminOnlySeeder extends Seeder
{
    public function run(): void
    {
        $password = env('INITIAL_ADMIN_PASSWORD');

        if (blank($password)) {
            throw new \RuntimeException('INITIAL_ADMIN_PASSWORD must be configured before seeding the admin user.');
        }

        User::create([
            'name'     => 'Super Admin',
            'username' => 'admin',
            'email'    => 'admin@chess.academy',
            'password' => Hash::make($password),
            'role'     => 'admin',
        ]);

        $this->command->info('Empty academy ready. Admin username: admin');
    }
}

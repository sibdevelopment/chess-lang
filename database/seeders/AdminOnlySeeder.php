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
 * Login: admin / admin123
 */
class AdminOnlySeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Super Admin',
            'username' => 'admin',
            'email'    => 'admin@chess.academy',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
        ]);

        $this->command->info('✅ Empty academy ready. Login: admin / admin123');
    }
}

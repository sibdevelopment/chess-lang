<?php namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Group::query()->delete();
        Chapter::query()->delete();
        User::query()->whereIn('role', ['admin', 'staff', 'coach'])->delete();

        // Admin
        $admin = User::create([
            'name' => 'Super Admin', 'username' => 'admin',
            'email' => 'admin@chess.academy',
            'password' => Hash::make('admin123'), 'role' => 'admin',
        ]);

        // Staff
        $staff = User::create([
            'name' => 'Riya Shah', 'username' => 'staff1',
            'email' => 'riya@chess.academy',
            'password' => Hash::make('staff123'), 'role' => 'staff',
        ]);

        // Coaches
        $coach1 = User::create([
            'name' => 'Arjun Mehta', 'username' => 'coach1',
            'email' => 'arjun@chess.academy',
            'password' => Hash::make('coach123'), 'role' => 'coach',
        ]);
        $coach2 = User::create([
            'name' => 'Meera Nair', 'username' => 'coach2',
            'email' => 'meera@chess.academy',
            'password' => Hash::make('coach123'), 'role' => 'coach',
        ]);

        $this->call(StudentSeeder::class);
        $students = User::students()->orderBy('id')->limit(5)->get();

        // Chapters — global sequence number across all levels (beginner 1..N, intermediate N+1..M, advanced ...)
        $pgns = [
            ['e4 basics',             '1. e4 e5 2. Nf3 Nc6 3. Bc4 Bc5',                                     'beginner'],
            ['Scholar\'s Mate',       '1. e4 e5 2. Qh5 Nc6 3. Bc4 Nf6 4. Qxf7',                              'beginner'],
            ['Pawn chains',           '1. e4 e6 2. d4 d5 3. e5 c5 4. c3 Nc6',                                'beginner'],
            ['Sicilian Dragon',       '1. e4 c5 2. Nf3 d6 3. d4 cxd4 4. Nxd4 Nf6 5. Nc3 g6',                 'intermediate'],
            ['Ruy Lopez',             '1. e4 e5 2. Nf3 Nc6 3. Bb5 a6 4. Ba4 Nf6 5. O-O Be7 6. Re1',          'intermediate'],
            ['Queen\'s Gambit',       '1. d4 d5 2. c4 e6 3. Nc3 Nf6 4. Bg5 Be7 5. e3 O-O',                   'intermediate'],
            ['King + Pawn endgame',   '[FEN "8/4k3/8/4P3/4K3/8/8/8 w - - 0 1"]
1. Kd5 Kd7 2. e6+ Ke7 3. Ke5',                                                                               'advanced'],
        ];

        foreach ($pgns as $i => [$name, $pgn, $level]) {
            preg_match_all('/\d+\./', $pgn, $m);
            Chapter::create([
                'name'            => $name,
                'pgn'             => $pgn,
                'level'           => $level,
                'sequence_number' => $i + 1,          // 1,2,3...N global
                'uploaded_by'     => $admin->id,
                'sort_order'      => $i,
                'move_count'      => count($m[0]),
            ]);
        }

        // Groups
        $groupA = Group::create(['name'=>'Batch A — Beginner','level'=>'beginner','coach_id'=>$coach1->id,'created_by'=>$admin->id]);
        $groupB = Group::create(['name'=>'Batch B — Intermediate','level'=>'intermediate','coach_id'=>$coach1->id,'created_by'=>$admin->id]);
        $groupC = Group::create(['name'=>'Batch C — Advanced','level'=>'advanced','coach_id'=>$coach2->id,'created_by'=>$admin->id]);

        $groupA->students()->attach($students->slice(0, 2)->pluck('id'));
        $groupB->students()->attach($students->slice(2, 2)->pluck('id'));
        $groupC->students()->attach($students->slice(4, 1)->pluck('id'));

        // Assign chapters to groups sequentially
        $beginnerChapters = Chapter::where('level','beginner')->orderBy('sort_order')->get();
        foreach ($beginnerChapters as $i => $ch) {
            $groupA->chapters()->attach($ch->id, ['sequence_order' => $i]);
        }
        $intermediateChapters = Chapter::where('level','intermediate')->orderBy('sort_order')->get();
        foreach ($intermediateChapters as $i => $ch) {
            $groupB->chapters()->attach($ch->id, ['sequence_order' => $i]);
        }

        $this->command->info('✅ Chess Academy seeded! Login: admin/admin123, coach1/coach123, demo student: ARCHERCHESS01/ARCHERCHESS');
    }
}

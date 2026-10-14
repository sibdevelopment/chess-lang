<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = CarbonImmutable::now('UTC');

        $flags = [
            ['key' => 'archer_stars_enabled', 'name' => 'Archer Stars', 'enabled' => true],
            ['key' => 'archer_levels_enabled', 'name' => 'Archer Levels', 'enabled' => true],
            ['key' => 'archer_badges_enabled', 'name' => 'Archer Badges', 'enabled' => true],
            ['key' => 'archer_missions_enabled', 'name' => 'Archer Missions', 'enabled' => true],
            ['key' => 'archer_monthly_challenge_enabled', 'name' => 'Monthly Challenge', 'enabled' => true],
            ['key' => 'archer_leaderboard_enabled', 'name' => 'Archer Leaderboards', 'enabled' => true],
            ['key' => 'archer_passport_enabled', 'name' => 'Archer Passport', 'enabled' => true],
            ['key' => 'archer_notifications_enabled', 'name' => 'Achievement Notifications', 'enabled' => true],
            ['key' => 'archer_ratings_enabled', 'name' => 'Archer FIDE-style Ratings', 'enabled' => true],
            ['key' => 'archer_discovery_enabled', 'name' => 'Archer Discovery', 'enabled' => true],
            ['key' => 'archer_houses_enabled', 'name' => 'Archer Houses', 'enabled' => false],
            ['key' => 'archer_referral_enabled', 'name' => 'Refer a Friend', 'enabled' => false],
        ];

        foreach ($flags as $flag) {
            DB::table('feature_flags')->updateOrInsert(
                ['key' => $flag['key']],
                [
                    'name' => $flag['name'],
                    'description' => 'Controls the '.$flag['name'].' experience.',
                    'is_enabled' => $flag['enabled'],
                    'rollout_percentage' => 100,
                    'config' => json_encode([]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $levels = [
            ['code' => 'beginner', 'name' => 'Beginner', 'description' => 'The first step in the Archer Journey.', 'minimum_stars' => 0, 'icon' => '🌱', 'sort_order' => 1],
            ['code' => 'intermediate', 'name' => 'Intermediate', 'description' => 'Growing confidence and consistency.', 'minimum_stars' => 200, 'icon' => '♟', 'sort_order' => 2],
            ['code' => 'advanced', 'name' => 'Advanced', 'description' => 'Strong competitive chess habits.', 'minimum_stars' => 500, 'icon' => '🏆', 'sort_order' => 3],
            ['code' => 'competitive', 'name' => 'Competitive', 'description' => 'A committed Archer competitor.', 'minimum_stars' => 1000, 'icon' => '👑', 'sort_order' => 4],
        ];

        foreach ($levels as $level) {
            DB::table('archer_levels')->updateOrInsert(
                ['code' => $level['code']],
                $level + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $rules = [
            ['code' => 'class_attendance', 'event_type' => 'CLASS_ATTENDED', 'name' => 'Class attendance', 'stars' => 5, 'frequency' => 'once_per_source'],
            ['code' => 'homework_completed', 'event_type' => 'HOMEWORK_COMPLETED', 'name' => 'Homework completed', 'stars' => 5, 'frequency' => 'once_per_source'],
            ['code' => 'weekly_puzzle_completed', 'event_type' => 'PUZZLE_COMPLETED', 'name' => 'Puzzle completed', 'stars' => 3, 'frequency' => 'once_per_source', 'daily_limit' => 3],
            ['code' => 'tournament_participation', 'event_type' => 'TOURNAMENT_PARTICIPATED', 'name' => 'Tournament participation', 'stars' => 10, 'frequency' => 'once_per_source'],
            ['code' => 'tournament_game_win', 'event_type' => 'TOURNAMENT_WON', 'name' => 'Tournament game win', 'stars' => 5, 'frequency' => 'once_per_source'],
            ['code' => 'coach_achievement', 'event_type' => 'ACHIEVEMENT_CREATED', 'name' => 'Sportsmanship or coach achievement', 'stars' => 5, 'frequency' => 'once_per_source', 'daily_limit' => 3, 'weekly_limit' => 10, 'monthly_limit' => 30],
            ['code' => 'monthly_challenge', 'event_type' => 'MONTHLY_CHALLENGE_COMPLETED', 'name' => 'Monthly challenge completed', 'stars' => 10, 'frequency' => 'once_per_source'],
            ['code' => 'monthly_attendance', 'event_type' => 'MONTHLY_ATTENDANCE_100', 'name' => '100% monthly attendance', 'stars' => 15, 'frequency' => 'once_per_source'],
            ['code' => 'mission_completed', 'event_type' => 'MISSION_COMPLETED', 'name' => 'Mission completed', 'stars' => 0, 'frequency' => 'once_per_source'],
        ];

        foreach ($rules as $rule) {
            DB::table('archer_reward_rules')->updateOrInsert(
                ['code' => $rule['code']],
                [
                    'event_type' => $rule['event_type'],
                    'name' => $rule['name'],
                    'description' => 'Automatically awarded from existing LMS activity.',
                    'stars' => $rule['stars'],
                    'frequency' => $rule['frequency'],
                    'daily_limit' => $rule['daily_limit'] ?? null,
                    'weekly_limit' => $rule['weekly_limit'] ?? null,
                    'monthly_limit' => $rule['monthly_limit'] ?? null,
                    'criteria' => json_encode([]),
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $badges = [
            ['code' => 'first_step', 'name' => 'First Step', 'description' => 'Complete the first Archer class.', 'icon' => '🏅', 'event' => 'CLASS_ATTENDED', 'target' => 1],
            ['code' => 'first_checkmate', 'name' => 'First Checkmate', 'description' => 'Record the first tournament checkmate.', 'icon' => '♟', 'event' => 'TOURNAMENT_CHECKMATE', 'target' => 1],
            ['code' => 'puzzle_hunter', 'name' => 'Puzzle Hunter', 'description' => 'Complete 50 puzzles.', 'icon' => '🧩', 'event' => 'PUZZLE_COMPLETED', 'target' => 50],
            ['code' => 'tournament_archer', 'name' => 'Tournament Archer', 'description' => 'Participate in the first tournament.', 'icon' => '🏆', 'event' => 'TOURNAMENT_PARTICIPATED', 'target' => 1],
            ['code' => 'rising_star', 'name' => 'Rising Star', 'description' => 'Improve an Archer chess rating by 100 points.', 'icon' => '🔥', 'event' => 'RATING_IMPROVED', 'target' => 100],
            ['code' => 'archer_champion', 'name' => 'Archer Champion', 'description' => 'Win 10 tournament games.', 'icon' => '🏆', 'event' => 'TOURNAMENT_WON', 'target' => 10],
            ['code' => 'global_archer', 'name' => 'Global Archer', 'description' => 'Participate in an international Archer event.', 'icon' => '🌎', 'event' => 'INTERNATIONAL_EVENT_PARTICIPATED', 'target' => 1],
            ['code' => 'one_hundred_classes', 'name' => '100 Classes', 'description' => 'Attend 100 Archer classes.', 'icon' => '⭐', 'event' => 'CLASS_ATTENDED', 'target' => 100],
        ];

        foreach ($badges as $badge) {
            DB::table('archer_badges')->updateOrInsert(
                ['code' => $badge['code']],
                [
                    'name' => $badge['name'],
                    'description' => $badge['description'],
                    'icon' => $badge['icon'],
                    'criteria_event_type' => $badge['event'],
                    'criteria_target' => $badge['target'],
                    'criteria' => json_encode([]),
                    'is_automatic' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $weekStart = $now->startOfWeek()->startOfDay();
        $weekEnd = $now->endOfWeek()->endOfDay();
        $missions = [
            ['name' => 'Puzzle Mission', 'description' => 'Solve 10 puzzles this week.', 'icon' => '🧩', 'event' => 'PUZZLE_COMPLETED', 'target' => 10, 'reward' => 15],
            ['name' => 'Attendance Mission', 'description' => 'Attend 3 classes this week.', 'icon' => '🏹', 'event' => 'CLASS_ATTENDED', 'target' => 3, 'reward' => 15],
            ['name' => 'Tournament Mission', 'description' => 'Play 3 tournament games this week.', 'icon' => '🏆', 'event' => 'TOURNAMENT_GAME_PLAYED', 'target' => 3, 'reward' => 15],
            ['name' => 'Homework Mission', 'description' => 'Complete assigned homework this week.', 'icon' => '📘', 'event' => 'HOMEWORK_COMPLETED', 'target' => 1, 'reward' => 15],
            ['name' => 'Endgame Mission', 'description' => 'Complete an endgame puzzle challenge.', 'icon' => '♔', 'event' => 'ENDGAME_PUZZLE_COMPLETED', 'target' => 1, 'reward' => 20],
        ];

        foreach ($missions as $mission) {
            DB::table('archer_missions')->insert([
                'name' => $mission['name'],
                'description' => $mission['description'],
                'icon' => $mission['icon'],
                'starts_at' => $weekStart,
                'ends_at' => $weekEnd,
                'criteria_event_type' => $mission['event'],
                'target' => $mission['target'],
                'reward_stars' => $mission['reward'],
                'criteria' => json_encode([]),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $monthStart = $now->startOfMonth()->startOfDay();
        $monthEnd = $now->endOfMonth()->endOfDay();
        $monthlyBadgeCode = $monthStart->format('Y-m').'-challenge';
        DB::table('archer_badges')->updateOrInsert(
            ['code' => $monthlyBadgeCode],
            [
                'name' => $monthStart->format('F Y').' Archer',
                'description' => 'Completed the '.$monthStart->format('F Y').' Archer Challenge.',
                'icon' => '🏹',
                'criteria_event_type' => null,
                'criteria_target' => 1,
                'criteria' => json_encode([]),
                'is_automatic' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $monthlyBadgeId = DB::table('archer_badges')->where('code', $monthlyBadgeCode)->value('id');
        DB::table('archer_monthly_challenges')->insert([
            'code' => $monthStart->format('Y-m').'-challenge',
            'name' => strtoupper($monthStart->format('F')).' Challenge',
            'description' => 'Build a balanced month of classes, puzzles, tournaments and homework.',
            'icon' => '🏹',
            'month_start' => $monthStart->toDateString(),
            'month_end' => $monthEnd->toDateString(),
            'criteria' => json_encode([
                'CLASS_ATTENDED' => 8,
                'PUZZLE_COMPLETED' => 10,
                'TOURNAMENT_PARTICIPATED' => 1,
                'HOMEWORK_COMPLETED' => 1,
            ]),
            'reward_stars' => 25,
            'badge_id' => $monthlyBadgeId,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['lion', 'Lion', '🦁', '#d97706'],
            ['wolf', 'Wolf', '🐺', '#64748b'],
            ['eagle', 'Eagle', '🦅', '#2563eb'],
            ['dragon', 'Dragon', '🐉', '#dc2626'],
        ] as [$code, $name, $icon, $color]) {
            DB::table('archer_houses')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'icon' => $icon,
                    'color' => $color,
                    'description' => $name.' House',
                    'is_active' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('archer_monthly_challenges')->where('code', 'like', '%-challenge')->delete();
        DB::table('archer_missions')->whereIn('name', [
            'Puzzle Mission',
            'Attendance Mission',
            'Tournament Mission',
            'Homework Mission',
            'Endgame Mission',
        ])->delete();
        DB::table('archer_houses')->whereIn('code', ['lion', 'wolf', 'eagle', 'dragon'])->delete();
        DB::table('archer_badges')->whereIn('code', [
            'first_step',
            'first_checkmate',
            'puzzle_hunter',
            'tournament_archer',
            'rising_star',
            'archer_champion',
            'global_archer',
            'one_hundred_classes',
        ])->delete();
        DB::table('archer_badges')->where('code', 'like', '%-challenge')->delete();
        DB::table('archer_reward_rules')->whereIn('code', [
            'class_attendance',
            'homework_completed',
            'weekly_puzzle_completed',
            'tournament_participation',
            'tournament_game_win',
            'coach_achievement',
            'monthly_challenge',
            'monthly_attendance',
            'mission_completed',
        ])->delete();
        DB::table('archer_levels')->whereIn('code', ['beginner', 'intermediate', 'advanced', 'competitive'])->delete();
        DB::table('feature_flags')->where('key', 'like', 'archer_%')->delete();
    }
};

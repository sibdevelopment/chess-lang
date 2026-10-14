<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArcherBadge;
use App\Models\ArcherHouse;
use App\Models\ArcherLevel;
use App\Models\ArcherMission;
use App\Models\ArcherMonthlyChallenge;
use App\Models\ArcherRewardRule;
use App\Models\ArcherStarTransaction;
use App\Models\FeatureFlag;
use App\Models\StudentChessRating;
use App\Models\User;
use App\Services\ArcherJourney\ArcherJourneyEngine;
use App\Services\ArcherJourney\ArcherJourneyProcessor;
use App\Services\ArcherJourney\BadgeAwardService;
use App\Services\ArcherJourney\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArcherJourneyController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->string('tab')->toString(), ['overview', 'rewards', 'levels', 'badges', 'missions', 'challenge', 'ratings', 'houses', 'audit'], true)
            ? $request->string('tab')->toString()
            : 'overview';

        $studentSearch = trim($request->string('student_search')->toString());
        $students = User::students()->active()
            ->when($studentSearch !== '', fn ($query) => $query->where(function ($search) use ($studentSearch) {
                $search->where('name', 'like', '%'.$studentSearch.'%')
                    ->orWhere('username', 'like', '%'.$studentSearch.'%');
            }))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'username']);

        return view('admin.archer-journey.index', [
            'tab' => $tab,
            'flags' => FeatureFlag::orderBy('name')->get(),
            'rules' => ArcherRewardRule::orderBy('event_type')->get(),
            'levels' => ArcherLevel::orderBy('minimum_stars')->get(),
            'badges' => ArcherBadge::withCount('awards')->orderBy('name')->get(),
            'missions' => ArcherMission::latest('starts_at')->get(),
            'challenges' => ArcherMonthlyChallenge::latest('month_start')->get(),
            'eventTypes' => ArcherJourneyEngine::eventTypes(),
            'students' => $students,
            'studentSearch' => $studentSearch,
            'houses' => ArcherHouse::orderBy('name')->get(),
            'houseMemberships' => DB::table('archer_house_memberships')->pluck('house_id', 'user_id'),
            'ratings' => StudentChessRating::with('student')->orderByDesc('updated_at')->paginate(25),
            'transactions' => ArcherStarTransaction::with(['student', 'rule', 'actor'])->latest('occurred_at')->paginate(30),
            'metrics' => [
                'total_stars' => (int) DB::table('archer_star_transactions')->sum('stars'),
                'today_stars' => (int) DB::table('archer_star_transactions')->whereDate('occurred_at', now()->utc()->toDateString())->sum('stars'),
                'active_missions' => ArcherMission::activeNow()->count(),
                'badges_earned' => (int) DB::table('archer_badge_awards')->count(),
                'students_engaged' => (int) DB::table('archer_star_balances')->where('lifetime_stars', '>', 0)->count(),
            ],
            'topStudents' => DB::table('archer_star_balances')
                ->join('users', 'users.id', '=', 'archer_star_balances.user_id')
                ->select('users.name', 'users.country', 'archer_star_balances.lifetime_stars')
                ->orderByDesc('archer_star_balances.lifetime_stars')->limit(8)->get(),
        ]);
    }

    public function updateFlag(Request $request, FeatureFlag $featureFlag)
    {
        $data = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'rollout_percentage' => 'required|integer|min:0|max:100',
        ]);
        $featureFlag->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'rollout_percentage' => $data['rollout_percentage'],
        ]);
        app(FeatureFlagService::class)->forget();

        return back()->with('success', 'Feature flag updated.');
    }

    public function storeRule(Request $request)
    {
        $data = $this->ruleData($request);
        $data['code'] = $this->uniqueCode(ArcherRewardRule::class, $data['name']);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        ArcherRewardRule::create($data);

        return back()->with('success', 'Reward rule created.');
    }

    public function updateRule(Request $request, ArcherRewardRule $rule)
    {
        $rule->update($this->ruleData($request) + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Reward rule updated.');
    }

    public function updateLevel(Request $request, ArcherLevel $level)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'minimum_stars' => 'required|integer|min:0|max:10000000',
            'minimum_rating' => 'nullable|integer|min:100|max:4000',
            'icon' => 'nullable|string|max:32',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $level->update($data);

        return back()->with('success', 'Archer level updated.');
    }

    public function storeBadge(Request $request)
    {
        $data = $this->badgeData($request);
        $data['code'] = $this->uniqueCode(ArcherBadge::class, $data['name']);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        ArcherBadge::create($data);

        return back()->with('success', 'Badge created.');
    }

    public function updateBadge(Request $request, ArcherBadge $badge)
    {
        $badge->update($this->badgeData($request) + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Badge updated.');
    }

    public function awardBadge(Request $request, ArcherBadge $badge, BadgeAwardService $badges)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'note' => 'nullable|string|max:1000',
        ]);
        $student = User::students()->active()->findOrFail($data['user_id']);
        $badges->award($badge, $student, null, $request->user(), $data['note'] ?? 'Awarded manually by administrator.');

        return back()->with('success', 'Badge awarded to '.$student->name.'.');
    }

    public function destroyBadge(ArcherBadge $badge)
    {
        if ($badge->awards()->exists()) {
            $badge->update(['is_active' => false]);

            return back()->with('success', 'Earned badge archived to preserve student history.');
        }

        $badge->delete();

        return back()->with('success', 'Badge deleted.');
    }

    public function storeMission(Request $request)
    {
        ArcherMission::create($this->missionData($request) + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Mission created.');
    }

    public function updateMission(Request $request, ArcherMission $mission)
    {
        $mission->update($this->missionData($request) + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Mission updated.');
    }

    public function destroyMission(ArcherMission $mission)
    {
        $mission->update(['is_active' => false]);

        return back()->with('success', 'Mission archived.');
    }

    public function storeChallenge(Request $request)
    {
        $data = $this->challengeData($request);
        $data['code'] = $this->uniqueCode(ArcherMonthlyChallenge::class, $data['name']);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        ArcherMonthlyChallenge::create($data);

        return back()->with('success', 'Monthly challenge created.');
    }

    public function updateChallenge(Request $request, ArcherMonthlyChallenge $challenge)
    {
        $challenge->update($this->challengeData($request) + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Monthly challenge updated.');
    }

    public function destroyChallenge(ArcherMonthlyChallenge $challenge)
    {
        $challenge->update(['is_active' => false]);

        return back()->with('success', 'Monthly challenge archived.');
    }

    public function importRating(Request $request, User $student)
    {
        abort_unless($student->isStudent(), 404);
        $data = $request->validate([
            'rating_type' => ['required', Rule::in(StudentChessRating::TYPES)],
            'rating' => 'required|integer|min:100|max:4000',
        ]);

        $rating = StudentChessRating::firstOrNew([
            'user_id' => $student->id,
            'rating_type' => $data['rating_type'],
        ]);
        abort_if($rating->exists && $rating->games_played > 0, 422, 'A tournament-derived rating with game history cannot be overwritten.');
        $rating->fill([
            'initial_rating' => $data['rating'],
            'rating' => $data['rating'],
            'peak_rating' => max((int) $rating->peak_rating, $data['rating']),
            'games_played' => 0,
            'is_provisional' => true,
            'ever_reached_2400' => $data['rating'] >= 2400,
            'source' => 'erp',
        ])->save();

        return back()->with('success', ucfirst($data['rating_type']).' starting rating imported.');
    }

    public function adjustStars(Request $request, User $student, ArcherJourneyProcessor $processor)
    {
        abort_unless($student->isStudent(), 404);
        $data = $request->validate([
            'stars' => 'required|integer|min:-10000|max:10000|not_in:0',
            'reason' => 'required|string|max:255',
        ]);
        $processor->adjust($student, $data['stars'], $request->user(), $data['reason']);

        return back()->with('success', 'Star adjustment recorded in the audit log.');
    }

    public function reverseTransaction(Request $request, ArcherStarTransaction $transaction, ArcherJourneyProcessor $processor)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        $processor->reverse($transaction, $request->user(), $data['reason']);

        return back()->with('success', 'Transaction reversed without deleting history.');
    }

    public function updateHouse(Request $request, ArcherHouse $house)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'icon' => 'nullable|string|max:32',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $house->update($data);

        return back()->with('success', 'House settings updated.');
    }

    public function assignHouse(Request $request, ArcherHouse $house, User $student)
    {
        abort_unless($student->isStudent(), 404);
        DB::table('archer_house_memberships')->updateOrInsert(
            ['user_id' => $student->id],
            [
                'house_id' => $house->id,
                'assigned_by' => $request->user()->id,
                'joined_at' => now()->utc(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return back()->with('success', $student->name.' assigned to '.$house->name.' House.');
    }

    protected function ruleData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'event_type' => ['required', Rule::in(ArcherJourneyEngine::eventTypes())],
            'stars' => 'required|integer|min:-1000|max:1000',
            'frequency' => ['required', Rule::in(['once_per_source', 'unlimited'])],
            'daily_limit' => 'nullable|integer|min:1|max:10000',
            'weekly_limit' => 'nullable|integer|min:1|max:10000',
            'monthly_limit' => 'nullable|integer|min:1|max:100000',
            'is_enabled' => 'nullable|boolean',
        ]);
        $data['is_enabled'] = $request->boolean('is_enabled');

        return $data;
    }

    protected function badgeData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:32',
            'criteria_event_type' => ['nullable', Rule::in(ArcherJourneyEngine::eventTypes())],
            'criteria_target' => 'required|integer|min:1|max:1000000',
            'is_automatic' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_automatic'] = $request->boolean('is_automatic');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    protected function missionData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:32',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'criteria_event_type' => ['required', Rule::in(ArcherJourneyEngine::eventTypes())],
            'target' => 'required|integer|min:1|max:100000',
            'reward_stars' => 'required|integer|min:0|max:10000',
            'applicable_level_id' => 'nullable|exists:archer_levels,id',
            'applicable_country' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    protected function challengeData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:32',
            'month_start' => 'required|date',
            'month_end' => 'required|date|after_or_equal:month_start',
            'reward_stars' => 'required|integer|min:0|max:10000',
            'classes_target' => 'required|integer|min:0|max:1000',
            'puzzles_target' => 'required|integer|min:0|max:10000',
            'tournaments_target' => 'required|integer|min:0|max:1000',
            'homework_target' => 'required|integer|min:0|max:1000',
            'badge_id' => 'nullable|exists:archer_badges,id',
            'applicable_level_id' => 'nullable|exists:archer_levels,id',
            'applicable_country' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ]);
        $data['criteria'] = collect([
            ArcherJourneyEngine::CLASS_ATTENDED => $data['classes_target'],
            ArcherJourneyEngine::PUZZLE_COMPLETED => $data['puzzles_target'],
            ArcherJourneyEngine::TOURNAMENT_PARTICIPATED => $data['tournaments_target'],
            ArcherJourneyEngine::HOMEWORK_COMPLETED => $data['homework_target'],
        ])->filter(fn ($target) => $target > 0)->all();
        unset($data['classes_target'], $data['puzzles_target'], $data['tournaments_target'], $data['homework_target']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    protected function uniqueCode(string $model, string $name): string
    {
        $base = Str::slug($name, '_') ?: 'archer_item';
        $code = $base;
        $suffix = 2;
        while ($model::where('code', $code)->exists()) {
            $code = $base.'_'.$suffix++;
        }

        return $code;
    }
}

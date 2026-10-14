@extends('layouts.app')
@section('page-title', 'My Archer Journey')
@section('sidebar-nav') @include('student._sidebar') @endsection

@section('content')
<div class="ui-page-stack archer-journey">
  <section class="aj-hero">
    <div class="aj-hero__identity">
      <div class="aj-avatar" style="background:{{ $student->avatar_color }}">{{ $student->initials }}</div>
      <div>
        <div class="aj-eyebrow">🏹 My Archer Journey · Progress made simple</div>
        <h1>{{ $student->name }}'s Archer Journey</h1>
        <p>Learning, practice, consistency and competition—all in one clear progress story.</p>
        <small class="aj-hero__meta">{{ App\Models\User::studentCountryLabel($student->country) }} · {{ $student->groups->pluck('name')->join(', ') ?: 'Archer Student' }}</small>
      </div>
    </div>
    <div class="aj-level-card">
      <span>{{ $currentLevel?->icon ?: '🌱' }}</span>
      <div><small>Current Archer level</small><strong>{{ strtoupper($currentLevel?->name ?? 'Beginner') }}</strong></div>
    </div>
  </section>

  <nav class="ui-tabs ui-section-tabs" aria-label="Archer Journey sections">
    <a class="ui-tab {{ $tab === 'journey' ? 'is-active' : '' }}" href="{{ route('student.archer-journey', ['tab' => 'journey']) }}">Journey Overview</a>
    <a class="ui-tab {{ $tab === 'passport' ? 'is-active' : '' }}" href="{{ route('student.archer-journey', ['tab' => 'passport']) }}">Activity History</a>
    <a class="ui-tab {{ $tab === 'leaderboards' ? 'is-active' : '' }}" href="{{ route('student.archer-journey', ['tab' => 'leaderboards']) }}">Friendly Rankings</a>
  </nav>

  @if($tab === 'journey')
    <div class="aj-stat-grid">
      <article class="aj-stat"><span>⭐</span><div><strong>{{ number_format($balance->lifetime_stars) }}</strong><small>Total progress Stars</small></div></article>
      <article class="aj-stat"><span>📅</span><div><strong>{{ number_format($starsThisMonth) }}</strong><small>Stars earned this month</small></div></article>
      <article class="aj-stat"><span>🏅</span><div><strong>{{ $badges->count() }}</strong><small>Milestones earned</small></div></article>
      <article class="aj-stat"><span>🏆</span><div><strong>{{ $tournamentCount }}</strong><small>Tournaments played</small></div></article>
      <article class="aj-stat"><span>🔥</span><div><strong>{{ $balance->current_streak }}</strong><small>Active-day streak</small></div></article>
    </div>

    <section class="card aj-family-guide">
      <div class="aj-card-heading">
        <div><span class="aj-eyebrow">A simple guide for families</span><h2>How the Archer Journey works</h2></div>
        <span class="aj-auto-label">✓ Updated automatically</span>
      </div>
      <p class="aj-guide-intro">Every verified LMS activity builds a child's journey. Stars reward consistent effort; chess ratings separately measure tournament performance.</p>
      <div class="aj-guide-steps">
        <article><span class="aj-step-number">1</span><div><strong>Learn</strong><p>Attend a completed class</p><small>+5 Stars</small></div></article>
        <article><span class="aj-step-number">2</span><div><strong>Practise</strong><p>Finish homework or solve puzzles</p><small>+5 / +3 Stars</small></div></article>
        <article><span class="aj-step-number">3</span><div><strong>Compete</strong><p>Play tournaments, win and checkmate</p><small>+10 / +5 Stars</small></div></article>
        <article><span class="aj-step-number">4</span><div><strong>Grow</strong><p>Complete missions, earn badges and level up</p><small>Progress saved forever</small></div></article>
      </div>
      <div class="aj-trust-note"><span>🛡️</span><p><strong>Fair and verified:</strong> rewards come from completed classes, submitted homework, solved puzzles and recorded tournament results. The same activity cannot earn Stars twice.</p></div>
    </section>

    <section class="card aj-growth-card">
      <div class="aj-card-heading"><div><span class="aj-eyebrow">Whole-child progress</span><h2>Growth snapshot</h2></div><small class="text-muted">Verified lifetime activity</small></div>
      <div class="aj-growth-grid">
        <article><span>🎓</span><div><strong>{{ number_format($activityCounts->get(App\Services\ArcherJourney\ArcherJourneyEngine::CLASS_ATTENDED, 0)) }}</strong><small>Classes attended</small></div><p>Learning consistency</p></article>
        <article><span>📘</span><div><strong>{{ number_format($activityCounts->get(App\Services\ArcherJourney\ArcherJourneyEngine::HOMEWORK_COMPLETED, 0)) }}</strong><small>Homework completed</small></div><p>Independent responsibility</p></article>
        <article><span>🧩</span><div><strong>{{ number_format($activityCounts->get(App\Services\ArcherJourney\ArcherJourneyEngine::PUZZLE_COMPLETED, 0)) }}</strong><small>Puzzles solved</small></div><p>Tactical practice</p></article>
        <article><span>♟️</span><div><strong>{{ number_format($activityCounts->get(App\Services\ArcherJourney\ArcherJourneyEngine::TOURNAMENT_GAME_PLAYED, 0)) }}</strong><small>Tournament games</small></div><p>{{ number_format($activityCounts->get(App\Services\ArcherJourney\ArcherJourneyEngine::TOURNAMENT_WON, 0)) }} wins · competitive experience</p></article>
      </div>
    </section>

    <section class="card aj-path-card">
      <div class="aj-card-heading"><div><span class="aj-eyebrow">The road ahead</span><h2>Four Archer levels</h2></div><small class="text-muted">Levels are unlocked by lifetime Stars</small></div>
      <div class="aj-level-path">
        @foreach($levels as $level)
          @php
            $isCurrentLevel = $currentLevel && $level->id === $currentLevel->id;
            $state = $isCurrentLevel ? 'is-current' : ($balance->lifetime_stars >= $level->minimum_stars ? 'is-complete' : 'is-locked');
          @endphp
          <article class="{{ $state }}">
            <span class="aj-level-path__icon">{{ $level->icon ?: '🏹' }}</span>
            <div><strong>{{ $level->name }}</strong><small>{{ number_format($level->minimum_stars) }}+ Stars</small></div>
            <span class="aj-level-path__state">{{ $state === 'is-current' ? 'Current level' : ($state === 'is-complete' ? 'Completed' : 'Next milestone') }}</span>
          </article>
        @endforeach
      </div>
    </section>

    <div class="aj-two-column">
      <section class="card aj-mission-card">
        <div class="aj-card-heading"><div><span class="aj-eyebrow">Current mission</span><h2>{{ $mission?->model->icon ?? '🎯' }} {{ $mission?->model->name ?? 'New mission coming soon' }}</h2></div>@if($mission)<span class="aj-reward">+{{ $mission->model->reward_stars }} ⭐</span>@endif</div>
        @if($mission)
          <p>{{ $mission->model->description }}</p>
          <div class="aj-progress"><span style="width:{{ $mission->percent }}%"></span></div>
          <div class="aj-progress-meta"><strong>{{ $mission->progress }} / {{ $mission->model->target }}</strong><span>{{ $mission->completed ? 'Completed ✓' : $mission->percent.'%' }}</span></div>
        @else
          <p class="text-muted">Your next Archer mission will appear here.</p>
        @endif
      </section>

      <section class="card aj-milestone-card">
        <span class="aj-eyebrow">Next milestone</span>
        @if($nextLevel)
          <h2>{{ $nextLevel->icon }} {{ $nextLevel->name }} Archer</h2>
          <p><strong>{{ $starsToNextLevel }} Stars</strong> to unlock your next Archer milestone.</p>
          <div class="aj-progress aj-progress--gold"><span style="width:{{ $levelProgress }}%"></span></div>
          <div class="aj-progress-meta"><span>{{ $currentLevel?->name }}</span><span>{{ $nextLevel->name }}</span></div>
        @else
          <h2>👑 Highest milestone reached</h2><p>You are leading the Archer Journey.</p>
        @endif
      </section>
    </div>

    <section class="card">
      <div class="aj-card-heading"><div><span class="aj-eyebrow">Playing strength</span><h2>Tournament chess ratings</h2></div><small class="text-muted">Separate from Stars · calculated from LMS tournament results</small></div>
      <div class="aj-rating-grid">
        @foreach(['classical' => '♟ Classical', 'rapid' => '⚡ Rapid', 'blitz' => '🔥 Blitz'] as $type => $label)
          @php
            $rating = $ratings->get($type);
          @endphp
          <article><small>{{ $label }}</small><strong>{{ $rating?->rating ?? 1400 }}</strong><span>{{ $rating?->is_provisional ?? true ? 'Provisional' : ($rating?->games_played ?? 0).' rated games' }}</span></article>
        @endforeach
      </div>
    </section>

    @if($challenge)
    <section class="card aj-challenge-card">
      <div class="aj-card-heading"><div><span class="aj-eyebrow">Archer monthly challenge</span><h2>{{ $challenge->model->icon }} {{ $challenge->model->name }}</h2></div><span class="aj-reward">+{{ $challenge->model->reward_stars }} ⭐</span></div>
      <div class="aj-challenge-grid">
        @foreach($challenge->model->criteria as $eventType => $target)
          @php
            $done = (int) ($challenge->progress[$eventType] ?? 0);
          @endphp
          <div class="aj-challenge-item {{ $done >= $target ? 'is-complete' : '' }}"><span>{{ $done >= $target ? '✓' : '○' }}</span><div><strong>{{ str($eventType)->replace('_', ' ')->title() }}</strong><small>{{ min($done, $target) }} / {{ $target }}</small></div></div>
        @endforeach
      </div>
    </section>
    @endif

    <div class="aj-two-column">
      <section class="card">
        <div class="aj-card-heading"><h2>Badges</h2><span class="text-muted text-sm">{{ $badges->count() }} earned</span></div>
        <div class="aj-badge-grid">
          @forelse($badges->take(8) as $award)
            <article title="{{ $award->badge->description }}"><span>{{ $award->badge->icon ?: '🏅' }}</span><strong>{{ $award->badge->name }}</strong><small>{{ $award->earned_at?->timezone($student->preferredTimezone())->format('d M Y') }}</small></article>
          @empty
            <div class="empty">Complete your first activity to earn a badge.</div>
          @endforelse
        </div>
      </section>

      <section class="card">
        <div class="aj-card-heading"><h2>Achievement inbox</h2>@if($notifications->whereNull('read_at')->isNotEmpty())<form method="POST" action="{{ route('student.archer-journey.notifications.read') }}">@csrf<button class="btn btn-xs btn-ghost">Mark read</button></form>@endif</div>
        <div class="aj-notification-list">
          @forelse($notifications as $notification)
            <article class="{{ $notification->read_at ? '' : 'is-unread' }}"><div><strong>{{ $notification->title }}</strong><p>{{ $notification->message }}</p></div><small>{{ $notification->created_at->diffForHumans() }}</small></article>
          @empty
            <div class="empty">Your Archer achievements will appear here.</div>
          @endforelse
        </div>
        <details class="aj-preferences">
          <summary>Notification preferences</summary>
          <form method="POST" action="{{ route('student.archer-journey.notifications.preferences') }}">
            @csrf @method('PUT')
            @foreach(['in_app_enabled' => 'Achievement inbox', 'level_updates' => 'Level updates', 'badge_updates' => 'New badges', 'mission_updates' => 'Mission updates', 'tournament_updates' => 'Tournament updates'] as $field => $label)
              <label><input type="checkbox" name="{{ $field }}" value="1" @checked($notificationPreferences->{$field})> {{ $label }}</label>
            @endforeach
            <button class="btn btn-xs">Save preferences</button>
          </form>
        </details>
      </section>
    </div>
  @elseif($tab === 'passport')
    <section class="card aj-passport">
      <div class="aj-card-heading"><div><span class="aj-eyebrow">Archer Passport · A clear record for families</span><h2>🏹 Verified Activity History</h2><p>Classes, practice, tournament milestones and achievements are saved here in date order.</p></div><span class="badge badge-active">{{ $passport->total() }} entries</span></div>
      <div class="aj-timeline">
        @forelse($passport as $entry)
          <article><div class="aj-timeline__icon">{{ $entry->icon ?: '🏹' }}</div><div><small>{{ $entry->occurred_at?->timezone($student->preferredTimezone())->format('d M Y · g:i A') }}</small><h3>{{ $entry->title }}</h3>@if($entry->description)<p>{{ $entry->description }}</p>@endif</div></article>
        @empty
          <div class="empty">Your Archer Passport begins with your next activity.</div>
        @endforelse
      </div>
      {{ $passport->withQueryString()->links() }}
    </section>
  @else
    @php
      $boardOptions = [
        'stars' => ['⭐ Overall Progress', 'Stars', 'Consistent effort across all Archer activities'],
        'tournament' => ['🏆 Tournament Wins', 'Wins', 'Completed tournament games won'],
        'improved' => ['📈 Rating Growth', 'Points gained', 'Best improvement from the starting chess rating'],
        'puzzle' => ['🧩 Puzzle Practice', 'Puzzles', 'Verified puzzles completed'],
        'achievement' => ['🏅 Coach Recognition', 'Recognitions', 'Positive achievements recorded by coaches'],
      ];
      $scopeOptions = [
        'global' => ['All Archer Students', 'Compares with every active student in the academy.'],
        'country' => ['Same Country', 'Compares only with students from the same country.'],
        'level' => ['Same Journey Level', 'Compares with students at the same Beginner, Intermediate, Advanced or Competitive level.'],
        'class' => ['My Batchmates', 'Compares only with students who share the same batch.'],
      ];
      $activeBoard = $boardOptions[$board] ?? $boardOptions['stars'];
      $activeScope = $scopeOptions[$scope] ?? $scopeOptions['global'];
    @endphp
    <section class="card aj-leaderboard">
      <div class="aj-card-heading"><div><span class="aj-eyebrow">Leaderboards · Positive motivation, not pressure</span><h2>🌎 Friendly Rankings</h2><p>Choose what to compare and who to compare with. Rankings never reduce Stars, ratings or rewards.</p></div></div>
      <div class="aj-filter-label"><strong>1. What would you like to compare?</strong><span>Each view measures one clear area.</span></div>
      <div class="aj-filter-row aj-metric-filters">
        @foreach($boardOptions as $key => [$label, $scoreLabel, $description])
          <a class="{{ $board === $key ? 'is-active' : '' }}" href="{{ route('student.archer-journey', ['tab' => 'leaderboards', 'board' => $key, 'scope' => $scope]) }}"><strong>{{ $label }}</strong><small>{{ $description }}</small></a>
        @endforeach
      </div>
      <div class="aj-filter-label"><strong>2. Choose the comparison group</strong><span>This changes the students shown—not the score.</span></div>
      <div class="aj-filter-row aj-filter-row--scope">
        @foreach($scopeOptions as $key => [$label, $description])
          <a class="{{ $scope === $key ? 'is-active' : '' }}" href="{{ route('student.archer-journey', ['tab' => 'leaderboards', 'board' => $board, 'scope' => $key]) }}">{{ $label }}</a>
        @endforeach
      </div>
      <div class="aj-ranking-context"><span>{{ str($board)->is('stars') ? '⭐' : (str($board)->is('tournament') ? '🏆' : (str($board)->is('improved') ? '📈' : (str($board)->is('puzzle') ? '🧩' : '🏅'))) }}</span><div><small>Showing</small><strong>{{ $activeBoard[0] }} · {{ $activeScope[0] }}</strong><p>{{ $activeScope[1] }}</p></div></div>
      <div class="table-wrap">
        <table><thead><tr><th>Rank</th><th>Student</th><th>Country</th><th style="text-align:right">{{ $activeBoard[1] }}</th></tr></thead><tbody>
        @foreach($leaders as $leader)
          <tr class="{{ $leader->is_viewer ? 'aj-is-me' : '' }}"><td><strong>#{{ $leader->rank }}</strong></td><td>{{ $leader->display_name }} @if($leader->is_viewer)<span class="badge badge-active">You</span>@endif</td><td>{{ App\Models\User::studentCountryLabel($leader->country) }}</td><td style="text-align:right"><strong>{{ number_format($leader->score) }}</strong></td></tr>
        @endforeach
        </tbody></table>
      </div>
      <p class="aj-privacy-note">🔒 Student privacy is protected: only the first name and surname initial are shown. Use rankings as encouragement; the Journey Overview gives the fuller progress picture.</p>
    </section>
  @endif
</div>
@endsection

@extends('layouts.app')
@section('page-title', 'Archer Journey')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@php
  $journeyRoutePrefix = auth()->user()->role;
  $tabs = [
    'overview' => 'Overview', 'rewards' => 'Stars & Rules', 'levels' => 'Levels', 'badges' => 'Badges',
    'missions' => 'Missions', 'challenge' => 'Monthly Challenge', 'ratings' => 'Ratings', 'houses' => 'Houses', 'audit' => 'Audit Log',
  ];
@endphp

@section('content')
<div class="ui-page-stack archer-admin">
  <x-ui.page-header title="Archer Gamification Admin" subtitle="Configure Archer Journey without changing the core LMS." />

  @if($errors->any())
    <div class="ui-form-errors"><strong>Please fix these fields:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <nav class="ui-tabs ui-section-tabs" aria-label="Archer administration">
    @foreach($tabs as $key => $label)<a class="ui-tab {{ $tab === $key ? 'is-active' : '' }}" href="{{ route($journeyRoutePrefix.'.archer-journey.index', ['tab' => $key]) }}">{{ $label }}</a>@endforeach
  </nav>

  @if(in_array($tab, ['badges', 'ratings', 'houses', 'audit'], true))
    <form method="GET" action="{{ route($journeyRoutePrefix.'.archer-journey.index') }}" class="aj-student-search">
      <input type="hidden" name="tab" value="{{ $tab }}"><label><span>Find student for this action</span><input name="student_search" value="{{ $studentSearch }}" placeholder="Name or Archer username"></label><button class="btn">Search</button>
      @if($studentSearch !== '')<a class="btn btn-ghost" href="{{ route($journeyRoutePrefix.'.archer-journey.index', ['tab' => $tab]) }}">Clear</a>@endif
      <small>{{ $students->count() }} matching students shown (maximum 100)</small>
    </form>
  @endif

  @if($tab === 'overview')
    <div class="aj-stat-grid">
      <article class="aj-stat"><span>⭐</span><div><strong>{{ number_format($metrics['total_stars']) }}</strong><small>Total Stars awarded</small></div></article>
      <article class="aj-stat"><span>✨</span><div><strong>{{ number_format($metrics['today_stars']) }}</strong><small>Stars today</small></div></article>
      <article class="aj-stat"><span>🎯</span><div><strong>{{ $metrics['active_missions'] }}</strong><small>Active missions</small></div></article>
      <article class="aj-stat"><span>🏅</span><div><strong>{{ number_format($metrics['badges_earned']) }}</strong><small>Badges earned</small></div></article>
      <article class="aj-stat"><span>🏹</span><div><strong>{{ number_format($metrics['students_engaged']) }}</strong><small>Students engaged</small></div></article>
    </div>

    <div class="aj-two-column">
      <section class="card">
        <div class="card-title">Feature flags</div>
        <div class="aj-admin-stack">
          @foreach($flags as $flag)
            <form class="aj-inline-control" method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.flags.update', $flag) }}">
              @csrf @method('PUT')
              <div><strong>{{ $flag->name }}</strong><small>{{ $flag->description }}</small></div>
              <label class="aj-switch"><input type="checkbox" name="is_enabled" value="1" @checked($flag->is_enabled)><span>{{ $flag->is_enabled ? 'On' : 'Off' }}</span></label>
              <label><span>Rollout</span><input class="ui-input" type="number" name="rollout_percentage" value="{{ $flag->rollout_percentage }}" min="0" max="100"></label>
              <button class="btn btn-sm">Save</button>
            </form>
          @endforeach
        </div>
      </section>
      <section class="card">
        <div class="card-title">Top Archers</div>
        <div class="table-wrap"><table><thead><tr><th>Student</th><th>Country</th><th style="text-align:right">Stars</th></tr></thead><tbody>
          @forelse($topStudents as $student)<tr><td class="font-medium">{{ $student->name }}</td><td>{{ App\Models\User::studentCountryLabel($student->country) }}</td><td style="text-align:right"><strong>{{ number_format($student->lifetime_stars) }} ⭐</strong></td></tr>@empty<tr><td colspan="3"><div class="empty">Activity will appear automatically.</div></td></tr>@endforelse
        </tbody></table></div>
      </section>
    </div>
  @elseif($tab === 'rewards')
    <section class="card">
      <div class="aj-card-heading"><div><h2>Reward rules</h2><p class="text-muted text-sm">Each LMS event is idempotent. Limits use the student's local calendar.</p></div></div>
      <div class="aj-admin-stack">
        @foreach($rules as $rule)
          <details class="aj-editor"><summary><span><strong>{{ $rule->name }}</strong><small>{{ $rule->event_type }} · {{ $rule->stars >= 0 ? '+' : '' }}{{ $rule->stars }} Stars</small></span><span class="badge {{ $rule->is_enabled ? 'badge-active' : 'badge-cancelled' }}">{{ $rule->is_enabled ? 'Active' : 'Off' }}</span></summary>
            <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.rules.update', $rule) }}" class="aj-form-grid">@csrf @method('PUT')
              @include('admin.archer-journey.partials.rule-fields', ['item' => $rule])
              <div class="aj-form-span"><button class="btn btn-primary">Update rule</button></div>
            </form>
          </details>
        @endforeach
        <details class="aj-editor"><summary><span><strong>＋ Create reward rule</strong><small>Add a configurable rule for any Archer event</small></span></summary>
          <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.rules.store') }}" class="aj-form-grid">@csrf
            @include('admin.archer-journey.partials.rule-fields', ['item' => null])
            <div class="aj-form-span"><button class="btn btn-primary">Create rule</button></div>
          </form>
        </details>
      </div>
    </section>
  @elseif($tab === 'levels')
    <section class="card"><div class="card-title">Parent-friendly Archer levels</div><div class="aj-admin-stack">
      @foreach($levels as $level)
        <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.levels.update', $level) }}" class="aj-editor aj-form-grid">@csrf @method('PUT')
          <label><span>Name</span><input name="name" value="{{ $level->name }}" required></label><label><span>Icon</span><input name="icon" value="{{ $level->icon }}"></label>
          <label><span>Minimum Stars</span><input type="number" name="minimum_stars" value="{{ $level->minimum_stars }}" min="0" required></label><label><span>Optional rating reference</span><input type="number" name="minimum_rating" value="{{ $level->minimum_rating }}" min="100" max="4000"></label>
          <label class="aj-form-span"><span>Description</span><textarea name="description" rows="2">{{ $level->description }}</textarea></label>
          <label class="aj-check"><input type="checkbox" name="is_active" value="1" @checked($level->is_active)> Active</label><div><button class="btn btn-primary">Save {{ $level->name }}</button></div>
        </form>
      @endforeach
    </div></section>
  @elseif($tab === 'badges')
    <section class="card"><div class="card-title">Award a badge manually</div><form method="POST" class="aj-form-grid" onsubmit="this.action=this.action.replace('__BADGE__', this.badge_id.value)">@csrf<label><span>Badge</span><select name="badge_id" required><option value="">Select badge</option>@foreach($badges as $badge)<option value="{{ $badge->id }}">{{ $badge->icon }} {{ $badge->name }}</option>@endforeach</select></label><label><span>Student</span><select name="user_id" required><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select></label><label class="aj-form-span"><span>Optional note</span><input name="note" maxlength="1000"></label><div><button class="btn btn-primary">Award badge</button></div><script>document.currentScript.parentElement.action=@json(route($journeyRoutePrefix.'.archer-journey.badges.award', ['badge' => '__BADGE__']));</script></form></section>
    <section class="card"><div class="card-title">Badges</div><div class="aj-admin-stack">
      @foreach($badges as $badge)
        <details class="aj-editor"><summary><span><strong>{{ $badge->icon }} {{ $badge->name }}</strong><small>{{ $badge->criteria_event_type ?: 'Manual' }} · target {{ $badge->criteria_target }} · {{ $badge->awards_count }} earned</small></span><span class="badge {{ $badge->is_active ? 'badge-active' : 'badge-cancelled' }}">{{ $badge->is_active ? 'Active' : 'Off' }}</span></summary>
          <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.badges.update', $badge) }}" class="aj-form-grid">@csrf @method('PUT') @include('admin.archer-journey.partials.badge-fields', ['item' => $badge])<div><button class="btn btn-primary">Update badge</button></div></form>
          <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.badges.destroy', $badge) }}" onsubmit="return confirm('Archive or delete this badge?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Archive badge</button></form>
        </details>
      @endforeach
      <details class="aj-editor"><summary><span><strong>＋ Create badge</strong><small>Automatic or manual student recognition</small></span></summary><form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.badges.store') }}" class="aj-form-grid">@csrf @include('admin.archer-journey.partials.badge-fields', ['item' => null])<div><button class="btn btn-primary">Create badge</button></div></form></details>
    </div></section>
  @elseif($tab === 'missions')
    <section class="card"><div class="card-title">Weekly and custom missions</div><div class="aj-admin-stack">
      @foreach($missions as $mission)
        <details class="aj-editor"><summary><span><strong>{{ $mission->icon }} {{ $mission->name }}</strong><small>{{ $mission->criteria_event_type }} · {{ $mission->target }} target · +{{ $mission->reward_stars }} Stars</small></span><span class="badge {{ $mission->is_active ? 'badge-active' : 'badge-cancelled' }}">{{ $mission->is_active ? 'Active' : 'Off' }}</span></summary>
          <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.missions.update', $mission) }}" class="aj-form-grid">@csrf @method('PUT') @include('admin.archer-journey.partials.mission-fields', ['item' => $mission])<div><button class="btn btn-primary">Update mission</button></div></form>
          <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.missions.destroy', $mission) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Archive mission</button></form>
        </details>
      @endforeach
      <details class="aj-editor"><summary><span><strong>＋ Create mission</strong><small>Automatically track an existing LMS activity</small></span></summary><form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.missions.store') }}" class="aj-form-grid">@csrf @include('admin.archer-journey.partials.mission-fields', ['item' => null])<div><button class="btn btn-primary">Create mission</button></div></form></details>
    </div></section>
  @elseif($tab === 'challenge')
    <section class="card"><div class="card-title">Monthly challenges</div><div class="aj-admin-stack">
      @foreach($challenges as $challenge)
        <details class="aj-editor"><summary><span><strong>{{ $challenge->icon }} {{ $challenge->name }}</strong><small>{{ $challenge->month_start->format('d M') }} – {{ $challenge->month_end->format('d M Y') }} · +{{ $challenge->reward_stars }} Stars</small></span></summary>
          <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.challenges.update', $challenge) }}" class="aj-form-grid">@csrf @method('PUT') @include('admin.archer-journey.partials.challenge-fields', ['item' => $challenge])<div><button class="btn btn-primary">Update challenge</button></div></form>
          <form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.challenges.destroy', $challenge) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Archive challenge</button></form>
        </details>
      @endforeach
      <details class="aj-editor"><summary><span><strong>＋ Create monthly challenge</strong><small>Combine classes, puzzles, tournaments and homework</small></span></summary><form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.challenges.store') }}" class="aj-form-grid">@csrf @include('admin.archer-journey.partials.challenge-fields', ['item' => null])<div><button class="btn btn-primary">Create challenge</button></div></form></details>
    </div></section>
  @elseif($tab === 'ratings')
    <section class="card"><div class="aj-card-heading"><div><h2>Tournament ratings</h2><p class="text-sm text-muted">Separate Classical, Rapid and Blitz ratings. Import starting values only before rated games begin.</p></div></div>
      <form method="POST" id="rating-import-form" class="aj-form-grid" onsubmit="this.action=this.action.replace('__STUDENT__', this.student_id.value)">@csrf @method('PUT')
        <input type="hidden" name="_base" value="1"><label><span>Student</span><select name="student_id" required><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }} ({{ $student->username }})</option>@endforeach</select></label>
        <label><span>Rating type</span><select name="rating_type"><option value="classical">Classical</option><option value="rapid">Rapid</option><option value="blitz">Blitz</option></select></label><label><span>ERP starting rating</span><input type="number" name="rating" min="100" max="4000" value="1400" required></label><div><button class="btn btn-primary">Import starting rating</button></div>
      </form>
      <script>document.getElementById('rating-import-form').action=@json(route($journeyRoutePrefix.'.archer-journey.ratings.import', ['student' => '__STUDENT__']));</script>
      <div class="table-wrap mt-4"><table><thead><tr><th>Student</th><th>Type</th><th>Start</th><th>Current</th><th>Peak</th><th>Games</th><th>Source</th></tr></thead><tbody>@forelse($ratings as $rating)<tr><td>{{ $rating->student->name }}</td><td>{{ ucfirst($rating->rating_type) }}</td><td>{{ $rating->initial_rating }}</td><td><strong>{{ $rating->rating }}</strong></td><td>{{ $rating->peak_rating }}</td><td>{{ $rating->games_played }}</td><td>{{ strtoupper($rating->source) }}</td></tr>@empty<tr><td colspan="7"><div class="empty">Ratings will appear after ERP import or completed tournaments.</div></td></tr>@endforelse</tbody></table></div>{{ $ratings->withQueryString()->links() }}
    </section>
  @elseif($tab === 'houses')
    <div class="aj-two-column">
      <section class="card"><div class="aj-card-heading"><div><h2>Archer Houses</h2><p class="text-sm text-muted">Foundation is ready. Houses remain hidden until the feature flag and individual houses are enabled.</p></div></div><div class="aj-admin-stack">
        @foreach($houses as $house)<form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.houses.update', $house) }}" class="aj-editor aj-form-grid">@csrf @method('PUT')<label><span>Name</span><input name="name" value="{{ $house->name }}" required></label><label><span>Icon</span><input name="icon" value="{{ $house->icon }}"></label><label><span>Colour</span><input name="color" value="{{ $house->color }}" pattern="#[0-9a-fA-F]{6}"></label><label class="aj-check"><input type="checkbox" name="is_active" value="1" @checked($house->is_active)> House active</label><label class="aj-form-span"><span>Description</span><input name="description" value="{{ $house->description }}"></label><div><button class="btn">Save {{ $house->name }}</button></div></form>@endforeach
      </div></section>
      <section class="card"><div class="card-title">Assign student</div><form method="POST" class="aj-form-grid" onsubmit="this.action=this.action.replace('__HOUSE__', this.house_id.value).replace('__STUDENT__', this.student_id.value)">@csrf @method('PUT')<label><span>Student</span><select name="student_id" required><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }} @if($houseMemberships->has($student->id))· assigned @endif</option>@endforeach</select></label><label><span>House</span><select name="house_id" required>@foreach($houses as $house)<option value="{{ $house->id }}">{{ $house->icon }} {{ $house->name }}</option>@endforeach</select></label><div><button class="btn btn-primary">Assign House</button></div><script>document.currentScript.parentElement.action=@json(route($journeyRoutePrefix.'.archer-journey.houses.assign', ['house' => '__HOUSE__', 'student' => '__STUDENT__']));</script></form></section>
    </div>
  @else
    <div class="aj-two-column">
      <section class="card"><div class="card-title">Manual adjustment</div><form method="POST" class="aj-form-grid" onsubmit="this.action=this.action.replace('__STUDENT__', this.user_id.value)">@csrf<label><span>Student</span><select name="user_id" required><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select></label><label><span>Stars (+ or −)</span><input type="number" name="stars" min="-10000" max="10000" required></label><label class="aj-form-span"><span>Audit reason</span><input name="reason" maxlength="255" required></label><div><button class="btn btn-primary">Record adjustment</button></div><script>document.currentScript.parentElement.action=@json(route($journeyRoutePrefix.'.archer-journey.stars.adjust', ['student' => '__STUDENT__']));</script></form></section>
      <section class="card"><div class="card-title">Immutable audit policy</div><p class="text-muted">Transactions are never deleted. Incorrect awards are reversed with an equal and opposite transaction, preserving the complete history.</p></section>
    </div>
    <section class="card"><div class="table-wrap"><table><thead><tr><th>When</th><th>Student</th><th>Type</th><th>Stars</th><th>Reason</th><th>Actor</th><th></th></tr></thead><tbody>
      @forelse($transactions as $transaction)<tr><td class="text-sm text-muted">{{ $transaction->occurred_at?->timezone(auth()->user()->preferredTimezone())->format('d M Y · g:i A') }}</td><td>{{ $transaction->student->name }}</td><td>{{ ucfirst($transaction->transaction_type) }}</td><td><strong class="{{ $transaction->stars >= 0 ? 'text-success' : 'text-danger' }}">{{ $transaction->stars >= 0 ? '+' : '' }}{{ $transaction->stars }}</strong></td><td>{{ $transaction->reason }}</td><td>{{ $transaction->actor?->name ?? 'Automatic' }}</td><td>@if($transaction->transaction_type !== 'reversal' && !App\Models\ArcherStarTransaction::where('reversal_of_id', $transaction->id)->exists())<form method="POST" action="{{ route($journeyRoutePrefix.'.archer-journey.transactions.reverse', $transaction) }}" onsubmit="return confirm('Reverse this transaction?')">@csrf<input type="hidden" name="reason" value="Admin correction"><button class="btn btn-xs btn-danger">Reverse</button></form>@endif</td></tr>@empty<tr><td colspan="7"><div class="empty">No Star transactions yet.</div></td></tr>@endforelse
    </tbody></table></div>{{ $transactions->withQueryString()->links() }}</section>
  @endif
</div>
@endsection

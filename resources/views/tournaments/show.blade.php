@extends('layouts.app')
@section('page-title', $tournament->name)

@section('sidebar-nav')
@include('tournaments._sidebar')
@endsection

@php
  $u = auth()->user();
  $role = $u->role;
  $isManager = in_array($role, ['admin','staff','coach'], true);
  $isStudent = $role === 'student';

  $playRoute     = 'student.tournaments.play';
  $joinRoute     = 'student.tournaments.join';

  // Role-aware route names so the same blade serves all four roles.
  $rolePrefix    = $role; // admin / staff / coach / student
  $editRoute     = $isManager ? "$rolePrefix.tournaments.edit"     : null;
  $cancelRoute   = $isManager ? "$rolePrefix.tournaments.cancel"   : null;
  $spectateRoute = "$rolePrefix.tournaments.spectate";
  $pgnRoute      = "$rolePrefix.tournaments.download-pgn";
  $exportRoute   = $isStudent ? 'student.tournaments.export-analyse' : null;

  $myId = $u->id;
  $isMyPairing = fn ($p) => $p->white_user_id === $myId || $p->black_user_id === $myId;
  $hasJoined   = $hasJoined ?? ($isStudent ? $tournament->hasJoined($myId) : true);

  $tournamentOver = $tournament->isTerminal();
  $canManageTournament = $isManager && $tournament->canBeManagedBy($u);
  $canEditTournament = $canManageTournament && $tournament->isUpcoming();
  $canCancelTournament = $isManager && $tournament->canBeCancelledBy($u);

  $resultChip = function ($p) {
      if (!$p->result) return null;
      return match ($p->result) {
          '1-0'      => ['1–0',   'badge-active'],
          '0-1'      => ['0–1',   'badge-active'],
          '0.5-0.5'  => ['½–½',   'badge-pending'],
          default    => [$p->result, 'badge-pending'],
      };
  };

  // Countdown target for live banner.
  $countdownTarget = $tournament->countdownTarget();
  $countdownIso    = $countdownTarget ? $countdownTarget->copy()->utc()->toIso8601String() : null;
  $countdownLabel  = $tournament->isScheduled()
      ? 'Tournament starts in'
      : ($tournament->isInProgress() && $tournament->next_round_at
          ? 'Round ' . ($tournament->current_round + 1) . ' starts in'
          : null);
@endphp

@push('styles')
<style>
.spec-modal {
  height: min(680px, 85vh);
}
.spec-modal-body { padding:0; overflow:hidden; }
.spec-modal-body iframe { width: 100%; height: 100%; border: 0; }
.countdown-banner {
  background: var(--color-primary);
  color: #fff; padding: 16px 20px; border-radius: 12px; margin-bottom: 16px;
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.countdown-banner .clock {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 28px; font-weight: 700; letter-spacing: 1px;
  background: rgba(255,255,255,.15); padding: 8px 14px; border-radius: 8px;
}
.join-hero {
  background: var(--color-success);
  color: #fff; padding: 28px; border-radius: 14px; margin-bottom: 18px;
  text-align: center;
}
.join-hero h2 { margin: 0 0 8px 0; font-size: 22px; }
.join-hero p { margin: 0 0 16px 0; opacity: .92; }
.join-hero .btn-join {
  background: #fff; color: #047857; padding: 12px 28px; border-radius: 10px;
  font-weight: 700; font-size: 16px; border: 0; cursor: pointer;
  text-decoration: none; display: inline-block;
}
.join-hero .btn-join:hover { background: #f0fdf4; }
.two-col-roster { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 800px) { .two-col-roster { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

@php
  $displayTimezone = auth()->user()->preferredTimezone();
@endphp

@if(session('joined_message'))
  @push('scripts')
    <script>document.addEventListener('DOMContentLoaded', () => window.ArcherToast?.show(@json(session('joined_message')), 'success'));</script>
  @endpush
@endif

<x-ui.page-header
  :title="($tournament->isArcherSunday() ? '🏹 Archer Sunday · ' : '').$tournament->name"
  subtitle="{{ ucfirst($tournament->format) }} · {{ $tournament->time_control }} · {{ $tournament->rounds }} rounds · {{ \Illuminate\Support\Carbon::parse($tournament->scheduled_at)->timezone($displayTimezone)->format('D, d M Y · h:i A') }} {{ $displayTimezone }}"
>
  <x-slot:actions>
    <div class="flex items-center gap-2 flex-wrap">
    @if($tournament->isLive())
      <span class="badge badge-live">Round {{ $tournament->current_round }}</span>
    @elseif($tournament->status === 'completed')
      <span class="badge badge-done">Completed</span>
    @elseif($tournament->isCancelled())
      <span class="badge badge-inactive">Cancelled</span>
    @else
      <span class="badge badge-pending">Scheduled</span>
    @endif
    @if($canEditTournament && $editRoute)
      <a class="btn btn-outline" href="{{ route($editRoute, $tournament) }}">Edit tournament</a>
    @endif
    @if($canCancelTournament && $cancelRoute)
      <form method="POST" action="{{ route($cancelRoute, $tournament) }}" style="display:inline"
            onsubmit="return confirm('Cancel this tournament? Players will not be able to join or play.')">
        @csrf
        <button type="submit" class="btn btn-outline" style="color:#dc2626;border-color:#fca5a5">Cancel tournament</button>
      </form>
    @endif
    </div>
  </x-slot:actions>
</x-ui.page-header>

@if($countdownIso && $countdownLabel)
<div class="countdown-banner">
  <div style="flex:1;min-width:160px">
    <div style="font-size:12px;font-weight:600;opacity:.85">{{ $countdownLabel }}</div>
    <div class="clock" data-countdown="{{ $countdownIso }}">--:--</div>
  </div>
</div>
@endif

@if($isStudent && !$hasJoined && !$tournamentOver)
<div class="join-hero">
  <h2>Ready to compete?</h2>
  <form method="POST" action="{{ route($joinRoute, $tournament) }}">
    @csrf
    <button type="submit" class="btn-join">Join Tournament</button>
  </form>
</div>
@endif

@if($isStudent && $hasJoined && !$tournamentOver && $tournament->isJoinable())
<div class="card mb-4">
  <div class="flex items-center justify-between">
    <div>
      <strong>You have joined this tournament.</strong>
    </div>
    <form method="POST" action="{{ route('student.tournaments.leave', $tournament) }}">
      @csrf
      <button type="submit" class="btn btn-outline">Leave Tournament</button>
    </form>
  </div>
</div>
@endif

@if($isStudent && ($mode ?? null) === 'play' && !empty($myPairing) && $myPairing->isActive() && !$myPairing->is_bye)
<div class="alert alert-info" style="font-size:14px">
  <div style="flex:1">
    <strong>Your game is live — Round {{ $myPairing->round }}, Board {{ $myPairing->board }}.</strong>
    Opponent: {{ optional($myPairing->opponentOf($u->id))->name ?? '—' }}.
  </div>
  <a class="btn btn-primary" href="{{ route($playRoute, [$tournament, $myPairing]) }}">Play Your Game</a>
</div>
@elseif($isStudent && ($mode ?? null) === 'play' && !empty($myPairing) && $myPairing->is_bye)
<div class="alert alert-info">
  You have a <strong>bye</strong> this round (1 point awarded).
</div>
@endif

@if($isManager)
<div class="two-col-roster mb-4">
  <div class="card">
    <div class="card-title flex items-center justify-between">
      <span>Joined ({{ ($joinedStudents ?? collect())->count() }})</span>
      @if($tournament->isUpcoming())
        <span class="text-xs text-muted">Open until start</span>
      @endif
    </div>
    @if(($joinedStudents ?? collect())->isEmpty())
      <div class="empty">No players have joined yet.</div>
    @else
      <div class="flex" style="flex-wrap:wrap;gap:6px">
        @foreach($joinedStudents as $student)
          <span class="badge badge-active" style="padding:6px 10px;font-size:12px">{{ $student->name }}</span>
        @endforeach
      </div>
    @endif
  </div>

  <div class="card">
    <div class="card-title">Pending ({{ ($pendingStudents ?? collect())->count() }})</div>
    <div class="text-xs text-muted mb-2">Eligible students who haven't joined.</div>
    @if(($pendingStudents ?? collect())->isEmpty())
      <div class="empty">Everyone eligible has joined.</div>
    @else
      <div class="flex" style="flex-wrap:wrap;gap:6px">
        @foreach($pendingStudents as $student)
          <span class="badge badge-pending" style="padding:4px 8px;font-size:11px">{{ $student->name }}</span>
        @endforeach
      </div>
    @endif
  </div>
</div>
@else
<div class="card mb-4">
  <div class="card-title flex items-center justify-between">
    <span>Joined Players ({{ ($joinedStudents ?? collect())->count() }})</span>
    @if($tournament->isUpcoming())
      <span class="text-xs text-muted">More can join until the tournament starts.</span>
    @endif
  </div>
  @if(($joinedStudents ?? collect())->isEmpty())
    <div class="empty">No players have joined yet.</div>
  @else
    <div class="flex" style="flex-wrap:wrap;gap:8px">
      @foreach($joinedStudents as $student)
        <span class="badge {{ $student->id === $myId ? 'badge-active' : 'badge-pending' }}" style="padding:6px 10px;font-size:12px">
          {{ $student->name }}
          @if($student->id === $myId)<span style="opacity:.85">· you</span>@endif
        </span>
      @endforeach
    </div>
  @endif
</div>
@endif

<div class="grid-2">
  <div class="card">
    <div class="card-title">Standings</div>
    @if($standings->isEmpty())
      <div class="empty">Standings appear once round 1 begins.</div>
    @else
    <div class="table-wrap" style="border:none">
      <table>
        <thead>
          <tr><th style="width:40px">#</th><th>Player</th><th style="width:70px">Score</th><th style="width:80px">Buchholz</th><th style="width:120px">Record</th></tr>
        </thead>
        <tbody>
          @foreach($standings as $i => $row)
          @php
            $u_id = optional($row->user)->id;
            $rowMine = $u_id === $myId;
            $w = (int)($row->wins ?? 0);
            $d = (int)($row->draws ?? 0);
            $l = (int)($row->losses ?? 0);
            $score = rtrim(rtrim(number_format((float)$row->score, 1, '.', ''), '0'), '.') ?: '0';
          @endphp
          <tr style="{{ $rowMine ? 'background:#fef9c3' : '' }}">
            <td><strong>{{ $i + 1 }}</strong></td>
            <td>
              {{ optional($row->user)->name ?? '—' }}
              @if($rowMine) <span class="badge badge-student">you</span>@endif
            </td>
            <td><strong>{{ $score }}</strong></td>
            <td class="text-muted">{{ $row->buchholz }}</td>
            <td class="text-xs text-muted">
              <span style="color:#059669">{{ $w }}W</span>
              · <span style="color:#d97706">{{ $d }}D</span>
              · <span style="color:#dc2626">{{ $l }}L</span>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <div class="card">
    <div class="card-title">Rounds & Pairings</div>
    @if($rounds->isEmpty())
      <div class="empty">No rounds yet — pairings appear when the tournament starts.</div>
    @endif
    @foreach($rounds as $roundNo => $games)
      <div class="mb-4">
        <div class="flex items-center justify-between mb-2">
          <div style="font-weight:700;font-size:13px">Round {{ $roundNo }}</div>
          @if($tournament->current_round === (int)$roundNo && $tournament->isLive())
            <span class="badge badge-live">In play</span>
          @elseif($tournament->current_round > (int)$roundNo)
            <span class="badge badge-done">Finished</span>
          @endif
        </div>
        <div class="table-wrap" style="border:none">
          <table>
            <thead>
              <tr>
                <th style="width:40px">Bd</th>
                <th>White</th>
                <th>Black</th>
                <th style="width:90px">Result</th>
                <th style="width:170px"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($games as $g)
              @php
                $rc = $resultChip($g);
                $mine = $isMyPairing($g);
                $watchUrl = !$g->is_bye ? route($spectateRoute, [$tournament, $g]) : null;
              @endphp
              <tr>
                <td><strong>{{ $g->board }}</strong></td>
                <td>
                  {{ optional($g->white)->name ?? '—' }}
                  @if($g->is_bye) <span class="text-muted">(bye)</span>@endif
                  @if($g->winner_user_id && $g->winner_user_id === $g->white_user_id) <span class="badge badge-active">Winner</span>@endif
                </td>
                <td>
                  {{ optional($g->black)->name ?? '—' }}
                  @if($g->winner_user_id && $g->winner_user_id === $g->black_user_id) <span class="badge badge-active">Winner</span>@endif
                </td>
                <td>
                  @if($g->is_bye)
                    <span class="badge badge-pending">Bye</span>
                  @elseif($rc)
                    <strong style="color:{{ $g->result === '0.5-0.5' ? '#d97706' : '#059669' }}">{{ $rc[0] }}</strong>
                  @elseif($g->status === 'active')
                    <span class="badge badge-live">Live</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  <div class="flex gap-1" style="flex-wrap:wrap;justify-content:flex-end">
                    @if($isStudent && $mine && $g->status === 'active' && !$g->is_bye)
                      <a class="btn btn-xs btn-primary" href="{{ route($playRoute, [$tournament, $g]) }}">Play</a>
                    @endif

                    {{-- Live game spectator: POPUP modal (one at a time). Students see it
                         for OTHER players' games only (not their own). --}}
                    @if(!$g->is_bye && $g->status === 'active' && !($isStudent && $mine))
                      <button type="button" class="btn btn-xs btn-outline"
                              data-spec-watch="{{ $watchUrl }}"
                              data-spec-title="Round {{ $g->round }} · Board {{ $g->board }} · {{ optional($g->white)->name ?? '—' }} vs {{ optional($g->black)->name ?? '—' }}"
                              title="Watch live">Watch</button>
                    @endif

                    @if(!$g->is_bye && $g->isComplete() && ($isManager || ($isStudent && $mine)))
                      <a class="btn btn-xs btn-ghost" href="{{ route($pgnRoute, [$tournament, $g]) }}" title="Download PGN">Download PGN</a>
                    @endif

                    @if($isStudent && $mine && !$g->is_bye && $g->isComplete())
                      <form method="POST" action="{{ route($exportRoute, [$tournament, $g]) }}" style="display:inline">
                        @csrf
                        <button class="btn btn-xs btn-success" type="submit" title="Save to Analyse tab as a study">Analyse</button>
                      </form>
                    @endif

                    @if($isManager && !$g->is_bye && $g->isComplete())
                      <button type="button" class="btn btn-xs btn-ghost"
                              data-spec-watch="{{ $watchUrl }}"
                              data-spec-title="Round {{ $g->round }} · Board {{ $g->board }} · Replay"
                              title="Open game">Open</button>
                    @endif
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  </div>
</div>

<x-ui.modal id="specModal" max-width="720px" modal-class="spec-modal" body-class="spec-modal-body">
  <x-slot:header>
    <div class="ui-modal-heading">
      <div class="ui-modal-title" id="specModalTitle">Watching Live Game</div>
      <x-ui.button type="button" variant="secondary" size="sm" class="ui-button--icon" id="specModalClose" aria-label="Close" title="Close">
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
      </x-ui.button>
    </div>
  </x-slot:header>
  <iframe id="specModalFrame" src="about:blank" title="Live game"></iframe>
</x-ui.modal>

@push('scripts')
<script>
(function () {
  function updateCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach(function (el) {
      var iso = el.getAttribute('data-countdown');
      if (!iso) return;
      var target = new Date(iso).getTime();
      var now = Date.now();
      var diff = Math.max(0, target - now);
      var totalSecs = Math.floor(diff / 1000);
      var mins = Math.floor(totalSecs / 60);
      var secs = totalSecs % 60;
      el.textContent = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
      if (diff === 0 && !el.__refreshed) {
        el.__refreshed = true;
        setTimeout(function () { window.location.reload(); }, 1500);
      }
    });
  }
  setInterval(updateCountdowns, 1000);
  updateCountdowns();

  var modal = document.getElementById('specModal');
  var frame = document.getElementById('specModalFrame');
  var titleEl = document.getElementById('specModalTitle');
  var closeBtn = document.getElementById('specModalClose');

  function openSpec(url, title) {
    if (!url) return;
    frame.src = url;
    titleEl.textContent = title || 'Watching live game';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  function closeSpec() {
    modal.style.display = 'none';
    frame.src = 'about:blank';
    document.body.style.overflow = '';
  }
  document.querySelectorAll('[data-spec-watch]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openSpec(btn.getAttribute('data-spec-watch'), btn.getAttribute('data-spec-title'));
    });
  });
  closeBtn.addEventListener('click', closeSpec);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeSpec();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSpec();
  });

  // Auto-refresh while in pre-pair window or between rounds — keeps the
  // lobby fresh so when round 1 (or N+1) pairings appear, players see them
  // and get auto-redirected into the play screen by the show controller.
  // 5s is snappy enough that kids don't sit waiting after the countdown
  // hits zero, but slow enough not to thrash the server.
  var status = '{{ $tournament->status }}';
  var hasCountdown = {{ $countdownIso ? 'true' : 'false' }};
  if (status === 'scheduled' || (status === 'in_progress' && hasCountdown)) {
    setTimeout(function () { window.location.reload(); }, 5000);
  }

})();
</script>
@endpush
@endsection

@extends('layouts.app')
@section('page-title','Tournaments')

@section('sidebar-nav')
@include('tournaments._sidebar')
@endsection

@php
  $u = auth()->user();
  $role = $u->role;
  $isManager = in_array($role, ['admin','staff','coach']);
  $homeRoute = match($role) {
    'admin' => 'admin.tournaments.index',
    'staff' => 'staff.tournaments.index',
    'coach' => 'coach.tournaments.index',
    default => 'student.tournaments.index',
  };
  $createRoute = match($role) {
    'admin' => 'admin.tournaments.create',
    'staff' => 'staff.tournaments.create',
    'coach' => 'coach.tournaments.create',
    default => null,
  };
  $showRouteName = match($role) {
    'admin' => 'admin.tournaments.show',
    'staff' => 'staff.tournaments.show',
    'coach' => 'coach.tournaments.show',
    default => 'student.tournaments.show',
  };
  $editRouteName = match($role) {
    'admin' => 'admin.tournaments.edit',
    'staff' => 'staff.tournaments.edit',
    'coach' => 'coach.tournaments.edit',
    default => null,
  };
  $displayTimezone = $u->preferredTimezone();
@endphp

@section('content')
<x-ui.page-header title="Tournaments">
  @if($isManager && $createRoute)
    <x-slot:actions><x-ui.button :href="route($createRoute)" variant="primary">New Tournament</x-ui.button></x-slot:actions>
  @endif
</x-ui.page-header>

@php
  $today = $today ?? collect();
@endphp

@if(!$isManager)
<div class="sh"><div class="sh-title">Today</div></div>
@if($today->isEmpty())
  <div class="card empty">No tournaments today.</div>
@else
<div class="grid-2 mb-6">
  @foreach($today as $t)
    <div class="card">
      <div class="flex items-center justify-between mb-2">
        <div style="font-weight:700;font-size:15px">@if($t->isArcherSunday())<span class="badge badge-active">🏹 Archer Sunday</span> @endif{{ $t->name }}</div>
        @if($t->isLive())
          <span class="badge badge-live">Live</span>
        @elseif($t->status === \App\Models\Tournament::STATUS_COMPLETED)
          <span class="badge badge-done">Completed</span>
        @else
          <span class="badge badge-pending">Today</span>
        @endif
      </div>
      <div class="text-sm text-muted mb-2">
        {{ ucfirst($t->format) }} · {{ $t->time_control }} · {{ $t->rounds }} rounds
      </div>
      <div class="text-xs text-muted mb-3">
        {{ \Illuminate\Support\Carbon::parse($t->scheduled_at)->timezone($displayTimezone)->format('D, d M Y · h:i A') }} {{ $displayTimezone }}
      </div>
      <div class="flex gap-2" style="flex-wrap:wrap">
        @php $studentJoined = $t->participants->contains('user_id', $u->id); @endphp
        @if(!$studentJoined && $t->isJoinable())
          <form method="POST" action="{{ route('student.tournaments.join', $t) }}" style="display:inline">
            @csrf
            <button class="btn btn-sm btn-success" type="submit">Join Tournament</button>
          </form>
        @endif
        @if($studentJoined && $t->isJoinable())
          <form method="POST" action="{{ route('student.tournaments.leave', $t) }}" style="display:inline">
            @csrf
            <button class="btn btn-sm btn-outline" type="submit">Leave</button>
          </form>
        @endif
        <a class="btn btn-sm btn-primary" href="{{ route($showRouteName, $t) }}">
          {{ $studentJoined ? 'Lobby' : 'Open' }}
        </a>
      </div>
    </div>
  @endforeach
</div>
@endif
@endif

<div class="sh">
  <div class="sh-title">{{ $isManager ? 'Upcoming & live' : 'Upcoming' }}</div>
</div>

@if($upcoming->isEmpty())
  <div class="card empty">No upcoming tournaments yet.</div>
@else
<div class="grid-2 mb-6">
  @foreach($upcoming as $t)
    <div class="card">
      <div class="flex items-center justify-between mb-2">
        <div style="font-weight:700;font-size:15px">@if($t->isArcherSunday())<span class="badge badge-active">🏹 Archer Sunday</span> @endif{{ $t->name }}</div>
        @if($t->isLive())
          <span class="badge badge-live">Live</span>
        @else
          <span class="badge badge-pending">Scheduled</span>
        @endif
      </div>
      <div class="text-sm text-muted mb-2">
        {{ ucfirst($t->format) }} · {{ $t->time_control }} · {{ $t->rounds }} rounds
      </div>
      <div class="text-xs text-muted mb-3">
        {{ \Illuminate\Support\Carbon::parse($t->scheduled_at)->timezone($displayTimezone)->format('D, d M Y · h:i A') }} {{ $displayTimezone }}
      </div>
      @if($t->isLive())
        <div class="text-xs mb-3">
          Round <strong>{{ $t->current_round }}</strong> of {{ $t->rounds }}
        </div>
      @endif
      <div class="flex gap-2" style="flex-wrap:wrap">
        @php
          $studentJoined = !$isManager && $t->participants->contains('user_id', $u->id);
          $canManageTournament = $isManager && $t->canBeManagedBy($u);
        @endphp
        @if(!$isManager && !$studentJoined && $t->isJoinable())
          <form method="POST" action="{{ route('student.tournaments.join', $t) }}" style="display:inline">
            @csrf
            <button class="btn btn-sm btn-success" type="submit">Join Tournament</button>
          </form>
        @endif
        @if(!$isManager && $studentJoined && $t->isJoinable())
          <form method="POST" action="{{ route('student.tournaments.leave', $t) }}" style="display:inline">
            @csrf
            <button class="btn btn-sm btn-outline" type="submit">Leave</button>
          </form>
        @endif
        <a class="btn btn-sm btn-primary" href="{{ route($showRouteName, $t) }}">
          {{ $canManageTournament ? 'Manage' : ($isManager ? 'View' : ($studentJoined ? 'Lobby' : 'Open')) }}
        </a>
        @if(!$isManager && $studentJoined)
          <span class="badge badge-active">Joined</span>
        @endif
          @if($canManageTournament && $t->isUpcoming())
            <a class="btn btn-sm btn-outline" href="{{ route($editRouteName, $t) }}">Edit</a>
          @endif
          @if($isManager && $t->canBeCancelledBy($u))
            <form method="POST" action="{{ route(str_replace('.index','.cancel', $homeRoute), $t) }}" onsubmit="return confirm('Cancel this tournament?')">
              @csrf
              <button class="btn btn-sm btn-warning" type="submit">Cancel</button>
            </form>
          @endif
      </div>
    </div>
  @endforeach
</div>
@endif

<div class="sh">
  <div class="sh-title">Past tournaments</div>
</div>

@if($past->isEmpty())
  <div class="card empty">No past tournaments.</div>
@else
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Name</th><th>Format</th><th>Rounds</th><th>When</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
      @foreach($past as $t)
      <tr>
        <td><strong>@if($t->isArcherSunday())🏹 Archer Sunday · @endif{{ $t->name }}</strong></td>
        <td>{{ ucfirst($t->format) }} ({{ $t->time_control }})</td>
        <td>{{ $t->rounds }}</td>
        <td class="text-muted">{{ \Illuminate\Support\Carbon::parse($t->scheduled_at)->timezone($displayTimezone)->format('d M Y, h:i A') }} {{ $displayTimezone }}</td>
        <td>
          @if($t->status === \App\Models\Tournament::STATUS_COMPLETED)
            <span class="badge badge-done">Completed</span>
          @else
            <span class="badge badge-inactive">Cancelled</span>
          @endif
        </td>
        <td>
          <a class="btn btn-xs btn-outline" href="{{ route($showRouteName, $t) }}">View</a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif
@endsection

@extends('layouts.app')
@section('page-title', 'Quiz Sessions')

@section('sidebar-nav')
@include('quiz-sessions._sidebar')
@endsection

@php
  $u = auth()->user();
  $role = $u->role;
  $isManager = in_array($role, ['admin','staff'], true);
  $isCoach   = $role === 'coach';
  $isStudent = $role === 'student';

  $rolePrefix = $role;
  $createRoute = $isManager ? "$rolePrefix.quiz-sessions.create" : null;
  $showRoute   = "$rolePrefix.quiz-sessions.show";
  $editRoute   = $isManager ? "$rolePrefix.quiz-sessions.edit"   : null;
  $cancelRoute = $isManager ? "$rolePrefix.quiz-sessions.cancel" : null;
  $joinRoute   = $isStudent ? "student.quiz-sessions.join"       : null;
@endphp

@section('content')
<x-ui.page-header title="Quiz Sessions">
  @if($createRoute)
    <x-slot:actions><x-ui.button :href="route($createRoute)" variant="primary">Schedule Session</x-ui.button></x-slot:actions>
  @endif
</x-ui.page-header>

<div class="sh">
  <div class="sh-title">Upcoming &amp; live</div>
</div>

@if($upcoming->isEmpty())
  <div class="card empty">No upcoming quiz sessions.</div>
@else
<div class="grid-2 mb-6">
  @foreach($upcoming as $s)
    @php
      $studentJoined = $isStudent && $s->hasJoined($u->id);
    @endphp
    <div class="card">
      <div class="flex items-center justify-between mb-2" style="gap:8px">
        <div style="font-weight:700;font-size:15px">{{ $s->name }}</div>
        @if($s->status === 'live')
          <span class="badge badge-live">Live</span>
        @else
          <span class="badge badge-pending">Scheduled</span>
        @endif
      </div>
      <div class="text-sm text-muted mb-2">
        Coach: <strong>{{ optional($s->coach)->name ?? '—' }}</strong> · Level: <strong>{{ ucfirst($s->level) }}</strong>
      </div>
      <div class="text-xs text-muted mb-2">
        {{ \Illuminate\Support\Carbon::parse($s->scheduled_at)->timezone('Asia/Kolkata')->format('D, d M Y · h:i A') }} IST
      </div>
      <div class="mb-3" style="display:flex;flex-wrap:wrap;gap:4px">
        @if(empty($s->countries))
          <span class="badge badge-active">All regions</span>
        @else
          @foreach($s->countries as $code)
            <span class="badge" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe">{{ $code }}</span>
          @endforeach
        @endif
      </div>
      <div class="flex gap-2" style="flex-wrap:wrap">
        @if($isStudent && !$studentJoined && $s->status !== 'ended')
          <form method="POST" action="{{ route($joinRoute, $s) }}" style="display:inline">
            @csrf
            <button class="btn btn-sm btn-success" type="submit">Join</button>
          </form>
        @endif
        <a class="btn btn-sm btn-primary" href="{{ route($showRoute, $s) }}">
          @if($isCoach && $s->status === 'scheduled') Start
          @elseif($studentJoined) Lobby
          @else Open @endif
        </a>
        @if($isStudent && $studentJoined)
          <span class="badge badge-active">Joined</span>
        @endif
        @if($isManager)
          <a class="btn btn-sm btn-outline" href="{{ route($editRoute, $s) }}">Edit</a>
          @if($s->status !== 'ended')
            <form method="POST" action="{{ route($cancelRoute, $s) }}" onsubmit="return confirm('Cancel session?')" style="display:inline">
              @csrf
              <button class="btn btn-sm btn-warning" type="submit">Cancel</button>
            </form>
          @endif
        @endif
      </div>
    </div>
  @endforeach
</div>
@endif

<div class="sh">
  <div class="sh-title">Past sessions</div>
</div>
@if($past->isEmpty())
  <div class="card empty">No past sessions yet.</div>
@else
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Name</th><th>Coach</th><th>Level</th><th>Week</th><th>When</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
      @foreach($past as $s)
      <tr>
        <td><strong>{{ $s->name }}</strong></td>
        <td>{{ optional($s->coach)->name ?? '—' }}</td>
        <td>{{ ucfirst($s->level) }}</td>
        <td>{{ $s->week ?? '—' }}</td>
        <td class="text-muted">{{ \Illuminate\Support\Carbon::parse($s->scheduled_at)->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
        <td>
          @if($s->status === 'ended') <span class="badge badge-done">Ended</span>
          @else <span class="badge badge-inactive">Cancelled</span> @endif
        </td>
        <td>
          <a class="btn btn-xs btn-outline" href="{{ route($showRoute, $s) }}">View</a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif
@endsection

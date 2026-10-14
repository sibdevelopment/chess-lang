@extends('layouts.app')
@section('page-title','Dashboard')

@section('sidebar-nav') @include('staff._sidebar') @endsection

@section('content')
@php
  $firstName = preg_split('/\s+/', trim(auth()->user()->name))[0] ?? auth()->user()->name;
@endphp

<x-ui.page-header title="Dashboard" />

<div class="dash-card">
  <div class="dash-card-head">
    <h2>Welcome, {{ $firstName }}</h2>
    <span class="sub">{{ now($tz)->format('D, d M Y') }} · Asia/Kolkata</span>
  </div>
  <div class="dash-meta">Operational dashboard for groups, students, classes, and tournament coordination.</div>
  <div class="dash-actions dash-inline-list">
    <a class="dash-card-link" href="{{ route('staff.groups.create') }}">Create Batch</a>
    <a class="dash-card-link" href="{{ route('staff.students.create') }}">Add Student</a>
    <a class="dash-card-link" href="{{ route('staff.tournaments.create') }}">Create Tournament</a>
    <a class="dash-card-link" href="{{ route('staff.classes') }}">View Schedule</a>
  </div>
</div>

@if($liveNow->isNotEmpty())
  <div class="dash-live">
    <div class="dash-live-dot"></div>
    <div style="flex:1">
      <div class="dash-live-title">{{ $stats['live_now'] }} {{ \Illuminate\Support\Str::plural('class', $stats['live_now']) }} live now</div>
      <div class="dash-live-meta">{{ $liveNow->map(fn($class) => ($class->group->name ?? 'Batch').' · '.($class->chapter->name ?? 'Chapter'))->join(' · ') }}</div>
    </div>
    <a class="dash-live-cta" href="{{ route('staff.classes', ['range' => 'live']) }}">View Live Classes</a>
  </div>
@endif

<div class="dash-stats">
  <a class="dash-stat is-gold" href="{{ route('staff.groups') }}">
    <div class="dash-stat-label">Active groups</div>
    <div class="dash-stat-val">{{ $stats['active_groups'] }}</div>
    <div class="dash-stat-sub">Current teaching groups</div>
  </a>
  <a class="dash-stat" href="{{ route('staff.classes', ['range' => 'today']) }}">
    <div class="dash-stat-label">Today's classes</div>
    <div class="dash-stat-val">{{ $stats['today_classes'] }}</div>
    <div class="dash-stat-sub">Slots scheduled today</div>
  </a>
  <a class="dash-stat is-ruby" href="{{ route('staff.classes', ['range' => 'past']) }}">
    <div class="dash-stat-label">Missed today</div>
    <div class="dash-stat-val">{{ $stats['missed_today'] }}</div>
    <div class="dash-stat-sub">Cancelled or no-show slots</div>
  </a>
  <a class="dash-stat is-sapphire" href="{{ route('staff.students') }}">
    <div class="dash-stat-label">Unassigned students</div>
    <div class="dash-stat-val">{{ $stats['students_without_group'] }}</div>
    <div class="dash-stat-sub">Need group placement</div>
  </a>
  <a class="dash-stat is-walnut" href="{{ route('staff.tournaments.index') }}">
    <div class="dash-stat-label">Upcoming tournaments</div>
    <div class="dash-stat-val">{{ $stats['upcoming_tournaments'] }}</div>
    <div class="dash-stat-sub">Scheduled ahead</div>
  </a>
  <a class="dash-stat" href="{{ route('staff.homework') }}">
    <div class="dash-stat-label">Homework</div>
    <div class="dash-stat-val">{{ $stats['homework'] }}</div>
    <div class="dash-stat-sub">Submissions to monitor</div>
  </a>
</div>

<div class="dash-grid-2">
  <div class="dash-card">
    <div class="dash-card-head">
      <h2>Today's Scheduled Classes</h2>
      <div class="spacer"></div>
      <a class="dash-card-link" href="{{ route('staff.classes', ['range' => 'today']) }}">View All</a>
    </div>
    @forelse($todayClasses as $class)
      <div class="dash-row">
        <div class="dash-row-av" style="background:{{ $class->coach->avatar_color ?? 'var(--d-navy)' }}">{{ $class->coach->initials ?? 'CL' }}</div>
        <div class="dash-row-body">
          <div class="dash-row-title">{{ $class->group->name ?? $class->name }}</div>
          <div class="dash-row-meta">
            {{ $class->scheduled_for?->timezone($tz)->format('D, g:i A') }} ·
            {{ $class->coach->name ?? 'Coach pending' }} ·
            {{ $class->chapter->name ?? 'Chapter pending' }}
          </div>
        </div>
        <span class="dash-status {{ $class->status === 'live' ? 'is-live' : ($class->status === 'cancelled' ? 'is-warning' : 'is-upcoming') }}">
          {{ $class->status === 'scheduled' ? 'Scheduled' : ucfirst($class->status) }}
        </span>
      </div>
    @empty
      <div class="dash-empty">
        <div>No classes are scheduled for today.</div>
      </div>
    @endforelse
  </div>

  <div class="dash-stack">
    <div class="dash-card">
      <div class="dash-card-head">
        <h2>Students Needing Assignment</h2>
        <div class="spacer"></div>
        <a class="dash-card-link" href="{{ route('staff.students') }}">Open Students</a>
      </div>
      @forelse($studentsNeedingAssignment as $student)
        <div class="dash-row">
          <div class="dash-row-av" style="background:{{ $student->avatar_color }}">{{ $student->initials }}</div>
          <div class="dash-row-body">
            <div class="dash-row-title">{{ $student->name }}</div>
            <div class="dash-row-meta">@{{ $student->username }}</div>
          </div>
          <span class="dash-status is-warning">Pending</span>
        </div>
      @empty
        <div class="dash-empty">
          <div>All active students are assigned to a group.</div>
        </div>
      @endforelse
    </div>

    <div class="dash-card">
      <div class="dash-card-head">
        <h2>Upcoming Tournaments</h2>
        <div class="spacer"></div>
        <a class="dash-card-link" href="{{ route('staff.tournaments.index') }}">View All</a>
      </div>
      @forelse($upcomingTournaments as $tournament)
        <div class="dash-row">
          <div class="dash-row-body">
            <div class="dash-row-title">{{ $tournament->name }}</div>
            <div class="dash-row-meta">{{ $tournament->scheduled_at?->timezone($tz)->format('d M, g:i A') }} · {{ ucfirst($tournament->format) }} · {{ $tournament->time_control }}</div>
          </div>
          <span class="dash-status is-upcoming">Upcoming</span>
        </div>
      @empty
        <div class="dash-empty">
          <div>No upcoming tournaments are scheduled.</div>
        </div>
      @endforelse
    </div>
  </div>
</div>

<div class="dash-grid-2">
  <div class="dash-card">
    <div class="dash-card-head">
      <h2>Missed or Cancelled Today</h2>
      <div class="spacer"></div>
      <a class="dash-card-link" href="{{ route('staff.classes', ['range' => 'past']) }}">View Past</a>
    </div>
    @forelse($missedToday as $class)
      <div class="dash-row">
        <div class="dash-row-av" style="background:#f97316">!</div>
        <div class="dash-row-body">
          <div class="dash-row-title">{{ $class->group->name ?? $class->name }}</div>
          <div class="dash-row-meta">
            {{ $class->scheduled_for?->timezone($tz)->format('g:i A') }} ·
            {{ $class->coach->name ?? 'Coach pending' }} ·
            {{ $class->chapter->name ?? 'Chapter pending' }}
          </div>
        </div>
        <span class="dash-status is-warning">Cancelled</span>
      </div>
    @empty
      <div class="dash-empty">
        <div>No classes have been cancelled today.</div>
      </div>
    @endforelse
  </div>

  <div class="dash-stack">
    <div class="dash-card">
      <div class="dash-card-head">
        <h2>Recent Batch Updates</h2>
      </div>
      @forelse($recentGroups as $group)
        <div class="dash-row">
          <div class="dash-row-body">
            <div class="dash-row-title">{{ $group->name }}</div>
            <div class="dash-row-meta">{{ $group->students_count }} students · {{ $group->coach->name ?? 'Coach pending' }}</div>
          </div>
          <span class="dash-muted">{{ $group->updated_at?->diffForHumans() }}</span>
        </div>
      @empty
        <div class="dash-empty">
          <div>No recent group updates.</div>
        </div>
      @endforelse
    </div>

    <div class="dash-card">
      <div class="dash-card-head">
        <h2>Recent Student Updates</h2>
      </div>
      @forelse($recentUsers as $user)
        <div class="dash-row">
          <div class="dash-row-av" style="background:{{ $user->avatar_color }}">{{ $user->initials }}</div>
          <div class="dash-row-body">
            <div class="dash-row-title">{{ $user->name }}</div>
            <div class="dash-row-meta">@{{ $user->username }} · {{ ucfirst($user->role) }}</div>
          </div>
          <span class="dash-muted">{{ $user->created_at?->diffForHumans() }}</span>
        </div>
      @empty
        <div class="dash-empty">
          <div>No recent user changes.</div>
        </div>
      @endforelse
    </div>
  </div>
</div>
@endsection

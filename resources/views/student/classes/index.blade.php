@extends('layouts.app')
@section('page-title','Classes')

@section('sidebar-nav') @include('student._sidebar') @endsection

@php
  $range = in_array($range ?? 'today', ['live', 'today', 'past', 'upcoming', 'cancelled'], true) ? $range : 'today';
  $chapterLabel = static fn ($chapter, string $fallback = 'Current chapter') =>
    $chapter
      ? trim(($chapter->sequence_number ? 'Chapter '.$chapter->sequence_number.' · ' : '').$chapter->name)
      : $fallback;
@endphp

@section('content')
<x-ui.page-header title="Classes" />

@if($live)
<div class="live-banner mb-4">
  <span class="live-dot"></span>
  <div style="flex:1">
    <div style="font-weight:700;font-size:16px">{{ $live->name }}</div>
    <div style="font-size:13px;opacity:.8">Coach: {{ $live->coach->name }} · {{ $chapterLabel($live->chapter) }}</div>
    <div style="font-size:12px;opacity:.65;margin-top:2px">Started {{ $live->started_at?->diffForHumans() }} · {{ $live->group?->students?->count() ?? 0 }} in batch</div>
  </div>
  <a href="{{ route('student.class.join', $live) }}" class="btn btn-primary">Join Now</a>
</div>
@endif

<nav class="ui-tabs ui-section-tabs" aria-label="Classes">
  <a href="{{ route('student.classes', ['range' => 'live']) }}" class="ui-tab {{ $range === 'live' ? 'is-active' : '' }}">Live <span class="ui-tab__count">{{ $counts['live'] ?? 0 }}</span></a>
  <a href="{{ route('student.classes', ['range' => 'today']) }}" class="ui-tab {{ $range === 'today' ? 'is-active' : '' }}">Today <span class="ui-tab__count">{{ $counts['today'] ?? 0 }}</span></a>
  <a href="{{ route('student.classes', ['range' => 'upcoming']) }}" class="ui-tab {{ $range === 'upcoming' ? 'is-active' : '' }}">Upcoming <span class="ui-tab__count">{{ $counts['upcoming'] ?? 0 }}</span></a>
  <a href="{{ route('student.classes', ['range' => 'past']) }}" class="ui-tab {{ $range === 'past' ? 'is-active' : '' }}">Past <span class="ui-tab__count">{{ $counts['past'] ?? 0 }}</span></a>
  <a href="{{ route('student.classes', ['range' => 'cancelled']) }}" class="ui-tab {{ $range === 'cancelled' ? 'is-active' : '' }}">Cancelled <span class="ui-tab__count">{{ $counts['cancelled'] ?? 0 }}</span></a>
</nav>

@if($range === 'live')
<div class="card">
  <div class="card-title">Live Class</div>
  @if($live)
    <div class="table-wrap">
      <table>
        <thead><tr><th>Class</th><th>Batch</th><th>Coach</th><th>Chapter</th><th>Action</th></tr></thead>
        <tbody>
          <tr>
            <td class="font-medium">{{ $live->name }}</td>
            <td><span class="badge badge-{{ $live->group->level }}">{{ $live->group->name }}</span></td>
            <td class="text-sm">{{ $live->coach->name }}</td>
            <td class="text-sm">{{ $chapterLabel($live->chapter) }}</td>
            <td><a href="{{ route('student.class.join', $live) }}" class="btn btn-xs">Join</a></td>
          </tr>
        </tbody>
      </table>
    </div>
  @else
    <div class="empty">No live class right now.</div>
  @endif
</div>
@elseif($range === 'today')
<div class="card">
  <div class="card-title">Today's Classes</div>
  @if($todayClasses->isEmpty())
    <div class="empty">No class scheduled today.</div>
  @else
    <div class="table-wrap">
      <table>
        <thead><tr><th>When</th><th>Batch</th><th>Coach</th><th>Chapter</th><th>Join</th></tr></thead>
        <tbody>
          @foreach($todayClasses as $cls)
            @php
              $joinVisible = now()->utc()->gte($cls->scheduled_for->copy()->subMinutes(15)->utc())
                && now()->utc()->lt($cls->scheduled_for->copy()->addMinutes(60)->utc());
            @endphp
            <tr>
              <td>
                <div class="font-medium">{{ $cls->scheduled_for->timezone($cls->displayTimezone())->format('D, d M Y') }}</div>
                <div class="text-xs text-muted">{{ $cls->scheduled_for->timezone($cls->displayTimezone())->format('g:i A') }} <span style="color:#94a3b8">{{ $cls->displayTimezone() }}</span></div>
              </td>
              <td><span class="badge badge-{{ $cls->group->level }}">{{ $cls->group->name }}</span></td>
              <td class="text-sm">{{ $cls->coach->name }}</td>
              <td class="text-sm">{{ $chapterLabel($cls->chapter, 'Next chapter') }}</td>
              <td>
                @if($live && $live->id === $cls->id)
                  <a href="{{ route('student.class.join', $cls) }}" class="btn btn-xs">Join</a>
                @elseif($joinVisible)
                  <button class="btn btn-xs" type="button" disabled>Join</button>
                  <div class="text-xs text-muted mt-1">Waiting for coach</div>
                @else
                  <span class="text-xs text-muted">Opens 15 min before</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@elseif($range === 'past')
<div class="card">
  <div class="card-title">Past Classes</div>
  @if($pastClasses->isEmpty())
    <div class="empty">No past classes yet.</div>
  @else
    <div class="table-wrap">
      <table>
        <thead><tr><th>Class</th><th>Chapter</th><th>Coach</th><th>Batch</th><th>Date</th><th>Duration</th></tr></thead>
        <tbody>
          @foreach($pastClasses as $cls)
          <tr>
            <td class="font-medium">{{ $cls->name }}</td>
            <td class="text-sm">{{ $chapterLabel($cls->chapter) }}</td>
            <td>
              <div class="flex items-center gap-2">
                <div class="av av-sm" style="background:{{ $cls->coach->avatar_color }}">{{ $cls->coach->initials }}</div>
                {{ $cls->coach->name }}
              </div>
            </td>
            <td><span class="badge badge-{{ $cls->group->level }}">{{ $cls->group->name }}</span></td>
            <td class="text-muted text-sm">{{ $cls->started_at?->timezone($cls->displayTimezone())->format('d M Y') }}</td>
            <td class="text-sm">{{ $cls->duration }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@elseif($range === 'upcoming')
<div class="card">
  <div class="card-title">Upcoming Classes</div>
  @if($upcomingClasses->isEmpty())
    <div class="empty">No upcoming classes scheduled.</div>
  @else
    <div class="table-wrap">
      <table>
        <thead><tr><th>When</th><th>Batch</th><th>Coach</th><th>Chapter</th></tr></thead>
        <tbody>
          @foreach($upcomingClasses as $cls)
          <tr>
            <td>
              <div class="font-medium">{{ $cls->scheduled_for->timezone($cls->displayTimezone())->format('D, d M Y') }}</div>
              <div class="text-xs text-muted">{{ $cls->scheduled_for->timezone($cls->displayTimezone())->format('g:i A') }} <span style="color:#94a3b8">{{ $cls->displayTimezone() }}</span></div>
            </td>
            <td><span class="badge badge-{{ $cls->group->level }}">{{ $cls->group->name }}</span></td>
            <td class="text-sm">{{ $cls->coach->name }}</td>
            <td class="text-sm">{{ $chapterLabel($cls->chapter, 'Next chapter') }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@else
<div class="card">
  <div class="card-title">Cancelled Classes</div>
  @if($cancelledClasses->isEmpty())
    <div class="empty">No cancelled classes.</div>
  @else
    <div class="table-wrap">
      <table>
        <thead><tr><th>Class</th><th>Batch</th><th>Coach</th><th>Chapter</th><th>Scheduled Time</th><th>Status</th></tr></thead>
        <tbody>
          @foreach($cancelledClasses as $cls)
          <tr>
            <td class="font-medium">{{ $cls->name }}</td>
            <td><span class="badge badge-{{ $cls->group->level }}">{{ $cls->group->name }}</span></td>
            <td class="text-sm">{{ $cls->coach->name }}</td>
            <td class="text-sm">{{ $chapterLabel($cls->chapter, 'Next chapter') }}</td>
            <td class="text-muted text-sm">{{ $cls->scheduled_for?->timezone($cls->displayTimezone())->format('d M Y · g:i A') }}</td>
            <td><span class="badge badge-cancelled">Cancelled</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endif
@endsection

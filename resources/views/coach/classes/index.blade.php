@extends('layouts.app')
@section('page-title','My Classes')

@section('sidebar-nav') @include('coach._sidebar') @endsection

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
      <div style="font-weight:700">{{ $live->name }} — LIVE NOW</div>
    <div style="font-size:12px;opacity:.8">{{ $live->group->name ?? '—' }} · {{ $chapterLabel($live->chapter) }} · {{ $live->group?->students?->count() ?? 0 }} in batch</div>
  </div>
  <a href="{{ route('coach.class.live', $live) }}" class="btn btn-sm" style="background:rgba(255,255,255,.25);color:#fff;border:none">Join</a>
</div>
@endif

<nav class="ui-tabs ui-section-tabs" aria-label="Classes">
  <a href="{{ route('coach.classes', ['range' => 'live']) }}" class="ui-tab {{ $range === 'live' ? 'is-active' : '' }}">Live <span class="ui-tab__count">{{ $counts['live'] ?? 0 }}</span></a>
  <a href="{{ route('coach.classes', ['range' => 'today']) }}" class="ui-tab {{ $range === 'today' ? 'is-active' : '' }}">Today <span class="ui-tab__count">{{ $counts['today'] ?? 0 }}</span></a>
  <a href="{{ route('coach.classes', ['range' => 'upcoming']) }}" class="ui-tab {{ $range === 'upcoming' ? 'is-active' : '' }}">Upcoming <span class="ui-tab__count">{{ $counts['upcoming'] ?? 0 }}</span></a>
  <a href="{{ route('coach.classes', ['range' => 'past']) }}" class="ui-tab {{ $range === 'past' ? 'is-active' : '' }}">Past <span class="ui-tab__count">{{ $counts['past'] ?? 0 }}</span></a>
  <a href="{{ route('coach.classes', ['range' => 'cancelled']) }}" class="ui-tab {{ $range === 'cancelled' ? 'is-active' : '' }}">Cancelled <span class="ui-tab__count">{{ $counts['cancelled'] ?? 0 }}</span></a>
</nav>

@if($range === 'live')
<div class="card">
  <div class="card-title">Live Class</div>
  @if($live)
    <div class="table-wrap">
      <table>
        <thead><tr><th>Class</th><th>Batch</th><th>Chapter</th><th>Started</th><th style="text-align:right">Action</th></tr></thead>
        <tbody>
          <tr>
            <td class="font-medium">{{ $live->name }}</td>
            <td>@if($live->group)<span class="badge badge-{{ $live->group->level }}">{{ $live->group->name }}</span>@else — @endif</td>
            <td class="text-sm">{{ $chapterLabel($live->chapter) }}</td>
            <td class="text-muted text-sm">{{ $live->started_at?->timezone($live->displayTimezone())->format('g:i A') }}</td>
            <td style="text-align:right"><a href="{{ route('coach.class.live', $live) }}" class="btn btn-sm btn-primary">Join</a></td>
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
        <thead><tr><th>When</th><th>Class</th><th>Batch</th><th>Chapter</th><th style="text-align:right">Action</th></tr></thead>
        <tbody>
          @foreach($todayClasses as $cls)
            @php
              $canStart = now()->utc()->gte($cls->scheduled_for->copy()->subMinutes(15)->utc())
                && now()->utc()->lt($cls->scheduled_for->copy()->addMinutes(60)->utc());
            @endphp
            <tr>
              <td>
                <div style="font-weight:600">{{ $cls->scheduled_for->timezone($cls->displayTimezone())->format('D, d M Y') }}</div>
                <div class="text-xs text-muted">{{ $cls->scheduled_for->timezone($cls->displayTimezone())->format('g:i A') }}</div>
              </td>
              <td class="font-medium">{{ $cls->name }}</td>
              <td>@if($cls->group)<span class="badge badge-{{ $cls->group->level }}">{{ $cls->group->name }}</span>@else — @endif</td>
              <td class="text-sm">{{ $chapterLabel($cls->chapter, 'Next chapter') }}</td>
              <td style="text-align:right">
                @if($canStart)
                  <form method="POST" action="{{ route('coach.class.start', $cls) }}" style="display:inline">
                    @csrf
                    <button class="btn btn-sm btn-primary">Join</button>
                  </form>
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
        <thead><tr><th>Class</th><th>Batch</th><th>Chapter</th><th>Date</th><th>Duration</th><th>Students</th><th>HW</th></tr></thead>
        <tbody>
          @foreach($pastClasses as $cls)
          <tr>
            <td class="font-medium">{{ $cls->name }}</td>
            <td><span class="badge badge-{{ $cls->group->level ?? 'ended' }}">{{ $cls->group->name ?? '—' }}</span></td>
            <td class="text-sm">{{ $chapterLabel($cls->chapter) }}</td>
            <td class="text-muted text-sm">{{ $cls->started_at?->timezone($cls->displayTimezone())->format('d M Y · g:i A') }}</td>
            <td class="text-sm">{{ $cls->duration }}</td>
            <td><span style="font-weight:600;color:var(--success)">{{ $cls->participants_count }}</span></td>
            <td>
              @if($cls->homework->count())
                <a href="{{ route('coach.homework.show', $cls->homework->first()) }}" class="btn btn-xs btn-ghost">View HW</a>
              @else
                <span class="text-muted text-xs">—</span>
              @endif
            </td>
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
        <thead><tr><th>When</th><th>Class</th><th>Batch</th><th>Chapter</th></tr></thead>
        <tbody>
          @foreach($upcomingClasses as $cls)
          <tr>
            <td>
              <div style="font-weight:600">{{ $cls->scheduled_for->timezone($cls->displayTimezone())->format('D, d M Y') }}</div>
              <div class="text-xs text-muted">{{ $cls->scheduled_for->timezone($cls->displayTimezone())->format('g:i A') }}</div>
            </td>
            <td class="font-medium">{{ $cls->name }}</td>
            <td>@if($cls->group)<span class="badge badge-{{ $cls->group->level }}">{{ $cls->group->name }}</span>@else — @endif</td>
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
        <thead><tr><th>Class</th><th>Batch</th><th>Chapter</th><th>Scheduled Time</th><th>Status</th></tr></thead>
        <tbody>
          @foreach($cancelledClasses as $cls)
          <tr>
            <td class="font-medium">{{ $cls->name }}</td>
            <td><span class="badge badge-{{ $cls->group->level ?? 'ended' }}">{{ $cls->group->name ?? '—' }}</span></td>
            <td class="text-sm">{{ $chapterLabel($cls->chapter) }}</td>
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

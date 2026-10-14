{{-- resources/views/admin/homework/index.blade.php --}}
@extends('layouts.app')
@section('page-title','Homework')
@php
  $isStaffView = auth()->user()?->isStaff();
  $routePrefix = $isStaffView ? 'staff' : 'admin';
@endphp
@section('sidebar-nav') @include($isStaffView ? 'staff._sidebar' : 'admin._sidebar') @endsection
@section('content')
<x-ui.page-header title="Homework" />

<form method="GET" class="ui-filter-bar">
  <div class="ui-filter-bar__main">
    <x-ui.select label="Batch" name="group_id" onchange="this.form.submit()">
      <option value="">All groups</option>
      @foreach($groups as $g)
      <option value="{{ $g->id }}" {{ $groupId==$g->id?'selected':'' }}>{{ $g->name }}</option>
      @endforeach
    </x-ui.select>

    <x-ui.select label="Coach" name="coach_id" onchange="this.form.submit()">
      <option value="">All coaches</option>
      @foreach($coaches as $c)
      <option value="{{ $c->id }}" {{ (string)($coachId ?? '')===(string)$c->id?'selected':'' }}>{{ $c->name }}</option>
      @endforeach
    </x-ui.select>

    <x-ui.select label="Student" name="student_id" onchange="this.form.submit()">
      <option value="">All students</option>
      @foreach($students as $student)
        <option value="{{ $student->id }}" {{ (string)($studentId ?? '') === (string)$student->id ? 'selected' : '' }}>
          {{ $student->name }} ({{ $student->username }})
        </option>
      @endforeach
    </x-ui.select>

    @if($groupId || !empty($coachId) || !empty($studentId))
      <x-ui.button :href="route($routePrefix.'.homework')" variant="secondary">Reset</x-ui.button>
    @endif
  </div>
</form>

<x-ui.table>
  <x-slot:head>
    <tr><th>Homework</th><th>Batch</th><th>Coach</th><th>Due</th><th>Student progress</th><th>Coach review</th><th>Feedback</th><th>Actions</th></tr>
  </x-slot:head>
  @forelse($homework as $hw)
  @php
    $stats = $hw->stats;
    $reviewBadge = match ($stats['review_status']) {
      'Review complete' => 'completed',
      'Review in progress' => 'in_progress',
      'Needs coach review' => 'pending',
      default => 'draft',
    };
  @endphp
  <tr>
    <td>
      <div class="font-medium">{{ $hw->title }}</div>
      <div class="text-xs text-muted">{{ $hw->chapter->name }}</div>
    </td>
    <td>@if($hw->group)<span class="badge badge-{{ $hw->group->level }}">{{ $hw->group->name }}</span>@else —@endif</td>
    <td class="text-sm">{{ $hw->assigner->name }}</td>
    <td class="text-muted text-sm">{{ $hw->due_date?->format('d M Y') ?? '—' }}</td>
    <td>
      <div class="homework-progress">
        <div class="homework-progress__meta">
          <strong>{{ $stats['completion_percent'] }}%</strong>
          <span>{{ $stats['done'] }}/{{ $stats['total'] }} solved</span>
        </div>
        <div class="progress"><div class="progress-fill" style="width:{{ $stats['completion_percent'] }}%"></div></div>
      </div>
    </td>
    <td>
      <span class="badge badge-{{ $reviewBadge }}">{{ $stats['review_status'] }}</span>
      <div class="text-xs text-muted homework-table-meta">{{ $stats['reviewed'] }}/{{ $stats['done'] }} solved work checked</div>
    </td>
    <td>
      <strong class="homework-feedback-count">{{ $stats['feedback_count'] }}</strong>
      <span class="text-xs text-muted">{{ $stats['feedback_count'] === 1 ? 'note' : 'notes' }}</span>
    </td>
    <td><a href="{{ route($routePrefix.'.homework.show', ['homework' => $hw, 'student_id' => $studentId]) }}" class="btn btn-xs btn-ghost">View report</a></td>
  </tr>
  @empty
  <tr><td colspan="8"><x-ui.empty-state title="No homework found" /></td></tr>
  @endforelse
</x-ui.table>
{{ $homework->links('components.ui.pagination') }}
@endsection

@extends('layouts.app')
@section('page-title', 'Homework Report')

@php
  $isStaffView = auth()->user()?->isStaff();
  $routePrefix = $isStaffView ? 'staff' : 'admin';
  $homeworkSubtitle = collect([
    $homework->group?->name,
    $homework->chapter->name,
    'Assigned by '.$homework->assigner->name,
    $homework->due_date ? 'Due '.$homework->due_date->format('d M Y') : null,
  ])->filter()->implode(' · ');
@endphp

@section('sidebar-nav')
@include($isStaffView ? 'staff._sidebar' : 'admin._sidebar')
@endsection

@section('content')
<x-ui.page-header :title="$homework->title" :subtitle="$homeworkSubtitle">
  <x-slot:actions>
    <x-ui.button :href="route($routePrefix.'.homework')" variant="secondary">Back</x-ui.button>
  </x-slot:actions>
</x-ui.page-header>

<form method="GET" class="ui-filter-bar">
  <div class="ui-filter-bar__main">
    <x-ui.select label="Student" name="student_id" onchange="this.form.submit()">
      <option value="">All students</option>
      @foreach($studentOptions as $student)
        <option value="{{ $student->id }}" {{ $selectedStudentId === (int) $student->id ? 'selected' : '' }}>
          {{ $student->name }} ({{ $student->username }})
        </option>
      @endforeach
    </x-ui.select>
    @if($selectedStudentId)
      <x-ui.button :href="route($routePrefix.'.homework.show', $homework)" variant="secondary">Reset</x-ui.button>
    @endif
  </div>
</form>

<div class="stats homework-stats mb-4">
  <div class="stat"><div class="stat-label">Total students</div><div class="stat-val">{{ $stats['total'] }}</div></div>
  <div class="stat"><div class="stat-label">Solved</div><div class="stat-val" style="color:var(--success)">{{ $stats['done'] }}</div></div>
  <div class="stat stat-accent">
    <div class="stat-label">Completion</div><div class="stat-val">{{ $stats['completion_percent'] }}%</div>
  </div>
  <div class="stat"><div class="stat-label">Coach checked</div><div class="stat-val" style="color:var(--info)">{{ $stats['reviewed'] }}</div></div>
  <div class="stat"><div class="stat-label">Awaiting review</div><div class="stat-val" style="color:var(--warning)">{{ $stats['awaiting_review'] }}</div></div>
  <div class="stat"><div class="stat-label">Feedback given</div><div class="stat-val">{{ $stats['feedback_count'] }}</div></div>
</div>

<div class="card">
  <div class="homework-report-heading">
    <div>
      <div class="card-title">Student submissions</div>
      <div class="text-sm text-muted">Admin and Staff can view progress, coach review status, and feedback.</div>
    </div>
    @php
      $overallReviewBadge = match ($stats['review_status']) {
        'Review complete' => 'completed',
        'Review in progress' => 'in_progress',
        'Needs coach review' => 'pending',
        default => 'draft',
      };
    @endphp
    <span class="badge badge-{{ $overallReviewBadge }}">{{ $stats['review_status'] }}</span>
  </div>
  @if($visibleStudents->isNotEmpty())
    @foreach($visibleStudents as $student)
      @php $sub = $homework->submissions->firstWhere('user_id', $student->id); @endphp
      @php
        $isSolved = $sub?->status === 'done';
        $isReviewed = $isSolved && $sub->marked_correct !== null;
        $reviewLabel = $isReviewed ? 'Checked by coach' : ($isSolved ? 'Awaiting coach review' : 'Not ready for review');
        $reviewClass = $isReviewed ? 'completed' : ($isSolved ? 'pending' : 'draft');
      @endphp
      <div class="homework-submission-card">
        <div class="homework-submission-card__top">
          <div class="flex items-center gap-2">
            <div class="av" style="background:{{ $student->avatar_color }}">{{ $student->initials }}</div>
            <div>
              <div style="font-weight:600;font-size:14px">{{ $student->name }}</div>
              <div class="text-xs text-muted">{{ $student->username }}</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            @if($sub)
              <span class="badge badge-{{ $sub->status }}">{{ str_replace('_', ' ', $sub->status) }}</span>
              @if($sub->marked_correct !== null)
                <x-ui.badge :status="$sub->marked_correct ? 'completed' : 'cancelled'" :label="$sub->marked_correct ? 'Correct' : 'Wrong'" />
              @endif
              @if($sub->submitted_at)
                <span class="text-xs text-muted">Submitted {{ $sub->submitted_at->timezone('Asia/Kolkata')->format('d M Y, g:i A') }} <span style="color:#94a3b8">IST</span></span>
              @endif
            @else
              <span class="badge badge-pending">Not started</span>
            @endif
          </div>
        </div>
        <div class="homework-review-grid">
          <div class="homework-review-field">
            <span class="homework-review-field__label">Coach review</span>
            <span class="badge badge-{{ $reviewClass }}">{{ $reviewLabel }}</span>
          </div>
          <div class="homework-review-field">
            <span class="homework-review-field__label">Coach feedback</span>
            <span class="homework-review-field__value {{ $sub?->coach_note ? '' : 'text-muted' }}">
              {{ $sub?->coach_note ?: 'No feedback added' }}
            </span>
          </div>
        </div>
      </div>
    @endforeach
  @else
    <x-ui.empty-state title="No student found" description="No student is assigned to this homework." />
  @endif
</div>
@endsection

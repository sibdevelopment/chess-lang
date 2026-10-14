@extends('layouts.app')
@php
  $routePrefix = auth()->user()?->isStaff() ? 'staff' : 'admin';
  [$classDetailTitle, $classSection, $backLabel] = match ($class->status) {
      'live' => ['Live Class Details', 'live', 'Back to Live Classes'],
      'cancelled' => ['Cancelled Class Details', 'cancelled', 'Back to Cancelled Classes'],
      'ended', 'completed' => ['Past Class Details', 'past', 'Back to Past Classes'],
      default => [
          'Scheduled Class Details',
          $class->scheduledStart()?->isToday() ? 'today' : 'upcoming',
          'Back to Classes',
      ],
  };
@endphp
@section('page-title', $classDetailTitle)
@section('sidebar-nav') @include(auth()->user()?->isStaff() ? 'staff._sidebar' : 'admin._sidebar') @endsection

@section('content')
<div class="classroom-shell">
  <x-ui.page-header :title="$classDetailTitle" :subtitle="$class->name">
    <x-slot:actions>
      <x-ui.button :href="route($routePrefix.'.classes', ['section' => $classSection])" variant="secondary">{{ $backLabel }}</x-ui.button>
    </x-slot:actions>
  </x-ui.page-header>

  <div class="card">
    @include('admin.classes._details', [
      'class' => $class,
      'attendedStudents' => $attendedStudents,
      'absentStudents' => $absentStudents,
      'leaderboard' => $leaderboard,
      'puzzleAudit' => $puzzleAudit,
      'assignedStudents' => $assignedStudents ?? collect(),
    ])
  </div>
</div>
@endsection

@push('scripts')
@include('partials._class-puzzle-audit-assets')
@endpush

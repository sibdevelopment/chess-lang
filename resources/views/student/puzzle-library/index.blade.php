@extends('layouts.app')
@section('page-title', 'Puzzle Bank')
@section('sidebar-nav') @include('student._sidebar') @endsection

@section('content')
<x-ui.page-header title="Puzzle Bank" />

@if($sets->isEmpty())
  <x-ui.empty-state title="No puzzle sets available" />
@else
  <div class="grid-2">
    @foreach($sets as $set)
      @php
        $progress = $set->progress;
        $actionLabel = ($progress['completed_puzzles'] ?? 0) > 0 ? 'Continue' : 'Start';
      @endphp
      <div class="card">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-semibold" style="font-size:18px">{{ $set->name }}</div>
            <div class="text-sm text-muted mt-1">{{ $set->total_puzzles }} Puzzles</div>
          </div>
          <span class="badge badge-{{ $set->is_active ? 'active' : 'inactive' }}">{{ $set->is_active ? 'Active' : 'Inactive' }}</span>
        </div>
        <div class="grid-2 mt-4" style="gap:12px">
          <div><div class="text-xs text-muted">Points</div><div class="font-semibold">{{ $progress['total_points'] ?? 0 }}</div></div>
          <div><div class="text-xs text-muted">Progress</div><div class="font-semibold">{{ $progress['progress_percent'] ?? 0 }}%</div></div>
        </div>
        <div class="progress mt-3"><div class="progress-fill" style="width:{{ $progress['progress_percent'] ?? 0 }}%"></div></div>
        <div class="mt-4">
          <a href="{{ $set->start_url }}" class="btn btn-primary">{{ $actionLabel }}</a>
        </div>
      </div>
    @endforeach
  </div>
@endif
@endsection

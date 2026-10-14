@extends('layouts.app')
@section('page-title','Homework')

@section('sidebar-nav') @include('student._sidebar') @endsection

@section('content')
@php
  $homeworkTabs = collect(['all'=>'All','pending'=>'Pending','in_progress'=>'In progress','done'=>'Done'])
    ->map(fn ($label, $value) => ['label' => $label, 'href' => route('student.homework', ['status' => $value]), 'active' => $status === $value])
    ->values()
    ->all();
@endphp
<x-ui.page-header title="Homework" />

<x-ui.tabs :items="$homeworkTabs" aria-label="Homework status" class="ui-section-tabs mb-4" />

@forelse($homework as $hw)
@php $sub = $hw->submissions->first(); $subStatus = $sub?->status ?? 'pending'; @endphp
<div class="card mb-3" style="border-left:4px solid {{ $subStatus==='done'?'var(--success)':($subStatus==='in_progress'?'var(--info)':'var(--border)') }}">
  <div class="flex items-center justify-between flex-wrap gap-2">
    <div style="flex:1;min-width:200px">
      <div style="font-size:15px;font-weight:700;margin-bottom:4px">{{ $hw->title }}</div>
      <div class="flex gap-2 flex-wrap">
        <span class="badge badge-{{ $hw->group->level ?? 'ended' }}">{{ $hw->group->name ?? '—' }}</span>
        <span class="badge" style="background:#f1f5f9;color:#475569">{{ $hw->chapter->name }}</span>
        @if($hw->due_date)
        <span class="text-xs text-muted" style="padding:2px 0">
          {{ $hw->due_date->isPast() && $subStatus!=='done' ? 'Overdue:' : 'Due:' }}
          {{ $hw->due_date->format('d M Y') }}
        </span>
        @endif
      </div>
    </div>

    <div class="flex items-center gap-3">
      {{-- Mark result --}}
      @if($subStatus === 'done' && $sub?->marked_correct !== null)
      <div style="text-align:center">
        <x-ui.badge :status="$sub->marked_correct ? 'completed' : 'cancelled'" :label="$sub->marked_correct ? 'Correct' : 'Wrong'" />
      </div>
      @endif

      @if($subStatus === 'done')
        <span class="badge badge-{{ $sub?->marked_correct !== null ? 'completed' : 'pending' }}">
          {{ $sub?->marked_correct !== null ? 'Checked by coach' : 'Awaiting coach review' }}
        </span>
      @endif

      <span class="badge badge-{{ $subStatus }}">{{ str_replace('_',' ',$subStatus) }}</span>

      @if($subStatus !== 'done')
      <a href="{{ route('student.homework.solve', $hw) }}" class="btn btn-primary btn-sm">
        {{ $subStatus==='in_progress' ? 'Continue' : 'Solve' }}
      </a>
      @else
      <a href="{{ route('student.homework.solve', $hw) }}" class="btn btn-ghost btn-sm">Review</a>
      @endif
    </div>
  </div>

  @if($sub?->coach_note)
  <div class="homework-student-feedback">
    <strong>Coach feedback:</strong> {{ $sub->coach_note }}
  </div>
  @endif
</div>
@empty
<x-ui.empty-state title="No homework yet" description="Homework will appear here after your coach finishes a class." />
@endforelse

{{ $homework->links('components.ui.pagination') }}
@endsection

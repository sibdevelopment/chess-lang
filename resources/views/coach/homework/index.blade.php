@extends('layouts.app')
@section('page-title','Homework')

@section('sidebar-nav') @include('coach._sidebar') @endsection

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
    <x-ui.select label="Student" name="student_id" onchange="this.form.submit()">
      <option value="">All students</option>
      @foreach($students as $student)
        <option value="{{ $student->id }}" {{ (string)($studentId ?? '') === (string)$student->id ? 'selected' : '' }}>
          {{ $student->name }} ({{ $student->username }})
        </option>
      @endforeach
    </x-ui.select>
    <x-ui.select label="Status" name="status" onchange="this.form.submit()">
      <option value="all" {{ $status=='all'?'selected':'' }}>All statuses</option>
      <option value="pending" {{ $status=='pending'?'selected':'' }}>Pending</option>
      <option value="in_progress" {{ $status=='in_progress'?'selected':'' }}>In progress</option>
      <option value="done" {{ $status=='done'?'selected':'' }}>Solved</option>
      <option value="awaiting_review" {{ $status=='awaiting_review'?'selected':'' }}>Awaiting review</option>
      <option value="reviewed" {{ $status=='reviewed'?'selected':'' }}>Reviewed</option>
    </x-ui.select>
    @if($groupId || !empty($studentId) || $status!='all')
    <x-ui.button :href="route('coach.homework')" variant="secondary">Reset</x-ui.button>
    @endif
  </div>
</form>

@forelse($homework as $hw)
@php $stats = $hw->stats; @endphp
<div class="card mb-3">
  <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
    {{-- Left: meta --}}
    <div style="flex:1;min-width:220px">
      <div style="font-size:15px;font-weight:700;margin-bottom:4px">{{ $hw->title }}</div>
      <div class="flex gap-2 flex-wrap mb-2">
        @if($hw->group)<span class="badge badge-{{ $hw->group->level }}">{{ $hw->group->name }}</span>@endif
        <span class="badge" style="background:#f1f5f9;color:#475569">{{ $hw->chapter->name }}</span>
        @if($hw->auto_assigned)<span class="badge" style="background:#ede9fe;color:#5b21b6">Auto-assigned</span>@endif
        @if($hw->due_date)<span class="text-xs text-muted">Due: {{ $hw->due_date->format('d M Y') }}</span>@endif
      </div>

      <div class="flex items-center gap-2 mb-3">
        <div class="progress" style="flex:1"><div class="progress-fill" style="width:{{ $stats['completion_percent'] }}%"></div></div>
        <span class="homework-progress-value">{{ $stats['completion_percent'] }}% solved</span>
      </div>

      <div class="flex gap-2 flex-wrap">
        <span class="badge badge-pending">{{ $stats['pending'] }} pending</span>
        <span class="badge badge-in_progress">{{ $stats['in_progress'] }} in progress</span>
        <span class="badge badge-done">{{ $stats['done'] }} solved</span>
        <span class="badge badge-{{ $stats['awaiting_review'] > 0 ? 'pending' : 'completed' }}">{{ $stats['awaiting_review'] }} awaiting review</span>
        <span class="badge badge-completed">{{ $stats['reviewed'] }} checked</span>
        <span class="badge">{{ $stats['feedback_count'] }} feedback notes</span>
      </div>
    </div>

    {{-- Right: per-student rows --}}
    <div style="flex:2;min-width:300px">
      <x-ui.table :wrapped="false" compact>
        <x-slot:head>
          <tr>
            <th style="text-align:left">Student</th>
            <th style="text-align:center">Status</th>
            <th style="text-align:center">Coach review</th>
            <th>Feedback</th>
            <th style="text-align:right">Action</th>
          </tr>
        </x-slot:head>
        @foreach($hw->submissions as $sub)
        <tr>
          <td>
            <div class="flex items-center gap-2">
              <div class="av av-sm" style="background:{{ $sub->student->avatar_color }}">{{ $sub->student->initials }}</div>
              {{ $sub->student->name }}
            </div>
          </td>
          <td style="text-align:center">
            <span class="badge badge-{{ $sub->status }}">{{ str_replace('_',' ',$sub->status) }}</span>
          </td>
          <td style="text-align:center">
            @if($sub->status === 'done' && $sub->marked_correct !== null)
              <x-ui.badge :status="$sub->marked_correct ? 'completed' : 'cancelled'" :label="$sub->marked_correct ? 'Correct · Checked' : 'Wrong · Checked'" />
            @elseif($sub->status === 'done')
              <span class="badge badge-pending">Awaiting review</span>
            @else
              <span class="text-muted text-xs">Not ready</span>
            @endif
          </td>
          <td class="homework-feedback-cell">
            <span class="{{ $sub->coach_note ? '' : 'text-muted' }}">{{ $sub->coach_note ?: 'No feedback' }}</span>
          </td>
          <td style="text-align:right">
            @if($sub->status === 'done')
              <button
                type="button"
                class="btn btn-xs {{ $sub->marked_correct === null ? '' : 'btn-ghost' }}"
                data-review-submission
                data-action="{{ route('coach.homework.mark', $sub) }}"
                data-student="{{ $sub->student->name }}"
                data-result="{{ $sub->marked_correct === null ? '' : ($sub->marked_correct ? '1' : '0') }}"
                data-note="{{ $sub->coach_note }}"
              >{{ $sub->marked_correct === null ? 'Review' : 'Edit review' }}</button>
            @else
              <span class="text-xs text-muted">—</span>
            @endif
          </td>
        </tr>
        @endforeach
      </x-ui.table>
    </div>
  </div>

  <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
    <a href="{{ route('coach.homework.show', ['homework' => $hw, 'student_id' => $studentId]) }}" class="btn btn-sm">Full Report</a>
  </div>
</div>
@empty
<x-ui.empty-state title="No homework assigned" description="Homework will appear after a class is completed." />
@endforelse

{{ $homework->links('components.ui.pagination') }}

<x-ui.modal id="homework-review-modal" max-width="560px">
  <x-slot:header>
    <div class="ui-modal-heading">
      <div>
        <div class="ui-modal-title">Check homework</div>
        <div class="text-sm text-muted" id="homework-review-student"></div>
      </div>
      <x-ui.button type="button" variant="text" size="sm" class="ui-button--icon" data-homework-review-close aria-label="Close">
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
      </x-ui.button>
    </div>
  </x-slot:header>
  <form method="POST" action="#" id="homework-review-form" class="ui-modal-form">
    @csrf
    <x-ui.select label="Result" name="marked_correct" id="homework-review-result" required>
      <option value="" disabled>Select result</option>
      <option value="1">Correct</option>
      <option value="0">Needs improvement</option>
    </x-ui.select>
    <x-ui.textarea label="Feedback for student" name="coach_note" id="homework-review-note" rows="4" maxlength="500" placeholder="What was done well, or what should the student practise next?" />
  </form>
  <x-slot:footer>
    <x-ui.button type="button" variant="secondary" data-homework-review-close>Cancel</x-ui.button>
    <x-ui.button type="submit" form="homework-review-form">Save review</x-ui.button>
  </x-slot:footer>
</x-ui.modal>
@endsection

@push('scripts')
<script>
(function () {
  const modal = document.getElementById('homework-review-modal');
  const form = document.getElementById('homework-review-form');
  const student = document.getElementById('homework-review-student');
  const result = document.getElementById('homework-review-result');
  const note = document.getElementById('homework-review-note');

  document.querySelectorAll('[data-review-submission]').forEach(button => {
    button.addEventListener('click', () => {
      form.action = button.dataset.action;
      student.textContent = button.dataset.student;
      result.value = button.dataset.result;
      note.value = button.dataset.note || '';
      modal.style.display = 'flex';
    });
  });

  document.querySelectorAll('[data-homework-review-close]').forEach(button => {
    button.addEventListener('click', () => { modal.style.display = 'none'; });
  });
})();
</script>
@endpush

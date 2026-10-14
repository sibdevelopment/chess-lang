@extends('layouts.app')
@section('page-title', $set->name)
@section('sidebar-nav') @include('student._sidebar') @endsection

@section('content')
<x-ui.page-header :title="$set->name">
  <x-slot:actions>
    <div class="flex gap-2 flex-wrap">
      <x-ui.button :href="route('student.puzzle-library.index')" variant="secondary">Back</x-ui.button>
      <span class="badge">{{ $progress['completed_puzzles'] ?? 0 }}/{{ $set->total_puzzles }} Solved</span>
      <span class="badge">{{ $progress['total_points'] ?? 0 }} Points</span>
    </div>
  </x-slot:actions>
</x-ui.page-header>

<div class="card" style="padding:18px">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Puzzle</th>
          <th>Status</th>
          <th>Wrong Attempts</th>
          <th>Hints Used</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @foreach($puzzles as $puzzle)
          @php
            $attempt = $puzzle->student_attempt;
            $status = $attempt?->status ?? 'not_started';
            $statusLabel = match($status) {
              'completed' => 'Correct',
              'in_progress' => 'In Progress',
              default => 'Not Started',
            };
            $btnLabel = $status === 'completed' ? 'Review' : ($status === 'in_progress' ? 'Continue' : 'Start');
          @endphp
          <tr>
            <td style="font-weight:700">Puzzle {{ $puzzle->puzzle_order }}</td>
            <td><span class="badge badge-{{ $status === 'completed' ? 'active' : ($status === 'in_progress' ? 'warning' : 'inactive') }}">{{ $statusLabel }}</span></td>
            <td>{{ (int) ($attempt?->wrong_attempts ?? 0) }}</td>
            <td>{{ (int) ($attempt?->hint_count ?? 0) }}</td>
            <td><a href="{{ route('student.puzzle-library.solve', [$set, $puzzle]) }}" class="btn btn-sm btn-primary">{{ $btnLabel }}</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection

@extends('layouts.app')
@section('page-title', $chapter->name)

@section('sidebar-nav') @include('coach._sidebar') @endsection

@section('content')
<x-ui.page-header
  title="#{{ $chapter->sequence_number }} — {{ $chapter->name }}"
  subtitle="{{ $chapter->puzzle_count }} total puzzles · Added {{ $chapter->created_at->format('d M Y') }} · View only"
>
  <x-slot:actions>
    <div class="flex items-center gap-2 flex-wrap">
      <x-ui.badge :label="ucfirst($chapter->level)" variant="secondary" />
      <x-ui.button :href="route('coach.database')" variant="secondary">Back</x-ui.button>
    </div>
  </x-slot:actions>
</x-ui.page-header>

@include('partials._chapter-viewer', [
  'pgn'         => $chapter->effective_pgn,
  'chapterName' => $chapter->name,
  'hostId'      => 'db',
  'boardSize'   => 480,
])

@if($chapter->description)
<div class="card card-sm mt-3">
  <div class="card-title" style="font-size:13px">Notes</div>
  <div class="text-sm text-muted">{{ $chapter->description }}</div>
</div>
@endif
@endsection

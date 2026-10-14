@extends('layouts.app')
@section('page-title', 'Puzzle Bank')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@php
  $isReadOnlyDatabase = auth()->user()?->isStaff();
  $databaseRoutePrefix = $isReadOnlyDatabase ? 'staff' : 'admin';
@endphp

@section('content')
<x-ui.page-header title="Puzzle Bank">
    @if(!$isReadOnlyDatabase && $sets->count() > 0)
      <x-slot:actions><x-ui.button :href="route('admin.puzzle-library.create')" variant="primary">Create Puzzle Set</x-ui.button></x-slot:actions>
    @endif
</x-ui.page-header>

@if($sets->count() === 0)
  <x-ui.empty-state
    title="No puzzle sets found"
    :description="$isReadOnlyDatabase ? 'Puzzle sets will appear here when they are published.' : 'Create the first puzzle set by importing a PGN file.'"
    :action-label="$isReadOnlyDatabase ? null : 'Create Puzzle Set'"
    :action-href="$isReadOnlyDatabase ? null : route('admin.puzzle-library.create')"
  />
@else
  <x-ui.table>
    <x-slot:head>
      <tr>
        <th>Puzzle Set Name</th>
        <th>Total Puzzles</th>
        <th>Status</th>
        <th>Created Date</th>
        <th>Actions</th>
      </tr>
    </x-slot:head>
    @foreach($sets as $set)
      <tr>
        <td><a href="{{ route($databaseRoutePrefix.'.puzzle-library.show', $set) }}" style="font-weight:700;color:inherit">{{ $set->name }}</a></td>
        <td>{{ $set->total_puzzles }}</td>
        <td><span class="badge badge-{{ $set->is_active ? 'active' : 'inactive' }}">{{ $set->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td>{{ $set->created_at?->format('d M Y') }}</td>
        <td>
          <div class="flex gap-2">
            <a href="{{ route($databaseRoutePrefix.'.puzzle-library.show', $set) }}" class="btn btn-xs btn-ghost">View</a>
            @unless($isReadOnlyDatabase)
              <a href="{{ route('admin.puzzle-library.edit', $set) }}" class="btn btn-xs btn-ghost">Edit</a>
              <form method="POST" action="{{ route('admin.puzzle-library.toggle-status', $set) }}">
                @csrf
                <button type="submit" class="btn btn-xs {{ $set->is_active ? 'btn-danger' : 'btn-success' }}">
                  {{ $set->is_active ? 'Deactivate' : 'Activate' }}
                </button>
              </form>
            @endunless
          </div>
        </td>
      </tr>
    @endforeach
  </x-ui.table>

  {{ $sets->onEachSide(2)->links('components.ui.pagination') }}
@endif
@endsection

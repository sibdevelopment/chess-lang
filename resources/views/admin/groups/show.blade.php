@extends('layouts.app')
@section('page-title', $group->name)
@php
  $routePrefix = auth()->user()?->isStaff() ? 'staff' : 'admin';
  $assignedStudents = $group->students->sortBy('name')->values();
@endphp
@section('sidebar-nav') @include(auth()->user()?->isStaff() ? 'staff._sidebar' : 'admin._sidebar') @endsection

@section('content')
<x-ui.page-header
  :title="$group->name"
  subtitle="{{ $group->coach->name ?? 'Unassigned' }} · {{ ucfirst($group->level) }}"
>
  <x-slot:actions>
    <div class="group-manage-tools">
      <x-ui.badge :status="$group->is_active ? 'active' : 'inactive'" :label="$group->is_active ? 'Active' : 'Inactive'" />
      <x-ui.button :href="route($routePrefix.'.groups')" variant="secondary">Back</x-ui.button>
      <a href="{{ route($routePrefix.'.groups.edit', $group) }}" class="btn btn-ghost btn-sm">Edit Batch</a>
      <form method="POST" action="{{ route($routePrefix.'.groups.toggle-status', $group) }}">
        @csrf
        <button class="btn btn-sm {{ $group->is_active ? 'btn-danger' : 'btn-success' }}" type="submit" onclick="return confirm('{{ $group->is_active ? 'Inactivate' : 'Activate' }} this group?')">
          {{ $group->is_active ? 'Inactivate' : 'Activate' }}
        </button>
      </form>
      @if($group->is_active)
        <button
          class="btn btn-primary btn-sm"
          type="button"
          data-assignment-modal="group-show-students-modal"
          data-assignment-title="Assign Students"
          data-assignment-action="{{ route($routePrefix.'.groups.assign-students', $group) }}"
          data-assignment-selected="{{ $assignedStudents->pluck('id')->implode(',') }}"
        >Assign Students</button>
      @endif
    </div>
  </x-slot:actions>
</x-ui.page-header>

<div class="card">
  <div class="group-student-toolbar">
    <div class="card-title">Assigned Students</div>
    <x-ui.search id="assigned-search-input" name="assigned_search" placeholder="Search students" />
  </div>

  @if($assignedStudents->isNotEmpty())
    <div class="table-wrap">
      <table class="group-manage-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Username</th>
            <th>Country</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($assignedStudents as $student)
            <tr class="assigned-student-row" data-student-name="{{ strtolower($student->name.' '.$student->username) }}">
              <td>
                <div class="flex items-center gap-2">
                  <div class="av av-sm" style="background:{{ $student->avatar_color }}">{{ $student->initials }}</div>
                  <span class="font-medium">{{ $student->name }}</span>
                </div>
              </td>
              <td><code style="font-size:12px;background:var(--surface2);padding:2px 6px;border-radius:4px">{{ $student->username }}</code></td>
              <td>
                @if($student->country)
                  <span class="badge">{{ \App\Models\User::studentCountryLabel($student->country) }}</span>
                @else
                  <span class="text-muted text-xs">—</span>
                @endif
              </td>
              <td><span class="badge badge-{{ $student->is_active ? 'active' : 'inactive' }}">{{ $student->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="group-manage-empty">
      <div class="empty">No Students</div>
      @if($group->is_active)
        <button
          class="btn btn-primary btn-sm mt-3"
          type="button"
          data-assignment-modal="group-show-students-modal"
          data-assignment-title="Assign Students"
          data-assignment-action="{{ route($routePrefix.'.groups.assign-students', $group) }}"
          data-assignment-selected="{{ $assignedStudents->pluck('id')->implode(',') }}"
        >Assign Students</button>
      @endif
    </div>
  @endif
</div>

@include('components.assignment-modal', [
  'modalId' => 'group-show-students-modal',
  'title' => 'Assign Students',
  'inputName' => 'student_ids[]',
  'availableItems' => $assignableStudents->map(fn ($student) => [
      'id' => $student->id,
      'title' => $student->name,
      'meta' => $student->username.($student->country ? ' · '.\App\Models\User::studentCountryLabel($student->country) : ''),
      'badge' => $student->is_active ? 'Active' : 'Inactive',
  ]),
])
@endsection

@push('scripts')
<script>
window.ArcherAssignmentModal?.init('group-show-students-modal');

document.getElementById('assigned-search-input')?.addEventListener('input', function () {
  const query = this.value.toLowerCase().trim();
  document.querySelectorAll('.assigned-student-row').forEach(row => {
    const hay = row.dataset.studentName || '';
    row.style.display = !query || hay.includes(query) ? '' : 'none';
  });
});
</script>
@endpush

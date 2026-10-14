@php
  $isDashboardHub = $isDashboardHub ?? false;
  $tabRoute = $isDashboardHub ? $routePrefix.'.dashboard' : $routePrefix.'.students';
  $tabBase = $isDashboardHub ? ['tab' => 'students'] : [];
  $studentStatusTabs = [
    [
      'label' => 'Active',
      'active' => true,
      'count' => $counts['active'] ?? $students->total(),
    ],
  ];
@endphp

@include('admin.people._shared-assets')

<section class="ui-page-stack">
  @if($isDashboardHub)
    <h2 class="people-module-title">Students</h2>
  @else
    <x-ui.page-header title="Students" />
  @endif

  <x-ui.filter-bar>
    <x-ui.tabs :items="$studentStatusTabs" aria-label="Student status filters" />
    <x-slot:actions>
      <form method="GET" class="people-search-form">
        @if($isDashboardHub)<input type="hidden" name="tab" value="students">@endif
        <x-ui.search name="search" :value="$search" placeholder="Search students" data-people-search-input />
      </form>
    </x-slot:actions>
  </x-ui.filter-bar>

  <div class="ui-surface-block">
    <x-ui.table table-class="ui-table--people">
      <x-slot:head>
        <tr>
          <th>Student</th>
          <th>Username</th>
          <th>Country</th>
          <th>Batches</th>
          <th>Joined</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </x-slot:head>
      @forelse($students as $student)
        @php
          $studentLifecycle = $student->studentLifecycleStatus();
        @endphp
        <tr>
          <td>
            <div class="ui-entity">
              <div class="av av-sm ui-entity__avatar" style="background:{{ $student->avatar_color }}">{{ $student->initials }}</div>
              <div class="ui-entity__copy">
                <span class="ui-entity__title">{{ $student->name }}</span>
              </div>
            </div>
          </td>
          <td><code>{{ $student->username }}</code></td>
          <td>
            @if($student->country)
              <x-ui.badge variant="secondary">{{ \App\Models\User::studentCountryLabel($student->country) }}</x-ui.badge>
            @else
              <span class="ui-meta-text">—</span>
            @endif
          </td>
          <td>
            <div class="ui-stack-badges">
              @forelse($student->groups as $group)
                <x-ui.badge variant="secondary">{{ $group->name }}</x-ui.badge>
              @empty
                <span class="ui-meta-text">—</span>
              @endforelse
            </div>
          </td>
          <td class="ui-meta-text">{{ $student->created_at->format('d M Y') }}</td>
          <td>
            <x-ui.badge
              :status="$studentLifecycle === 'new_id' ? 'new_id' : ($studentLifecycle === 'active' ? 'active' : 'inactive')"
              :label="$studentLifecycle === 'new_id' ? 'New ID' : ($studentLifecycle === 'active' ? 'Active' : 'Inactive')"
            />
          </td>
          <td>
            <div class="ui-row-actions">
              <x-ui.button
                type="button"
                variant="secondary"
                size="sm"
                class="ui-button--icon ui-action-button"
                aria-label="Assign Batches"
                title="Assign Batches"
                data-assignment-modal="student-groups-modal"
                data-assignment-title="Assign Batches"
                data-assignment-action="{{ route($routePrefix.'.students.assign-groups', $student) }}"
                data-assignment-selected="{{ $student->groups->pluck('id')->implode(',') }}"
              >
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                  <path d="M10 3.33334L15.8333 6.66668L10 10L4.16667 6.66668L10 3.33334Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                  <path d="M15.8333 10L10 13.3333L4.16667 10" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                  <path d="M15.8333 13.3333L10 16.6667L4.16667 13.3333" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
              </x-ui.button>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7"><x-ui.empty-state title="No students found" /></td>
        </tr>
      @endforelse
    </x-ui.table>

    {{ $students->onEachSide(2)->links('components.ui.pagination') }}
  </div>
</section>

@include('components.assignment-modal', [
  'modalId' => 'student-groups-modal',
  'title' => 'Assign Batches',
  'inputName' => 'group_ids[]',
  'availableItems' => $assignableGroups->map(fn ($group) => [
      'id' => $group->id,
      'title' => $group->name,
      'meta' => ucfirst($group->level).' · '.($group->coach->name ?? 'Unassigned'),
      'badge' => $group->is_active ? 'Active' : 'Inactive',
  ]),
])

@push('scripts')
<script>
(function(){
  window.ArcherAssignmentModal?.init('student-groups-modal');
})();
</script>
@endpush

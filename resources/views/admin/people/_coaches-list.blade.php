@php
  $isDashboardHub = $isDashboardHub ?? false;
  $tabRoute = $isDashboardHub ? $routePrefix.'.dashboard' : $routePrefix.'.coaches';
  $tabBase = $isDashboardHub ? ['tab' => 'coaches'] : [];
  $statusFilter = request('status', 'all');
  $statusFilter = in_array($statusFilter, ['all', 'active', 'inactive'], true) ? $statusFilter : 'all';
  $statusTabs = [
    ['label' => 'All', 'href' => route($tabRoute, array_filter($tabBase + ['search' => $search ?? null])), 'active' => $statusFilter === 'all'],
    ['label' => 'Active', 'href' => route($tabRoute, array_filter($tabBase + ['status' => 'active', 'search' => $search ?? null])), 'active' => $statusFilter === 'active', 'count' => $counts['active'] ?? 0],
    ['label' => 'Inactive', 'href' => route($tabRoute, array_filter($tabBase + ['status' => 'inactive', 'search' => $search ?? null])), 'active' => $statusFilter === 'inactive', 'count' => $counts['inactive'] ?? 0],
  ];
@endphp

@include('admin.people._shared-assets')

<section class="ui-page-stack">
  @if($isDashboardHub)
    <h2 class="people-module-title">Coaches</h2>
  @else
    <x-ui.page-header title="Coaches" />
  @endif

  <x-ui.filter-bar>
    <x-ui.tabs :items="$statusTabs" aria-label="Coach status filters" />
    <x-slot:actions>
      <form method="GET" class="people-search-form">
        @if($isDashboardHub)<input type="hidden" name="tab" value="coaches">@endif
        @if($statusFilter !== 'all')<input type="hidden" name="status" value="{{ $statusFilter }}">@endif
        <x-ui.search name="search" :value="$search" placeholder="Search coaches" data-people-search-input />
      </form>
    </x-slot:actions>
  </x-ui.filter-bar>

  <div class="ui-surface-block">
    <x-ui.table table-class="ui-table--people">
      <x-slot:head>
        <tr>
          <th>Coach</th>
          <th>Username</th>
          <th>Batches</th>
          <th>Joined</th>
          <th>Status</th>
        </tr>
      </x-slot:head>
      @forelse($coaches as $coach)
        <tr data-coach-status="{{ $coach->is_active ? 'active' : 'inactive' }}">
          <td>
            <div class="ui-entity">
              <div class="av av-sm ui-entity__avatar" style="background:{{ $coach->avatar_color }}">{{ $coach->initials }}</div>
              <div class="ui-entity__copy">
                <span class="ui-entity__title">{{ $coach->name }}</span>
              </div>
            </div>
          </td>
          <td><code>{{ $coach->username }}</code></td>
          <td><span class="ui-inline-summary"><strong>{{ $coach->coached_groups_count }}</strong> batches</span></td>
          <td class="ui-meta-text">{{ $coach->created_at->format('d M Y') }}</td>
          <td><x-ui.badge :status="$coach->is_active ? 'active' : 'inactive'" :label="$coach->is_active ? 'Active' : 'Inactive'" /></td>
        </tr>
      @empty
        <tr>
          <td colspan="5"><x-ui.empty-state title="No coaches found" /></td>
        </tr>
      @endforelse
    </x-ui.table>

    {{ $coaches->onEachSide(2)->links('components.ui.pagination') }}
  </div>
</section>

@push('scripts')
<script>
(function(){
  const statusFilter = new URLSearchParams(window.location.search).get('status') || 'all';
  if (statusFilter !== 'all') {
    document.querySelectorAll('[data-coach-status]').forEach((row) => {
      row.style.display = row.dataset.coachStatus === statusFilter ? '' : 'none';
    });
  }
})();
</script>
@endpush

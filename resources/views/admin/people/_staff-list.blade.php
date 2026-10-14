@php
  $isDashboardHub = $isDashboardHub ?? false;
@endphp

@include('admin.people._shared-assets')

<section class="ui-page-stack">
  @if($isDashboardHub)
    <h2 class="people-module-title">Staff</h2>
  @else
    <x-ui.page-header title="Staff" />
  @endif

  <x-ui.filter-bar>
    <span class="ui-meta-text">{{ $staff->total() }} staff members</span>
    <x-slot:actions>
    <form method="GET" class="people-search-form">
      @if($isDashboardHub)<input type="hidden" name="tab" value="staff">@endif
      <x-ui.search name="search" :value="$search" placeholder="Search staff" data-people-search-input />
    </form>
    </x-slot:actions>
  </x-ui.filter-bar>

  <div class="ui-surface-block">
    <x-ui.table table-class="ui-table--people">
      <x-slot:head>
        <tr><th>Staff member</th><th>Username</th><th>Joined</th><th>Status</th></tr>
      </x-slot:head>
      @forelse($staff as $s)
      <tr>
        <td><div class="ui-entity"><div class="av av-sm ui-entity__avatar" style="background:{{ $s->avatar_color }}">{{ $s->initials }}</div><span class="ui-entity__title">{{ $s->name }}</span></div></td>
        <td><code>{{ $s->username }}</code></td>
        <td class="ui-meta-text">{{ $s->created_at->format('d M Y') }}</td>
        <td><x-ui.badge :status="$s->is_active ? 'active' : 'inactive'" :label="$s->is_active ? 'Active' : 'Inactive'" /></td>
      </tr>
      @empty
      <tr><td colspan="4"><x-ui.empty-state title="No staff members found" /></td></tr>
      @endforelse
    </x-ui.table>

    {{ $staff->onEachSide(2)->links('components.ui.pagination') }}
  </div>
</section>

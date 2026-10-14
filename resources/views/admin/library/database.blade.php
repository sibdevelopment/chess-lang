@extends('layouts.app')
@section('page-title','Curriculum')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@section('content')
<x-ui.page-header title="Curriculum" />


<div class="stats mb-4">
  <div class="stat"><div class="stat-label">Beginner PGNs</div><div class="stat-val" style="color:#059669">{{ $totals['beginner'] ?? 0 }}</div></div>
  <div class="stat"><div class="stat-label">Intermediate PGNs</div><div class="stat-val" style="color:#d97706">{{ $totals['intermediate'] ?? 0 }}</div></div>
  <div class="stat"><div class="stat-label">Advanced PGNs</div><div class="stat-val" style="color:#dc2626">{{ $totals['advanced'] ?? 0 }}</div></div>
  <div class="stat"><div class="stat-label">Expert PGNs</div><div class="stat-val" style="color:#7c3aed">{{ $totals['expert'] ?? 0 }}</div></div>
  <div class="stat stat-accent"><div class="stat-label">Active coaches</div><div class="stat-val">{{ $coaches->count() }}</div></div>
</div>

@forelse($coaches as $coach)
<div class="cw-card" data-cw-card>
  <button type="button" class="cw-card-head" data-cw-toggle aria-expanded="false">
    <div class="cw-av" style="background:{{ $coach->avatar_color }}">{{ $coach->initials }}</div>
    <div style="min-width:0">
      <div class="cw-name">{{ $coach->name }}</div>
      <div class="cw-meta">{{ $coach->username }}</div>
    </div>
    <div class="cw-stats">
      <span class="cw-pill" style="background:#dcfce7;color:#166534">{{ $coach->group_count }} group{{ $coach->group_count === 1 ? '' : 's' }}</span>
      <span class="cw-pill" style="background:#ecfdf5;color:#065f46">{{ $totals['beginner'] ?? 0 }} beg</span>
      <span class="cw-pill" style="background:#fef3c7;color:#92400e">{{ $totals['intermediate'] ?? 0 }} int</span>
      <span class="cw-pill" style="background:#fee2e2;color:#991b1b">{{ $totals['advanced'] ?? 0 }} adv</span>
      <span class="cw-pill" style="background:#f3e8ff;color:#6d28d9">{{ $totals['expert'] ?? 0 }} exp</span>
      <span class="cw-toggle" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="m7 5 5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
    </div>
  </button>
  <div class="cw-body">
    @foreach(\App\Models\Chapter::LEVELS as $lv)
      @if(isset($library[$lv]) && $library[$lv]->count())
      <div class="cw-lvl-head">
        <span class="badge badge-{{ $lv }}">{{ ucfirst($lv) }}</span>
        <span class="text-xs text-muted">{{ $library[$lv]->count() }} chapter(s)</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:60px">Seq</th>
              <th>Name</th>
              <th>Total Puzzles</th>
              <th>Uploaded</th>
              <th>Added by</th>
            </tr>
          </thead>
          <tbody>
            @foreach($library[$lv] as $ch)
            <tr>
              <td><strong>#{{ $ch->sequence_number }}</strong></td>
              <td>
                <div style="font-weight:600">{{ $ch->name }}</div>
                @if($ch->description)<div class="text-xs text-muted">{{ \Illuminate\Support\Str::limit($ch->description, 80) }}</div>@endif
              </td>
              <td class="text-sm">{{ $ch->puzzle_count }}</td>
              <td class="text-sm text-muted">{{ $ch->created_at->format('d M Y') }}</td>
              <td class="text-sm text-muted">{{ $ch->uploader->name ?? '—' }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif
    @endforeach
    @if($library->isEmpty())
      <div class="cw-empty-coach">No chapters in the library yet — upload PGNs from the Library tab.</div>
    @endif
  </div>
</div>
@empty
<div class="card empty">
  <div style="font-weight:600">No active coaches yet.</div>
  <div class="text-sm">Add a coach from the Coaches tab.</div>
</div>
@endforelse

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-cw-toggle]').forEach(head => {
    head.addEventListener('click', () => {
      const card = head.closest('[data-cw-card]');
      const isOpen = card.classList.toggle('open');
      head.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });
})();
</script>
@endpush
@endsection

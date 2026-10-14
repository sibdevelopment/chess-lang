@extends('layouts.app')
@section('page-title','Classes')

@php
  $routePrefix = auth()->user()?->isStaff() ? 'staff' : 'admin';
  $section = request('section', request('tab', 'today'));
  $section = in_array($section, ['live', 'today', 'past', 'upcoming', 'cancelled'], true) ? $section : 'today';
@endphp

@section('sidebar-nav') @include(auth()->user()?->isStaff() ? 'staff._sidebar' : 'admin._sidebar') @endsection

@section('content')
<div class="classroom-shell">
  <div class="classroom-top">
    <x-ui.page-header title="Classes" />
  </div>

  <nav class="ui-tabs ui-section-tabs" aria-label="Classes">
    <a href="{{ route($routePrefix.'.classes', array_filter(array_merge(request()->query(), ['section' => 'live']))) }}" class="ui-tab {{ $section === 'live' ? 'is-active' : '' }}">Live <span class="ui-tab__count">{{ $counts['live'] ?? 0 }}</span></a>
    <a href="{{ route($routePrefix.'.classes', array_filter(array_merge(request()->query(), ['section' => 'today']))) }}" class="ui-tab {{ $section === 'today' ? 'is-active' : '' }}">Today <span class="ui-tab__count">{{ $counts['today'] ?? 0 }}</span></a>
    <a href="{{ route($routePrefix.'.classes', array_filter(array_merge(request()->query(), ['section' => 'upcoming']))) }}" class="ui-tab {{ $section === 'upcoming' ? 'is-active' : '' }}">Upcoming <span class="ui-tab__count">{{ $counts['upcoming'] ?? 0 }}</span></a>
    <a href="{{ route($routePrefix.'.classes', array_filter(array_merge(request()->query(), ['section' => 'past']))) }}" class="ui-tab {{ $section === 'past' ? 'is-active' : '' }}">Past <span class="ui-tab__count">{{ $counts['past'] ?? 0 }}</span></a>
    <a href="{{ route($routePrefix.'.classes', array_filter(array_merge(request()->query(), ['section' => 'cancelled']))) }}" class="ui-tab {{ $section === 'cancelled' ? 'is-active' : '' }}">Cancelled <span class="ui-tab__count">{{ $counts['cancelled'] ?? 0 }}</span></a>
  </nav>

  <div class="card classroom-filter-card">
    <form method="GET" class="classroom-filters" autocomplete="off">
      <input type="hidden" name="section" value="{{ $section }}">
      <div class="classroom-field">
        <label for="classroom-search">Search Classes</label>
        <input
          id="classroom-search"
          type="search"
          name="search"
          value="{{ $filters['search'] }}"
          placeholder="Search by class name"
        >
      </div>
      <div class="classroom-field">
        <label for="classroom-coach">Coach</label>
        <select id="classroom-coach" name="coach_id" onchange="this.form.submit()">
          <option value="">All coaches</option>
          @foreach($coaches as $coach)
            <option value="{{ $coach->id }}" @selected((int) $filters['coach_id'] === (int) $coach->id)>{{ $coach->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="classroom-field">
        <label for="classroom-group">Batch</label>
        <select id="classroom-group" name="group_id" onchange="this.form.submit()">
          <option value="">All batches</option>
          @foreach($groups as $group)
            <option value="{{ $group->id }}" @selected((int) $filters['group_id'] === (int) $group->id)>{{ $group->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="classroom-field">
        <label for="classroom-student">Student</label>
        <select id="classroom-student" name="student_id" onchange="this.form.submit()">
          <option value="">All students</option>
          @foreach($students as $student)
            <option value="{{ $student->id }}" @selected((int) $filters['student_id'] === (int) $student->id)>{{ $student->name }}{{ $student->username ? ' · '.$student->username : '' }}</option>
          @endforeach
        </select>
      </div>
      <div class="classroom-field">
        <label for="classroom-date-from">Date From</label>
        <input id="classroom-date-from" type="date" name="date_from" value="{{ $filters['date_from'] }}" onchange="this.form.submit()">
      </div>
      <div class="classroom-field">
        <label for="classroom-date-to">Date To</label>
        <input id="classroom-date-to" type="date" name="date_to" value="{{ $filters['date_to'] }}" onchange="this.form.submit()">
      </div>
      <div class="classroom-filter-actions">
        <x-ui.button type="submit">Search</x-ui.button>
        @if(collect($filters)->except('tab')->filter(fn ($value) => filled($value))->isNotEmpty())
          <x-ui.button :href="route($routePrefix.'.classes', ['section' => $section])" variant="secondary">Reset</x-ui.button>
        @endif
      </div>
    </form>
  </div>

  @if($section === 'live')
    @include('admin.classes._results', ['classes' => $liveClasses, 'summary' => $summary, 'tab' => 'live', 'routePrefix' => $routePrefix])
  @elseif($section === 'today')
    @include('admin.classes._results', ['classes' => $todayClasses, 'summary' => $summary, 'tab' => 'today', 'routePrefix' => $routePrefix])
  @elseif($section === 'past')
    @include('admin.classes._results', ['classes' => $pastClasses, 'summary' => $summary, 'tab' => 'past', 'routePrefix' => $routePrefix])
  @elseif($section === 'upcoming')
    @include('admin.classes._results', ['classes' => $upcomingClasses, 'summary' => $summary, 'tab' => 'upcoming', 'routePrefix' => $routePrefix])
  @else
    @include('admin.classes._results', ['classes' => $cancelledClasses, 'summary' => $summary, 'tab' => 'cancelled', 'routePrefix' => $routePrefix])
  @endif
</div>

<x-ui.modal id="classroom-details-modal" max-width="720px" modal-class="classroom-modal">
  <x-slot:header>
    <div class="ui-modal-heading"><div class="ui-modal-title">Class Details</div><x-ui.button type="button" variant="text" size="sm" class="ui-button--icon" id="classroom-details-close" aria-label="Close"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></x-ui.button></div>
  </x-slot:header>
  <div id="classroom-details-body" class="classroom-loading">Loading class details…</div>
</x-ui.modal>

<x-ui.modal id="change-coach-modal" max-width="560px">
  <x-slot:header>
    <div class="ui-modal-heading"><div class="ui-modal-title">Change Coach</div><x-ui.button type="button" variant="text" size="sm" class="ui-button--icon" id="change-coach-close" aria-label="Close"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></x-ui.button></div>
  </x-slot:header>
    <form method="POST" action="#" id="change-coach-form" class="ui-modal-form">
      @csrf
      <div class="fg">
        <label>Class</label>
        <input type="text" id="change-coach-class-name" readonly style="background:var(--surface2);cursor:not-allowed">
      </div>
      <div class="fg">
        <label>Current Coach</label>
        <input type="text" id="change-coach-current" readonly style="background:var(--surface2);cursor:not-allowed">
      </div>
      <div class="fg">
        <label>Available Coach *</label>
        <select name="coach_id" id="change-coach-select" required>
          <option value="" disabled selected>— Select coach —</option>
        </select>
      </div>
      <div class="text-xs text-muted" id="change-coach-empty" style="display:none;margin-top:-4px;margin-bottom:12px">No replacement coach is available at this class time.</div>
    </form>
  <x-slot:footer>
    <x-ui.button type="button" variant="secondary" id="change-coach-cancel">Cancel</x-ui.button>
    <x-ui.button type="submit" variant="primary" id="change-coach-save" form="change-coach-form">Save</x-ui.button>
  </x-slot:footer>
</x-ui.modal>
@endsection

@push('scripts')
@include('partials._class-puzzle-audit-assets')
<script>
(() => {
  const modal = document.getElementById('classroom-details-modal');
  const modalBody = document.getElementById('classroom-details-body');
  const closeButton = document.getElementById('classroom-details-close');
  if (!modal || modal.dataset.ready === '1') return;
  modal.dataset.ready = '1';

  const openDetails = async (url) => {
    modal.style.display = 'flex';
    modalBody.className = 'classroom-loading';
    modalBody.textContent = 'Loading class details…';
    const response = await fetch(url, {
      headers: {'X-Requested-With': 'XMLHttpRequest','Accept': 'application/json'},
      credentials: 'same-origin',
    });
    if (!response.ok) {
      window.location.href = url;
      return;
    }
    const payload = await response.json();
    modalBody.className = '';
    modalBody.innerHTML = payload.html;
    window.ArcherClassPuzzleAudit?.init(modalBody);
  };

  document.addEventListener('click', event => {
    const detailLink = event.target.closest('[data-classroom-detail]');
    if (!detailLink) return;
    event.preventDefault();
    openDetails(detailLink.href);
  });

  closeButton.addEventListener('click', () => { modal.style.display = 'none'; });
  modal.addEventListener('click', event => { if (event.target === modal) modal.style.display = 'none'; });
})();

(() => {
  const modal = document.getElementById('change-coach-modal');
  const form = document.getElementById('change-coach-form');
  const className = document.getElementById('change-coach-class-name');
  const currentCoach = document.getElementById('change-coach-current');
  const select = document.getElementById('change-coach-select');
  const empty = document.getElementById('change-coach-empty');
  const save = document.getElementById('change-coach-save');
  const close = () => { if (modal) modal.style.display = 'none'; };
  if (!modal || !form || !select) return;

  const renderOptions = (coaches) => {
    select.innerHTML = '<option value="" disabled selected>— Select coach —</option>';
    coaches.forEach(coach => {
      const option = document.createElement('option');
      option.value = coach.id;
      option.textContent = coach.name;
      select.appendChild(option);
    });
    const hasOptions = coaches.length > 0;
    empty.style.display = hasOptions ? 'none' : 'block';
    select.disabled = !hasOptions;
    save.disabled = !hasOptions;
  };

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-change-coach-open]');
    if (!trigger) return;
    event.preventDefault();
    form.action = trigger.dataset.action;
    className.value = trigger.dataset.className || '';
    currentCoach.value = trigger.dataset.currentCoach || '';
    const coaches = JSON.parse(trigger.dataset.availableCoaches || '[]');
    renderOptions(coaches);
    modal.style.display = 'flex';
  });

  document.getElementById('change-coach-close')?.addEventListener('click', close);
  document.getElementById('change-coach-cancel')?.addEventListener('click', close);
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
 })();
</script>
@endpush

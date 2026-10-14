@extends('layouts.app')
@section('page-title', isset($tournament) ? 'Edit Tournament' : 'New Tournament')

@section('sidebar-nav')
@include('tournaments._sidebar')
@endsection

@php
  $role = auth()->user()->role;
  $isEdit = isset($tournament);
  $formRoute = match($role) {
    'admin' => $isEdit ? 'admin.tournaments.update' : 'admin.tournaments.store',
    'staff' => $isEdit ? 'staff.tournaments.update' : 'staff.tournaments.store',
    'coach' => $isEdit ? 'coach.tournaments.update' : 'coach.tournaments.store',
  };
  $cancelRoute = match($role) {
    'admin' => 'admin.tournaments.index',
    'staff' => 'staff.tournaments.index',
    'coach' => 'coach.tournaments.index',
  };
  // CREATE forms render fully blank. Only EDIT forms repopulate from the model.
  $selectedGroups = old('group_ids', $isEdit ? $tournament->targetGroups->pluck('id')->all() : []);
  $selectedUsers  = old('user_ids',  $isEdit ? $tournament->targetUsers->pluck('id')->all()  : []);
  $displayTimezone = auth()->user()->preferredTimezone();
@endphp

@section('content')
<x-ui.page-header :title="$isEdit ? 'Edit Tournament' : 'Create Tournament'">
  <x-slot:actions><x-ui.button :href="route($cancelRoute)" variant="secondary">Back</x-ui.button></x-slot:actions>
</x-ui.page-header>

<form method="POST" action="{{ $isEdit ? route($formRoute, $tournament) : route($formRoute) }}" class="card tournament-form" autocomplete="off">
  @csrf
  @if($isEdit)
    @method('PUT')
  @endif

  <div class="fg">
    <label>Tournament name</label>
    <input type="text" name="name" required maxlength="120" autocomplete="off"
           value="{{ $isEdit ? old('name', $tournament->name) : old('name', '') }}">
  </div>

  <div class="fg">
    <label>Archer event series</label>
    <select name="event_series" required>
      <option value="standard" @selected(old('event_series', $isEdit ? $tournament->event_series : 'standard') === 'standard')>Standard tournament</option>
      <option value="archer_sunday" @selected(old('event_series', $isEdit ? $tournament->event_series : '') === 'archer_sunday')>🏹 Archer Sunday</option>
    </select>
    <span class="text-xs text-muted">Archer Sunday sessions can run at local times in different countries; all results still feed the global Archer ecosystem.</span>
  </div>

  <div class="fg-row">
    <div class="fg">
      <label>Day &amp; time</label>
      <input type="datetime-local" name="scheduled_at" required autocomplete="off"
             value="{{ $isEdit ? old('scheduled_at', optional($tournament->scheduled_at)->timezone($displayTimezone)->format('Y-m-d\TH:i')) : old('scheduled_at', '') }}">
      <span class="text-xs text-muted">{{ $displayTimezone }}</span>
    </div>
    <div class="fg">
      <label>Number of rounds</label>
      <input type="number" name="rounds" min="1" max="15" required autocomplete="off"
             value="{{ $isEdit ? old('rounds', $tournament->rounds) : old('rounds', '') }}">
    </div>
  </div>

  @php
    // CREATE: leave format unselected so all three pills render in their resting style.
    $formatValue = $isEdit ? old('format', $tournament->format) : old('format', '');
  @endphp
  <div class="fg">
    <label>Mode</label>
    <div class="mode-grid">
      @foreach(['blitz' => 'Blitz', 'rapid' => 'Rapid', 'classical' => 'Classical'] as $k => $label)
        <label class="mode-pill {{ $formatValue === $k ? 'is-active' : '' }}" data-mode="{{ $k }}">
          <input type="radio" name="format" value="{{ $k }}" {{ $formatValue === $k ? 'checked' : '' }} onchange="onModeChange(this)">
          <span class="dot"></span>
          <span class="label">{{ $label }}</span>
        </label>
      @endforeach
    </div>
  </div>

  <div class="fg">
    <label>Time control (FIDE-standard)</label>
    <select name="time_control" id="tcSelect" required>
      {{-- options populated by JS --}}
    </select>
  </div>

  @php
    $audienceValue = $isEdit ? old('audience_type', $tournament->audience_type) : old('audience_type', '');
  @endphp
  <div class="fg">
    <label>Audience</label>
    <select name="audience_type" id="audSelect" onchange="updateAudienceUI()" required>
      <option value="" disabled {{ $audienceValue === '' ? 'selected' : '' }}>Select audience…</option>
      <option value="all_students"      {{ $audienceValue === 'all_students' ? 'selected' : '' }}>All students (everyone)</option>
      <option value="specific_groups"   {{ $audienceValue === 'specific_groups' ? 'selected' : '' }}>Specific group(s)</option>
      <option value="specific_students" {{ $audienceValue === 'specific_students' ? 'selected' : '' }}>Specific student(s)</option>
    </select>
  </div>

  <div class="fg" id="groupsBox" style="display:none">
    <label>Pick groups</label>
    <div class="pick-list">
      @forelse($groups as $g)
        <label>
          <input type="checkbox" name="group_ids[]" value="{{ $g->id }}" {{ in_array($g->id, $selectedGroups) ? 'checked' : '' }}>
          <span><strong>{{ $g->name }}</strong> <span class="text-xs text-muted">({{ $g->students_count ?? $g->students()->count() }} students)</span></span>
        </label>
      @empty
        <div class="text-muted text-sm">No groups available.</div>
      @endforelse
    </div>
  </div>

  <div class="fg" id="studentsBox" style="display:none">
    <label>Pick students</label>
    <input type="text" id="studentSearch" class="ui-input" placeholder="Search by name" oninput="filterStudents()" style="margin-bottom:8px">
    <div class="pick-list" id="studentsList">
      @forelse($students as $s)
        <label class="stu-row" data-name="{{ strtolower($s->name) }}">
          <input type="checkbox" name="user_ids[]" value="{{ $s->id }}" {{ in_array($s->id, $selectedUsers) ? 'checked' : '' }}>
          <span>{{ $s->name }} <span class="text-xs text-muted">@ {{ $s->username }}</span></span>
        </label>
      @empty
        <div class="text-muted text-sm">No students available.</div>
      @endforelse
    </div>
  </div>

  <div class="flex gap-2 mt-3">
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update tournament' : 'Schedule tournament' }}</button>
    <a href="{{ route($cancelRoute) }}" class="btn btn-ghost">Cancel</a>
  </div>
</form>

<script>
  // Time-control catalogue mirrors AdminTournamentController::TIME_CONTROLS
  const TC = @json($timeControls);

  function onModeChange(input) {
    // Toggle the .is-active class on the surrounding pill
    document.querySelectorAll('.mode-pill').forEach(p => p.classList.remove('is-active'));
    input.closest('.mode-pill').classList.add('is-active');
    updateTcOptions();
  }

  function updateTcOptions() {
    const sel = document.querySelector('input[name="format"]:checked');
    const out = document.getElementById('tcSelect');
    if (!sel) {
      // CREATE form before mode is picked — show a placeholder so the field is visibly empty.
      out.innerHTML = '<option value="" disabled selected>Select a mode first…</option>';
      return;
    }
    const list = TC[sel.value] || [];
    const previous = @json($isEdit ? old('time_control', $tournament->time_control ?? '') : old('time_control', ''));
    const opts = list.map(t =>
      `<option value="${t.value}" ${previous === t.value ? 'selected' : ''}>${t.label}</option>`
    );
    if (!previous) {
      opts.unshift('<option value="" disabled selected>Select time control…</option>');
    }
    out.innerHTML = opts.join('');
  }

  function updateAudienceUI() {
    const v = document.getElementById('audSelect').value;
    document.getElementById('groupsBox').style.display   = v === 'specific_groups'   ? '' : 'none';
    document.getElementById('studentsBox').style.display = v === 'specific_students' ? '' : 'none';
  }

  function filterStudents() {
    const q = document.getElementById('studentSearch').value.toLowerCase();
    document.querySelectorAll('.stu-row').forEach(r => {
      r.style.display = r.dataset.name.includes(q) ? '' : 'none';
    });
  }

  updateTcOptions();
  updateAudienceUI();
</script>
@endsection

@extends('layouts.app')
@section('page-title', $session ? 'Edit quiz session' : 'Schedule quiz session')

@section('sidebar-nav')
@include('quiz-sessions._sidebar')
@endsection

@php
  $u = auth()->user();
  $role = $u->role;
  $rolePrefix = $role; // admin / staff
  $storeRoute  = "$rolePrefix.quiz-sessions.store";
  $updateRoute = "$rolePrefix.quiz-sessions.update";
  $indexRoute  = "$rolePrefix.quiz-sessions.index";
  $editing = (bool) $session;
  $action  = $editing ? route($updateRoute, $session) : route($storeRoute);

  // CREATE: render the date/time field BLANK. EDIT: pre-fill from the model.
  $defaultDt = $editing
      ? \Illuminate\Support\Carbon::parse($session->scheduled_at)->timezone('Asia/Kolkata')->format('Y-m-d\TH:i')
      : '';

  $countryCodes      = \App\Models\User::studentCountryCodes();
  // CREATE forms start with no country pre-checked. EDIT forms keep current selection.
  $selectedCountries = $editing ? old('countries', $session?->countries ?? []) : old('countries', []);
  if (!is_array($selectedCountries)) $selectedCountries = [];
@endphp

@section('content')
<x-ui.page-header :title="$editing ? 'Edit Quiz Session' : 'Schedule Quiz Session'">
  <x-slot:actions><x-ui.button :href="route($indexRoute)" variant="secondary">Back</x-ui.button></x-slot:actions>
</x-ui.page-header>

@php
  $nameVal    = $editing ? old('name', $session->name) : old('name', '');
  $coachVal   = $editing ? old('coach_id', $session->coach_id ?? '') : old('coach_id', '');
  $levelVal   = $editing ? old('level', $session->level ?? '') : old('level', '');
  $scheduleVal = old('scheduled_at', $defaultDt);
@endphp

<form method="POST" action="{{ $action }}" class="card" style="max-width:680px" autocomplete="off">
  @csrf
  @if($editing) @method('PUT') @endif

  <div class="fg">
    <label>Session name <span style="color:#dc2626">*</span></label>
    <input type="text" name="name" autocomplete="off" value="{{ $nameVal }}"
           required>
  </div>

  <div class="fg-row">
    <div class="fg">
      <label>Coach <span style="color:#dc2626">*</span></label>
      <select name="coach_id" required>
        <option value="" {{ (string)$coachVal === '' ? 'selected' : '' }}>— Select coach —</option>
        @foreach($coaches as $c)
          <option value="{{ $c->id }}"
            {{ (string)$coachVal === (string)$c->id ? 'selected' : '' }}>
            {{ $c->name }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="fg">
      <label>Level <span style="color:#dc2626">*</span></label>
      <select name="level" required>
        <option value="" disabled {{ $levelVal === '' ? 'selected' : '' }}>— Select level —</option>
        @foreach(['beginner','intermediate','advanced'] as $lvl)
          <option value="{{ $lvl }}"
            {{ $levelVal === $lvl ? 'selected' : '' }}>
            {{ ucfirst($lvl) }}
          </option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="fg">
    <label>Scheduled date &amp; time (IST) <span style="color:#dc2626">*</span></label>
    <input type="datetime-local" name="scheduled_at" value="{{ $scheduleVal }}" required>
  </div>

  <div class="fg">
    <label>Eligible countries / regions</label>
    <div class="qs-country-dropdown" id="qs-country-dropdown">
      <button type="button" class="qs-country-trigger" id="qs-country-trigger">
        <span id="qs-country-trigger-text">All Regions</span>
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7 5 5 5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="qs-country-menu" id="qs-country-menu">
        <label class="qs-country-item">
          <input type="checkbox" value="__ALL__" id="qs-country-all" {{ count($selectedCountries) === 0 ? 'checked' : '' }}>
          <span class="qs-country-label">All Regions</span>
        </label>
        @foreach($countryCodes as $code => $label)
          <label class="qs-country-item">
            <input type="checkbox" name="countries[]" value="{{ $code }}" class="qs-country-cb" {{ in_array($code, $selectedCountries, true) ? 'checked' : '' }}>
            <span class="qs-country-label">{{ $label }}</span>
            <span class="qs-country-code">{{ $code }}</span>
          </label>
        @endforeach
      </div>
    </div>
  </div>

  <div class="mt-4 flex gap-2">
    <button class="btn btn-primary" type="submit">{{ $editing ? 'Update session' : 'Schedule session' }}</button>
    <a class="btn btn-outline" href="{{ route($indexRoute) }}">Cancel</a>
  </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
  const dropdown = document.getElementById('qs-country-dropdown');
  const trigger = document.getElementById('qs-country-trigger');
  const triggerText = document.getElementById('qs-country-trigger-text');
  const all = document.getElementById('qs-country-all');
  const countries = Array.from(document.querySelectorAll('.qs-country-cb'));
  if (!dropdown || !trigger || !triggerText || !all) return;

  function normalizeSelection(changedValue = null) {
    const picked = countries.filter(option => option.checked);

    if (changedValue === '__ALL__' && all.checked) {
      countries.forEach(option => option.checked = false);
      return;
    }

    if (picked.length > 0) {
      all.checked = false;
      return;
    }

    all.checked = true;
  }

  function refresh() {
    normalizeSelection();
    const picked = countries.filter(option => option.checked).map(option => option.value);

    triggerText.textContent = picked.length === 0
      ? 'All Regions'
      : picked.length === 1
        ? picked[0]
        : `${picked.length} regions selected`;
  }

  trigger.addEventListener('click', () => {
    dropdown.classList.toggle('open');
  });

  all.addEventListener('change', () => {
    normalizeSelection('__ALL__');
    refresh();
  });

  countries.forEach(cb => cb.addEventListener('change', () => {
    normalizeSelection(cb.value);
    refresh();
  }));

  document.addEventListener('click', (event) => {
    if (!dropdown.contains(event.target)) {
      dropdown.classList.remove('open');
    }
  });

  all.form?.addEventListener('submit', () => {
    if (all.checked) {
      countries.forEach(option => option.checked = false);
    }
  });

  refresh();
})();
</script>
@endpush

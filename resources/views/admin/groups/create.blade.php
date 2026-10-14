@extends('layouts.app')
@section('page-title','Create Batch')
@php
  $routePrefix = auth()->user()?->isStaff() ? 'staff' : 'admin';
  $dayOptions = \App\Models\GroupSchedule::DAYS;
  $curriculumLevels = \App\Models\Group::LEVELS;
  $curriculumChapterMap = \App\Models\Chapter::query()
    ->orderedForLevel()
    ->get(['id', 'name', 'level', 'sequence_number'])
    ->groupBy('level')
    ->map(fn ($items) => $items->map(fn ($chapter) => [
      'id' => $chapter->id,
      'name' => $chapter->name,
      'sequence_number' => $chapter->sequence_number,
    ])->values())
    ->all();
@endphp
@section('sidebar-nav') @include(auth()->user()?->isStaff() ? 'staff._sidebar' : 'admin._sidebar') @endsection

@section('content')
<x-ui.page-header title="Create Batch">
  <x-slot:actions><x-ui.button :href="route($routePrefix.'.groups')" variant="secondary">Back</x-ui.button></x-slot:actions>
</x-ui.page-header>

<div class="card ui-form-card">
  <form method="POST" action="{{ route($routePrefix.'.groups.store') }}" autocomplete="off">
    @csrf

    <div class="fg">
      <label>Batch Name *</label>
      <input name="name" required autocomplete="off" value="{{ old('name') }}">
    </div>

    <div class="fg-row">
      <div class="fg">
        <label>Coach *</label>
        <select name="coach_id" required>
          <option value="" disabled {{ old('coach_id') ? '' : 'selected' }}>— Select coach —</option>
          @foreach($coaches as $coach)
            <option value="{{ $coach->id }}" {{ (string) old('coach_id') === (string) $coach->id ? 'selected' : '' }}>{{ $coach->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="fg">
        <label>Level *</label>
        <select name="level" required>
          <option value="" disabled {{ old('level') ? '' : 'selected' }}>— Select level —</option>
          @foreach($curriculumLevels as $level)
            <option value="{{ $level }}" {{ old('level') === $level ? 'selected' : '' }}>{{ ucfirst($level) }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="fg">
      <label>Starting Chapter *</label>
      <select name="first_chapter_id" required id="group-first-chapter">
        <option value="" disabled selected>— Select chapter —</option>
      </select>
    </div>

    <div class="fg">
      <label>Status *</label>
      <select name="is_active" required>
        <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option>
        <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
      </select>
    </div>

    <div class="fg">
      <label>Class Days *</label>
      <div id="schedule-rows" class="group-schedule-list">
        @php
          $oldSchedules = old('schedules', [['day' => '', 'time' => '']]);
        @endphp
        @foreach($oldSchedules as $i => $schedule)
          <div class="group-schedule-row schedule-row">
            <div class="fg group-schedule-field" style="margin-bottom:0">
              <label>Day *</label>
              <select name="schedules[{{ $i }}][day]" required>
                <option value="" disabled {{ ($schedule['day'] ?? '') === '' ? 'selected' : '' }}>— Day —</option>
                @foreach($dayOptions as $dayValue => $dayLabel)
                  <option value="{{ $dayValue }}" {{ (string) ($schedule['day'] ?? '') !== '' && (int) $schedule['day'] === $dayValue ? 'selected' : '' }}>{{ $dayLabel }}</option>
                @endforeach
              </select>
            </div>
            <div class="fg group-schedule-field" style="margin-bottom:0">
              <label>Class Time *</label>
              <input type="time" name="schedules[{{ $i }}][time]" value="{{ $schedule['time'] ?? '' }}" required>
            </div>
          </div>
        @endforeach
      </div>
      <button type="button" class="btn btn-sm btn-ghost" onclick="addScheduleRow()">Add Day</button>
    </div>

    <div class="flex gap-2 mt-3">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn" href="{{ route($routePrefix.'.groups') }}">Cancel</a>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
let scheduleIndex = {{ count(old('schedules', [['day' => '', 'time' => '']])) }};
const scheduleDays = @json($dayOptions);
const chapterMap = @json($curriculumChapterMap);

function renderGroupChapters(level, selectedId = '') {
  const select = document.getElementById('group-first-chapter');
  if (!select) return;
  const items = chapterMap[level] || [];
  select.innerHTML = '<option value="" disabled ' + (!selectedId ? 'selected' : '') + '>— Select chapter —</option>'
    + items.map(item => `<option value="${item.id}" ${String(selectedId) === String(item.id) ? 'selected' : ''}>Chapter ${item.sequence_number} · ${item.name}</option>`).join('');
}

function addScheduleRow(day = '', time = '') {
  const wrap = document.getElementById('schedule-rows');
  if (!wrap) return;

  const row = document.createElement('div');
  row.className = 'group-schedule-row schedule-row';

  const options = ['<option value="" disabled selected>— Day —</option>']
    .concat(Object.entries(scheduleDays).map(([value, label]) =>
      `<option value="${value}" ${String(day) === String(value) ? 'selected' : ''}>${label}</option>`
    ))
    .join('');

  row.innerHTML = `
    <div class="fg group-schedule-field" style="margin-bottom:0">
      <label>Day *</label>
      <select name="schedules[${scheduleIndex}][day]" required>${options}</select>
    </div>
    <div class="fg group-schedule-field" style="margin-bottom:0">
      <label>Class Time *</label>
      <input type="time" name="schedules[${scheduleIndex}][time]" value="${time}" required>
    </div>
  `;

  wrap.appendChild(row);
  scheduleIndex += 1;
}

document.querySelector('select[name="level"]')?.addEventListener('change', function(){
  renderGroupChapters(this.value, '');
});

@if(old('level'))
renderGroupChapters(@json(old('level')), @json(old('first_chapter_id')));
@endif
</script>
@endpush

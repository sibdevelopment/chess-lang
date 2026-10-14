@php
  $isDashboardHub = $isDashboardHub ?? false;
  $tabRoute = $isDashboardHub ? $routePrefix.'.dashboard' : $routePrefix.'.groups';
  $tabBase = $isDashboardHub ? ['tab' => 'groups'] : [];
  $groupCreateScope = 'groups-create';
  $groupEditScope = 'groups-edit';
  $groupCurriculumScope = 'groups-curriculum';
  $dayOptions = \App\Models\GroupSchedule::DAYS;
  $status = ($status ?? 'active') === 'inactive' ? 'inactive' : 'active';
  $curriculumChapterMap = $curriculumChapters
    ->groupBy('level')
    ->map(fn ($items) => $items->map(fn ($chapter) => [
      'id' => $chapter->id,
      'name' => $chapter->name,
      'sequence_number' => $chapter->sequence_number,
    ])->values())
    ->all();
  $chapterLabel = static fn ($chapter) => $chapter
    ? trim(($chapter->sequence_number ? 'Chapter '.$chapter->sequence_number.' · ' : '').$chapter->name)
    : 'Not Assigned';
@endphp

@include('admin.people._shared-assets')

@if(!$isDashboardHub)
<x-ui.page-header title="Batches" />
@endif

<section class="people-module">
  <div class="people-module-head">
    @if($isDashboardHub)<h2 class="people-module-title">Batches</h2>@endif
    <button type="button" class="btn btn-primary btn-sm" data-group-create-open>Create</button>
  </div>

  <div class="people-module-tools">
    <div class="ui-tabs">
      <a href="{{ route($tabRoute, $tabBase + ['status' => 'active'] + ($search ? ['search' => $search] : [])) }}"
         class="ui-tab {{ $status === 'active' ? 'is-active' : '' }}">
        Active <span class="ui-tab__count">{{ $counts['active'] ?? 0 }}</span>
      </a>
      <a href="{{ route($tabRoute, $tabBase + ['status' => 'inactive'] + ($search ? ['search' => $search] : [])) }}"
         class="ui-tab {{ $status === 'inactive' ? 'is-active' : '' }}">
        Inactive <span class="ui-tab__count">{{ $counts['inactive'] ?? 0 }}</span>
      </a>
    </div>

    <form method="GET" class="people-search-form">
      @if($isDashboardHub)<input type="hidden" name="tab" value="groups">@endif
      <input type="hidden" name="status" value="{{ $status }}">
      <x-ui.search name="search" :value="$search" placeholder="Search batches" data-people-search-input />
    </form>
  </div>

  <x-ui.table table-class="ui-table--people">
    <x-slot:head>
      <tr><th>Batch Name</th><th>Coach</th><th>Students</th>@unless($isDashboardHub)<th>Upcoming Chapter</th>@endunless<th>Status</th><th>Actions</th></tr>
    </x-slot:head>
    @forelse($groups as $group)
      @php
        $upcomingClass = $group->liveClasses->first();
        $upcomingChapter = $upcomingClass?->chapter;
      @endphp
      <tr>
        @php $firstChapter = $group->chapters->sortBy(fn($chapter) => (int) $chapter->pivot->sequence_order)->first(); @endphp
        <td class="font-medium">{{ $group->name }}</td>
        <td>
          @if($group->coach)
            <div class="flex items-center gap-2">
              <div class="av av-sm" style="background:{{ $group->coach->avatar_color }}">{{ $group->coach->initials }}</div>
              {{ $group->coach->name }}
            </div>
          @else
            <span class="text-muted">Unassigned</span>
          @endif
        </td>
        <td><span class="font-semibold">{{ $group->active_students_count }}</span></td>
        @unless($isDashboardHub)<td>{{ $chapterLabel($upcomingChapter) }}</td>@endunless
        <td><span class="badge badge-{{ $group->is_active ? 'active' : 'inactive' }}">{{ $group->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td>
          <div class="people-actions">
            <button
              type="button"
              class="btn btn-xs btn-ghost"
              data-assignment-modal="group-students-modal"
              data-assignment-title="Assign Students"
              data-assignment-action="{{ route($routePrefix.'.groups.assign-students', $group) }}"
              data-assignment-selected="{{ $group->students->pluck('id')->implode(',') }}"
            >Assign Students</button>
            <button
              type="button"
              class="btn btn-xs btn-ghost"
              data-group-curriculum
              data-action="{{ route($routePrefix.'.groups.assign-chapters', $group) }}"
              data-level="{{ $upcomingChapter?->level ?? $group->level }}"
              data-chapter="{{ $upcomingChapter?->id ?? $firstChapter?->id }}"
            >Assign Curriculum</button>
            <button type="button" class="btn btn-xs btn-ghost"
              data-group-edit
              data-id="{{ $group->id }}"
              data-name="{{ $group->name }}"
              data-coach="{{ $group->coach_id }}"
              data-level="{{ $group->level }}"
              data-first-chapter="{{ $firstChapter?->id }}"
              data-status="{{ $group->is_active ? '1' : '0' }}"
              data-action="{{ route($routePrefix.'.groups.update', $group) }}"
              data-schedules='@json($group->scheduleSlots->map(fn($schedule) => ["day" => $schedule->day_of_week, "time" => \Illuminate\Support\Str::of($schedule->start_time)->substr(0,5)])->values())'
            >Edit</button>
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="{{ $isDashboardHub ? 5 : 6 }}" class="empty">No Records</td></tr>
    @endforelse
  </x-ui.table>

  {{ $groups->onEachSide(2)->links('components.ui.pagination') }}
</section>

@include('components.assignment-modal', [
  'modalId' => 'group-students-modal',
  'title' => 'Assign Students',
  'inputName' => 'student_ids[]',
  'availableItems' => $assignableStudents->map(fn ($student) => [
      'id' => $student->id,
      'title' => $student->name,
      'meta' => $student->username.($student->country ? ' · '.\App\Models\User::studentCountryLabel($student->country) : ''),
      'badge' => $student->is_active ? 'Active' : 'Inactive',
  ]),
])

@push('scripts')
<script>
window.ArcherAssignmentModal?.init('group-students-modal');
</script>
@endpush

@php
  $groupCreateOpen = old('_modal_scope') === $groupCreateScope;
  $groupEditOpen = old('_modal_scope') === $groupEditScope;
  $groupCurriculumOpen = old('_modal_scope') === $groupCurriculumScope;
  $groupOldSchedules = old('schedules', [['day' => '', 'time' => '']]);
@endphp

<x-ui.modal id="create-group-modal" :visible="$groupCreateOpen" max-width="720px">
  <x-slot:header>
    <div class="ui-modal-heading"><div class="ui-modal-title">Create Batch</div><x-ui.button type="button" variant="text" size="sm" class="ui-button--icon" data-group-create-close aria-label="Close"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></x-ui.button></div>
  </x-slot:header>
    <form method="POST" action="{{ route($routePrefix.'.groups.store') }}" autocomplete="off" id="create-group-form" class="ui-modal-form" data-group-create-form data-dashboard-async>
      @csrf
      <input type="hidden" name="_modal_scope" value="{{ $groupCreateScope }}">
      <div class="alert alert-danger mb-3" data-form-errors style="display:{{ $groupCreateOpen && $errors->any() ? 'block' : 'none' }}">@if($groupCreateOpen && $errors->any())<ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</div>
      <div class="fg"><label>Batch Name *</label><input name="name" required value="{{ old('name') }}"></div>
      <div class="fg-row">
        <div class="fg"><label>Coach *</label><select name="coach_id" required><option value="" disabled {{ old('coach_id') ? '' : 'selected' }}>— Select coach —</option>@foreach($coaches as $coach)<option value="{{ $coach->id }}" {{ (string) old('coach_id') === (string) $coach->id ? 'selected' : '' }}>{{ $coach->name }}</option>@endforeach</select></div>
        <div class="fg"><label>Level *</label><select name="level" required><option value="" disabled {{ old('level') ? '' : 'selected' }}>— Select level —</option>@foreach($curriculumLevels as $levelOption)<option value="{{ $levelOption }}" {{ old('level') === $levelOption ? 'selected' : '' }}>{{ ucfirst($levelOption) }}</option>@endforeach</select></div>
      </div>
      <div class="fg"><label>Status *</label><select name="is_active" required><option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option><option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option></select></div>
      <div class="fg">
        <label>Class Days *</label>
        <div class="group-schedule-list" data-group-create-schedules>
          @foreach($groupOldSchedules as $i => $schedule)
            <div class="group-schedule-row">
              <div class="fg group-schedule-field" style="margin-bottom:0"><label>Day *</label><select name="schedules[{{ $i }}][day]" required><option value="" disabled {{ ($schedule['day'] ?? '') === '' ? 'selected' : '' }}>— Day —</option>@foreach($dayOptions as $dayValue => $dayLabel)<option value="{{ $dayValue }}" {{ (string) ($schedule['day'] ?? '') !== '' && (int) $schedule['day'] === $dayValue ? 'selected' : '' }}>{{ $dayLabel }}</option>@endforeach</select></div>
              <div class="fg group-schedule-field" style="margin-bottom:0"><label>Class Time *</label><input type="time" name="schedules[{{ $i }}][time]" value="{{ $schedule['time'] ?? '' }}" required></div>
            </div>
          @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-ghost" data-group-create-add>Add Day</button>
      </div>
    </form>
  <x-slot:footer>
    <x-ui.button type="button" variant="secondary" data-group-create-close>Cancel</x-ui.button>
    <x-ui.button type="submit" variant="primary" form="create-group-form">Save</x-ui.button>
  </x-slot:footer>
 </x-ui.modal>

<x-ui.modal id="edit-group-modal" :visible="$groupEditOpen" max-width="720px">
  <x-slot:header>
    <div class="ui-modal-heading"><div class="ui-modal-title">Edit Batch</div><x-ui.button type="button" variant="text" size="sm" class="ui-button--icon" data-group-edit-close aria-label="Close"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></x-ui.button></div>
  </x-slot:header>
    <form method="POST" action="{{ old('_modal_action', '#') }}" autocomplete="off" id="edit-group-form" class="ui-modal-form" data-group-edit-form data-dashboard-async>
      @csrf @method('PUT')
      <input type="hidden" name="_modal_scope" value="{{ $groupEditScope }}">
      <input type="hidden" name="_modal_action" value="{{ old('_modal_action') }}">
      <div class="alert alert-danger mb-3" data-form-errors style="display:{{ $groupEditOpen && $errors->any() ? 'block' : 'none' }}">@if($groupEditOpen && $errors->any())<ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</div>
      <div class="fg"><label>Batch Name *</label><input name="name" required value="{{ old('name') }}" data-group-edit-name></div>
      <div class="fg-row">
        <div class="fg"><label>Coach *</label><select name="coach_id" required data-group-edit-coach><option value="" disabled>— Select coach —</option>@foreach($coaches as $coach)<option value="{{ $coach->id }}">{{ $coach->name }}</option>@endforeach</select></div>
        <div class="fg"><label>Level *</label><select name="level" required data-group-edit-level><option value="" disabled>— Select level —</option>@foreach($curriculumLevels as $levelOption)<option value="{{ $levelOption }}">{{ ucfirst($levelOption) }}</option>@endforeach</select></div>
      </div>
      <div class="fg">
        <label>Starting Chapter *</label>
        <select name="first_chapter_id" required data-group-edit-chapter>
          <option value="" disabled selected>— Select chapter —</option>
        </select>
      </div>
      <div class="fg"><label>Status *</label><select name="is_active" required data-group-edit-status><option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option><option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option></select></div>
      <div class="fg">
        <label>Class Days *</label>
        <div class="group-schedule-list" data-group-edit-schedules></div>
        <button type="button" class="btn btn-sm btn-ghost" data-group-edit-add>Add Day</button>
      </div>
    </form>
  <x-slot:footer>
    <x-ui.button type="button" variant="secondary" data-group-edit-close>Cancel</x-ui.button>
    <x-ui.button type="submit" variant="primary" form="edit-group-form">Save</x-ui.button>
  </x-slot:footer>
 </x-ui.modal>

<x-ui.modal id="curriculum-group-modal" :visible="$groupCurriculumOpen" max-width="560px">
  <x-slot:header>
    <div class="ui-modal-heading"><div class="ui-modal-title">Assign Curriculum</div><x-ui.button type="button" variant="text" size="sm" class="ui-button--icon" data-group-curriculum-close aria-label="Close"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></x-ui.button></div>
  </x-slot:header>
    <form method="POST" action="{{ old('_modal_action', '#') }}" autocomplete="off" id="curriculum-group-form" class="ui-modal-form" data-group-curriculum-form data-dashboard-async>
      @csrf
      <input type="hidden" name="_modal_scope" value="{{ $groupCurriculumScope }}">
      <input type="hidden" name="_modal_action" value="{{ old('_modal_action') }}">
      <div class="alert alert-danger mb-3" data-form-errors style="display:{{ $groupCurriculumOpen && $errors->any() ? 'block' : 'none' }}">@if($groupCurriculumOpen && $errors->any())<ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</div>
      <div class="fg">
        <label>Starting Level *</label>
        <select name="starting_level" required data-group-curriculum-level>
          <option value="" disabled {{ old('starting_level') ? '' : 'selected' }}>— Select level —</option>
          @foreach($curriculumLevels as $levelOption)
            <option value="{{ $levelOption }}" {{ old('starting_level') === $levelOption ? 'selected' : '' }}>{{ ucfirst($levelOption) }}</option>
          @endforeach
        </select>
      </div>
      <div class="fg">
        <label>Starting Chapter *</label>
        <select name="starting_chapter_id" required data-group-curriculum-chapter>
          <option value="" disabled selected>— Select chapter —</option>
        </select>
      </div>
    </form>
  <x-slot:footer>
    <x-ui.button type="button" variant="secondary" data-group-curriculum-close>Cancel</x-ui.button>
    <x-ui.button type="submit" variant="primary" form="curriculum-group-form">Save</x-ui.button>
  </x-slot:footer>
 </x-ui.modal>

@push('scripts')
<script>
(function(){
  const dayOptions = @json($dayOptions);
  const curriculumChapters = @json($curriculumChapterMap);
  const bindDelegatedOpen = (selector, handler) => {
    document.addEventListener('click', (event) => {
      const trigger = event.target.closest(selector);
      if (!trigger) return;
      handler(trigger);
    });
  };
  function scheduleMarkup(index, day, time) {
    const options = ['<option value="" disabled ' + (!String(day) ? 'selected' : '') + '>— Day —</option>']
      .concat(Object.entries(dayOptions).map(([value, label]) => `<option value="${value}" ${String(day)===String(value)?'selected':''}>${label}</option>`))
      .join('');
    return `<div class="group-schedule-row"><div class="fg group-schedule-field" style="margin-bottom:0"><label>Day *</label><select name="schedules[${index}][day]" required>${options}</select></div><div class="fg group-schedule-field" style="margin-bottom:0"><label>Class Time *</label><input type="time" name="schedules[${index}][time]" value="${time || ''}" required></div></div>`;
  }
  function chapterOptions(level, selectedId = '') {
    const items = curriculumChapters[level] || [];
    return '<option value="" disabled ' + (!selectedId ? 'selected' : '') + '>— Select chapter —</option>'
      + items.map(item => `<option value="${item.id}" ${String(selectedId)===String(item.id)?'selected':''}>Chapter ${item.sequence_number} · ${item.name}</option>`).join('');
  }
  function renderSelectOptions(select, level, selectedId = '') {
    if (!select) return;
    select.innerHTML = chapterOptions(level, selectedId);
  }
  function init(modalId, closeSelector, formSelector, openSelector, populate) {
    const modal = document.getElementById(modalId); if (!modal || modal.dataset.ready==='1') return; modal.dataset.ready='1';
    const form = modal.querySelector(formSelector); let dirty = false;
    const sync = () => { dirty = Array.from(form.elements).some(el => el.name && el.type !== 'hidden' && ((el.type==='checkbox'||el.type==='radio') ? el.checked!==el.defaultChecked : el.value!==el.defaultValue)); };
    form?.addEventListener('input', sync); form?.addEventListener('change', sync); form?.addEventListener('submit', ()=> dirty=false);
    const close = () => { modal.style.display='none'; };
    modal.querySelectorAll(closeSelector).forEach(btn => btn.addEventListener('click', close)); modal.addEventListener('click', e => { if (e.target===modal) close(); });
    bindDelegatedOpen(openSelector, (btn) => { populate?.(btn, form); dirty=false; modal.style.display='flex'; });
  }

  let createIndex = {{ count($groupOldSchedules) }};
  document.querySelector('[data-group-create-add]')?.addEventListener('click', function(){
    const wrap = document.querySelector('[data-group-create-schedules]'); if (!wrap) return;
    wrap.insertAdjacentHTML('beforeend', scheduleMarkup(createIndex, '', '')); createIndex += 1;
  });
  init('create-group-modal','[data-group-create-close]','[data-group-create-form]','[data-group-create-open]',null);

  let editIndex = 0;
  document.querySelector('[data-group-edit-add]')?.addEventListener('click', function(){
    const wrap = document.querySelector('[data-group-edit-schedules]'); if (!wrap) return;
    wrap.insertAdjacentHTML('beforeend', scheduleMarkup(editIndex, '', '')); editIndex += 1;
  });
  init('edit-group-modal','[data-group-edit-close]','[data-group-edit-form]','[data-group-edit]',function(btn,form){
    form.action = btn.dataset.action;
    form.querySelector('input[name="_modal_action"]').value = btn.dataset.action;
    form.querySelector('[data-group-edit-name]').value = form.querySelector('[data-group-edit-name]').defaultValue = btn.dataset.name || '';
    form.querySelector('[data-group-edit-coach]').value = form.querySelector('[data-group-edit-coach]').defaultValue = btn.dataset.coach || '';
    const levelSelect = form.querySelector('[data-group-edit-level]');
    const chapterSelect = form.querySelector('[data-group-edit-chapter]');
    levelSelect.value = levelSelect.defaultValue = btn.dataset.level || 'beginner';
    renderSelectOptions(chapterSelect, levelSelect.value, btn.dataset.firstChapter || '');
    chapterSelect.defaultValue = btn.dataset.firstChapter || '';
    form.querySelector('[data-group-edit-status]').value = form.querySelector('[data-group-edit-status]').defaultValue = btn.dataset.status || '1';
    const wrap = form.querySelector('[data-group-edit-schedules]');
    const schedules = JSON.parse(btn.dataset.schedules || '[]');
    wrap.innerHTML = '';
    editIndex = 0;
    (schedules.length ? schedules : [{day:'', time:''}]).forEach(schedule => { wrap.insertAdjacentHTML('beforeend', scheduleMarkup(editIndex, schedule.day ?? '', schedule.time ?? '')); editIndex += 1; });
  });
  document.querySelector('[data-group-edit-level]')?.addEventListener('change', function(){
    renderSelectOptions(document.querySelector('[data-group-edit-chapter]'), this.value, '');
  });

  function renderCurriculumOptions(level, selectedId) {
    const select = document.querySelector('[data-group-curriculum-chapter]');
    if (!select) return;
    const items = curriculumChapters[level] || [];
    select.innerHTML = '<option value="" disabled ' + (!selectedId ? 'selected' : '') + '>— Select chapter —</option>'
      + items.map(item => `<option value="${item.id}" ${String(selectedId)===String(item.id)?'selected':''}>Chapter ${item.sequence_number} · ${item.name}</option>`).join('');
  }

  init('curriculum-group-modal','[data-group-curriculum-close]','[data-group-curriculum-form]','[data-group-curriculum]',function(btn,form){
    form.action = btn.dataset.action;
    form.querySelector('input[name="_modal_action"]').value = btn.dataset.action;
    const levelSelect = form.querySelector('[data-group-curriculum-level]');
    const chapterSelect = form.querySelector('[data-group-curriculum-chapter]');
    levelSelect.value = levelSelect.defaultValue = btn.dataset.level || '{{ $curriculumLevels[0] ?? 'beginner' }}';
    renderCurriculumOptions(levelSelect.value, btn.dataset.chapter || '');
    chapterSelect.defaultValue = btn.dataset.chapter || '';
  });

  document.querySelector('[data-group-curriculum-level]')?.addEventListener('change', function(){
    renderCurriculumOptions(this.value, '');
  });

  @if($groupCurriculumOpen && old('starting_level'))
    renderCurriculumOptions(@json(old('starting_level')), @json(old('starting_chapter_id')));
  @endif
})();
</script>
@endpush

@extends('layouts.app')
@section('page-title','Start a Class')

@section('sidebar-nav') @include('coach._sidebar') @endsection

@section('content')
<x-ui.page-header title="Schedule or Start a Class" />

@if($errors->any())
  <div class="alert alert-danger mb-3">
    <ul style="margin:0;padding-left:18px">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="grid-2" style="align-items:start">
  <div class="card">
    <div class="card-title">Class details</div>
    <form method="POST" action="{{ route('coach.classes.store') }}" id="class-form" autocomplete="off">
      @csrf
      <div class="fg">
        <label>Class name *</label>
        <input name="name" required autocomplete="off" value="{{ old('name', '') }}">
      </div>
      <div class="fg">
        <label>Batch *</label>
        <select name="group_id" id="group-select" required onchange="loadChapters(this.value)">
          <option value="">— Select a batch —</option>
          @foreach($groups as $g)
          <option value="{{ $g->id }}" {{ old('group_id')==$g->id?'selected':'' }}>
            {{ $g->name }} ({{ ucfirst($g->level) }} · {{ $g->students->count() }} students)
          </option>
          @endforeach
        </select>
      </div>
      <div class="fg">
        <label>Chapter *</label>
        <select name="chapter_id" id="chapter-select" required>
          <option value="">— Select batch first —</option>
        </select>
        <input type="hidden" name="chapter_index" id="chapter-index-input">
      </div>
      <div id="chapter-warning" class="alert alert-warning" style="display:none">
        You can only teach the next unlocked chapter. Earlier chapters are for reference.
      </div>
      <button class="btn btn-primary w-full" style="margin-top:8px" id="start-btn">
        Start Live Class
      </button>
    </form>
  </div>

  {{-- Chapter sequence preview --}}
  <div>
    <div class="card" id="chapter-preview" style="display:none">
      <div class="card-title" id="preview-title">Chapter sequence</div>
      <div id="chapter-list-preview"></div>
    </div>

    {{-- Group students preview --}}
    <div class="card mt-3" id="students-preview" style="display:none">
      <div class="card-title" id="students-title">Students in class</div>
      <div id="students-list-preview"></div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@php
  $groupPayload = $groups->map(fn ($group) => [
      'id' => $group->id,
      'name' => $group->name,
      'level' => $group->level,
      'unlocked' => $group->unlocked_chapter_index,
      'students' => $group->students->map(fn ($student) => [
          'name' => $student->name,
          'initials' => $student->initials,
          'color' => $student->avatar_color,
      ])->values(),
      'chapters' => $group->chapters->map(fn ($chapter) => [
          'id' => $chapter->id,
          'name' => $chapter->name,
          'puzzle_count' => $chapter->puzzle_count,
          'seq' => $chapter->pivot->sequence_order,
      ])->values(),
  ])->values();
@endphp
<script>
const groups = @json($groupPayload);

function loadChapters(groupId) {
  const group = groups.find(g => g.id == groupId);
  if (!group) return;

  const chSel = document.getElementById('chapter-select');
  const idxInput = document.getElementById('chapter-index-input');
  const preview = document.getElementById('chapter-list-preview');
  const previewCard = document.getElementById('chapter-preview');
  const studentsCard = document.getElementById('students-preview');
  const studentsPreview = document.getElementById('students-list-preview');

  chSel.innerHTML = '';
  preview.innerHTML = '';

  document.getElementById('preview-title').textContent = group.name + ' — chapters';

  group.chapters.forEach(ch => {
    const isDone = ch.seq < group.unlocked;
    const isNext = ch.seq === group.unlocked;
    const isLocked = ch.seq > group.unlocked;

    // Dropdown option
    const opt = document.createElement('option');
    opt.value = ch.id;
    opt.dataset.idx = ch.seq;
    opt.disabled = ch.seq > group.unlocked + 1;
    opt.textContent = `${ch.seq+1}. ${ch.name}${isDone?' (Done)':isNext?' (Next)':isLocked?' (Locked)':''}`;
    if (isNext) { opt.selected = true; idxInput.value = ch.seq; }
    chSel.appendChild(opt);

    // Preview item
    const div = document.createElement('div');
    div.className = 'ch-item ' + (isDone?'done':isNext?'active':isLocked?'locked':'');
    div.innerHTML = `
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:${isNext?'700':'500'};overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${ch.name}</div>
        <div style="font-size:11px;color:var(--text3)">${ch.puzzle_count} puzzles</div>
      </div>
      ${isDone?'<span class="badge badge-done">Done</span>':isNext?'<span class="badge" style="background:#e0e7ff;color:#3730a3">Next</span>':isLocked?'<span class="badge badge-locked">Locked</span>':''}
    `;
    preview.appendChild(div);
  });

  // Students
  studentsPreview.innerHTML = group.students.map(s => `
    <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">
      <div class="av av-sm" style="background:${s.color}">${s.initials}</div>
      <span style="font-size:13px">${s.name}</span>
    </div>
  `).join('');
  document.getElementById('students-title').textContent = `Students (${group.students.length})`;

  previewCard.style.display = 'block';
  studentsCard.style.display = 'block';

  // Update hidden index on chapter change
  chSel.onchange = function() {
    const selected = chSel.selectedOptions[0];
    idxInput.value = selected?.dataset.idx || 0;
  };
}
</script>
@endpush

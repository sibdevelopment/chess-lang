@extends('layouts.app')
@section('page-title','Curriculum')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@php
  $isReadOnlyDatabase = auth()->user()?->isStaff();
  $databaseRoutePrefix = $isReadOnlyDatabase ? 'staff' : 'admin';
@endphp

@section('content')
<x-ui.page-header title="Curriculum" />

<div class="lib-toolbar">
  <div class="lib-filters">
    @foreach(array_merge(['all'], \App\Models\Chapter::LEVELS) as $lv)
    <a href="{{ route($databaseRoutePrefix.'.library', ['level'=>$lv]) }}"
       class="btn btn-sm {{ $level===$lv ? 'btn-primary' : '' }}">
      {{ ucfirst($lv) }}
    </a>
    @endforeach
  </div>
  @unless($isReadOnlyDatabase)
    <button type="button" class="btn btn-primary btn-sm" data-lib-open-modal>Add Chapter</button>
  @endunless
</div>

@unless($isReadOnlyDatabase)
{{-- Upload modal --}}
<x-ui.modal id="lib-upload-modal" :visible="$errors->any() || (bool) old('name')" max-width="560px">
  <x-slot:header>
    <div class="ui-modal-heading"><div class="ui-modal-title" id="lib-upload-title">Add Chapter</div><x-ui.button type="button" variant="text" size="sm" class="ui-button--icon" data-lib-close-modal aria-label="Close"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></x-ui.button></div>
  </x-slot:header>
    <form method="POST" action="{{ route('admin.library.upload') }}" enctype="multipart/form-data" id="lib-upload-form" class="ui-modal-form">
      @csrf
      @if($errors->any())
        <div class="alert alert-danger">
          <ul class="ui-error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
      @endif
        <div class="fg">
          <label>Chapter Name *</label>
          <input name="name" required value="{{ old('name') }}" autocomplete="off">
        </div>
        <div class="fg-row">
          <div class="fg">
            <label>Level *</label>
            <select name="level" required>
              @foreach(\App\Models\Chapter::LEVELS as $chapterLevel)
                <option value="{{ $chapterLevel }}" {{ old('level', $level !== 'all' ? $level : 'beginner') === $chapterLevel ? 'selected' : '' }}>{{ ucfirst($chapterLevel) }}</option>
              @endforeach
            </select>
          </div>
          <div class="fg">
            <label>Sequence # *</label>
            <input type="number" name="sequence_number" min="1" required value="{{ old('sequence_number', $nextSeq) }}">
          </div>
        </div>
        <div class="fg">
          <label>PGN file *</label>
          <input type="file" name="pgn_file" accept=".pgn,.txt" required>
        </div>
    </form>
  <x-slot:footer>
    <x-ui.button type="button" variant="secondary" data-lib-close-modal>Cancel</x-ui.button>
    <x-ui.button type="submit" variant="primary" form="lib-upload-form">Save</x-ui.button>
  </x-slot:footer>
</x-ui.modal>

@push('scripts')
<script>
(function(){
  const modal = document.getElementById('lib-upload-modal');
  if (!modal) return;
  const open = () => { modal.style.display = 'flex'; };
  const close = () => { modal.style.display = 'none'; };
  document.querySelectorAll('[data-lib-open-modal]').forEach(b => b.addEventListener('click', open));
  modal.querySelectorAll('[data-lib-close-modal]').forEach(b => b.addEventListener('click', close));
  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.style.display !== 'none') close(); });
})();
</script>
@endpush

{{-- Edit chapter modal --}}
<x-ui.modal id="lib-edit-modal" max-width="560px">
  <x-slot:header>
    <div class="ui-modal-heading"><div class="ui-modal-title">Edit Chapter</div><x-ui.button type="button" variant="text" size="sm" class="ui-button--icon" data-lib-edit-close aria-label="Close"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></x-ui.button></div>
  </x-slot:header>
    <form id="lib-edit-form" method="POST" enctype="multipart/form-data" class="ui-modal-form">
      @csrf @method('PUT')
        <div class="fg">
          <label>Chapter Name *</label>
          <input id="edit-name" name="name" required>
        </div>
        <div class="fg-row">
          <div class="fg">
            <label>Level *</label>
            <select id="edit-level" name="level" required>
              @foreach(\App\Models\Chapter::LEVELS as $chapterLevel)
                <option value="{{ $chapterLevel }}">{{ ucfirst($chapterLevel) }}</option>
              @endforeach
            </select>
          </div>
          <div class="fg">
            <label>Sequence # *</label>
            <input id="edit-seq" type="number" name="sequence_number" min="1" required>
          </div>
        </div>

        <div class="fg" id="edit-file-wrap">
          <label>Import PGN (adds puzzles after current last puzzle)</label>
          <input type="file" name="pgn_file" accept=".pgn,.txt">
        </div>
    </form>
  <x-slot:footer>
    <x-ui.button type="button" variant="secondary" data-lib-edit-close>Cancel</x-ui.button>
    <x-ui.button type="submit" variant="primary" form="lib-edit-form">Save</x-ui.button>
  </x-slot:footer>
</x-ui.modal>

@push('scripts')
<script>
(function(){
  const modal  = document.getElementById('lib-edit-modal');
  const form   = document.getElementById('lib-edit-form');
  const fName  = document.getElementById('edit-name');
  const fLevel = document.getElementById('edit-level');
  const fSeq   = document.getElementById('edit-seq');
  if (!modal) return;

  const open  = () => { modal.style.display = 'flex'; };
  const close = () => { modal.style.display = 'none'; };

  document.querySelectorAll('[data-edit-chapter]').forEach(btn => {
    btn.addEventListener('click', () => {
      form.action      = btn.dataset.actionUrl;
      fName.value      = btn.dataset.name  || '';
      fLevel.value     = btn.dataset.level || 'beginner';
      fSeq.value       = btn.dataset.seq   || '';
      open();
    });
  });

  modal.querySelectorAll('[data-lib-edit-close]').forEach(b => b.addEventListener('click', close));
  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.style.display !== 'none') close(); });
})();
</script>
@endpush
@endunless

{{-- Flat list grouped by level --}}
@forelse(\App\Models\Chapter::LEVELS as $lv)
  @if(isset($chapters[$lv]))
    <div class="card mb-3">
      <div class="card-title">
        <span class="badge badge-{{ $lv }}">{{ ucfirst($lv) }}</span>
        <span class="text-muted text-xs" style="margin-left:8px">{{ $chapters[$lv]->count() }} PGN(s)</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:60px">Seq</th>
              <th>Name</th>
              <th>Total Puzzles</th>
              <th>Uploaded by</th>
              <th>Date</th>
              <th style="text-align:right">Action</th>
            </tr>
          </thead>
          <tbody>
          @foreach($chapters[$lv] as $ch)
            <tr>
              <td><strong>#{{ $ch->sequence_number }}</strong></td>
              <td>
                <div style="font-weight:600">
                  <a href="{{ route($databaseRoutePrefix.'.library.chapter', $ch) }}" style="color:inherit;text-decoration:none">
                    {{ $ch->name }}
                  </a>
                </div>
                @if($ch->description)<div class="text-xs text-muted">{{ \Illuminate\Support\Str::limit($ch->description, 80) }}</div>@endif
              </td>
              <td class="text-sm">{{ $ch->puzzle_count }}</td>
              <td class="text-sm">{{ $ch->uploader->name ?? '—' }}</td>
              <td class="text-sm text-muted">{{ $ch->created_at->format('d M Y') }}</td>
              <td style="text-align:right;white-space:nowrap">
                @if($isReadOnlyDatabase)
                  <a href="{{ route('staff.library.chapter', $ch) }}" class="btn btn-xs btn-ghost">View</a>
                @else
                  <button type="button" class="btn btn-xs btn-ghost"
                          data-edit-chapter
                          data-id="{{ $ch->id }}"
                          data-name="{{ $ch->name }}"
                          data-level="{{ $ch->level }}"
                          data-seq="{{ $ch->sequence_number }}"
                          data-action-url="{{ route('admin.library.chapter.update', $ch) }}">Edit</button>
                  <a href="{{ route('admin.library.chapter.download', $ch) }}" class="btn btn-xs btn-ghost">Download</a>
                  <form method="POST" action="{{ route('admin.library.chapter.delete', $ch) }}" style="display:inline" onsubmit="return confirm('Delete this chapter?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-xs btn-ghost">Delete</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@empty
@endforelse

@if($chapters->isEmpty())
  <div class="card empty">
    <div style="font-weight:600">No Records</div>
  </div>
@endif
@endsection

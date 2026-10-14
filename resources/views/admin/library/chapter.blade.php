@extends('layouts.app')
@section('page-title', $chapter->name)

@php
  $isReadOnlyDatabase = auth()->user()?->isStaff();
  $databaseRoutePrefix = $isReadOnlyDatabase ? 'staff' : 'admin';
@endphp

@section('sidebar-nav') @include('admin._sidebar') @endsection

@section('content')
<div class="study-page">
  <div class="study-topbar">
    <div class="study-topbar-left">
      <a href="{{ route($databaseRoutePrefix.'.library') }}" class="btn btn-ghost btn-sm">Back to Curriculum</a>
      <div class="study-chapter-name">#{{ $chapter->sequence_number ?? '—' }} {{ $chapter->name }}</div>
    </div>
    @unless($isReadOnlyDatabase)
      <div class="study-topbar-right">
        <button type="button" class="btn btn-primary btn-sm" id="ape-save-chapter-topbar">Save Chapter</button>
        <button type="button" class="btn btn-primary btn-sm" id="chapter-import-trigger">Import PGN</button>
      </div>
    @endunless
  </div>

  @unless($isReadOnlyDatabase)
  <form method="POST" action="{{ route('admin.library.chapter.update', $chapter) }}" enctype="multipart/form-data" id="chapter-import-form" class="study-import-form">
    @csrf
    @method('PUT')
    <input type="hidden" name="name" value="{{ old('name', $chapter->name) }}">
    <input type="hidden" name="level" value="{{ old('level', $chapter->level) }}">
    <input type="hidden" name="sequence_number" value="{{ old('sequence_number', $chapter->sequence_number) }}">
    <input id="chapter-import-file" type="file" name="pgn_file" accept=".pgn,.txt">
    <textarea id="chapter-import-text" name="pgn_text" style="display:none">{{ old('pgn_text') }}</textarea>
  </form>
  @endunless

@include('admin.library._puzzle-editor', [
  'chapter' => $chapter,
  'puzzleChecksums' => $puzzleChecksums ?? [],
  'puzzleAnswers' => $puzzleAnswers ?? collect(),
  'editorReadOnly' => $isReadOnlyDatabase,
  'editorImportEnabled' => !$isReadOnlyDatabase,
  'editorSaveUrlTemplate' => $isReadOnlyDatabase ? '' : route('admin.library.chapter.puzzle.update', [$chapter, '__INDEX__']),
])
</div>

@unless($isReadOnlyDatabase)
@push('scripts')
<script>
(function () {
  const form = document.getElementById('chapter-import-form');
  const input = document.getElementById('chapter-import-file');
  const text = document.getElementById('chapter-import-text');
  const trigger = document.getElementById('chapter-import-trigger');
  if (!form || !input || !text) return;

  trigger?.addEventListener('click', () => input.click());
  input.addEventListener('change', async () => {
    if (!input.files || !input.files.length) return;
    const file = input.files[0];
    try {
      text.value = await file.text();
      window.dispatchEvent(new CustomEvent('archer:chapter-import-staged', {
        detail: { fileName: file.name, hasStagedImport: true }
      }));
    } catch (error) {
      text.value = '';
      input.value = '';
      window.ArcherToast?.show('PGN file could not be read.', 'error');
    }
  });
})();
</script>
@endpush
@endunless
@endsection

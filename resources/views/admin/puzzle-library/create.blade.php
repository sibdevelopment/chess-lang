@extends('layouts.app')
@section('page-title', 'Create Puzzle Set')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@section('content')
<x-ui.page-header title="Create Puzzle Set">
  <x-slot:actions><x-ui.button :href="route('admin.puzzle-library.index')" variant="secondary">Back</x-ui.button></x-slot:actions>
</x-ui.page-header>

<div class="card" style="max-width:860px">
  <form method="POST" action="{{ route('admin.puzzle-library.store') }}" id="puzzle-set-form">
    @csrf
    <input type="hidden" name="preview_token" id="preview-token" value="{{ old('preview_token') }}">
    <input type="hidden" name="source_file_name" id="source-file-name" value="{{ old('source_file_name') }}">
    <input type="hidden" name="level" value="{{ old('level', 'beginner') }}">
    <input type="hidden" name="category_name" value="{{ old('category_name', 'Mixed') }}">

    <div class="fg">
      <label>Name of Puzzle Set *</label>
      <input type="text" name="name" value="{{ old('name') }}" required autocomplete="off">
    </div>

    <div class="fg">
      <label>Status *</label>
      <select name="is_active" required>
        <option value="1" {{ old('is_active', '1') === '1' ? 'selected' : '' }}>Active</option>
        <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
      </select>
    </div>

    <div class="fg">
      <label>Upload PGN *</label>
      <div class="fg-row" style="align-items:end">
        <div class="fg" style="margin-bottom:0">
          <input type="file" id="pgn-file" accept=".pgn,.txt">
        </div>
        <button type="button" class="btn btn-primary" id="preview-btn">Preview PGN</button>
      </div>
    </div>

    <div class="card" id="preview-card" style="display:none;background:var(--surface2);padding:18px;margin-top:8px">
      <div class="grid-4">
        <div><div class="text-xs text-muted">File Name</div><div id="preview-file-name" class="font-semibold">—</div></div>
        <div><div class="text-xs text-muted">Detected Puzzle Count</div><div id="preview-detected" class="font-semibold">0</div></div>
        <div><div class="text-xs text-muted">Valid Puzzles</div><div id="preview-valid" class="font-semibold">0</div></div>
        <div><div class="text-xs text-muted">Invalid Puzzles</div><div id="preview-invalid" class="font-semibold">0</div></div>
      </div>
      <div id="preview-errors" class="mt-3" style="display:none"></div>
    </div>

    <div class="flex gap-2 mt-4">
      <button type="submit" class="btn btn-primary" id="save-btn" disabled>Create Puzzle Set</button>
      <a href="{{ route('admin.puzzle-library.index') }}" class="btn">Cancel</a>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const fileInput = document.getElementById('pgn-file');
  const previewBtn = document.getElementById('preview-btn');
  const previewCard = document.getElementById('preview-card');
  const previewToken = document.getElementById('preview-token');
  const sourceFileName = document.getElementById('source-file-name');
  const saveBtn = document.getElementById('save-btn');
  const previewErrors = document.getElementById('preview-errors');

  function resetPreview() {
    if (previewToken) previewToken.value = '';
    if (sourceFileName) sourceFileName.value = '';
    if (saveBtn) saveBtn.disabled = true;
    if (previewCard) previewCard.style.display = 'none';
    if (previewErrors) {
      previewErrors.innerHTML = '';
      previewErrors.style.display = 'none';
    }
  }

  fileInput?.addEventListener('change', resetPreview);

  previewBtn?.addEventListener('click', async function () {
    if (!fileInput?.files?.length) {
      window.ArcherToast?.show('Select a PGN file first.', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('pgn_file', fileInput.files[0]);

    previewBtn.disabled = true;
    previewBtn.textContent = 'Previewing...';

    try {
      const response = await fetch(@json(route('admin.puzzle-library.preview')), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': @json(csrf_token()),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
      });

      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload.message || 'Preview failed.');
      }

      previewCard.style.display = '';
      document.getElementById('preview-file-name').textContent = payload.file_name || '—';
      document.getElementById('preview-detected').textContent = payload.detected_puzzle_count ?? 0;
      document.getElementById('preview-valid').textContent = payload.valid_puzzle_count ?? 0;
      document.getElementById('preview-invalid').textContent = payload.invalid_puzzle_count ?? 0;
      previewToken.value = payload.token || '';
      sourceFileName.value = payload.file_name || '';

      if ((payload.invalid_puzzle_count ?? 0) > 0) {
        previewErrors.style.display = '';
        previewErrors.innerHTML = '<div class="alert alert-danger">Invalid puzzles found.<ul style="margin-top:8px;padding-left:18px">' +
          (payload.invalid_puzzles || []).map(item => '<li>Puzzle ' + item.index + ': ' + (item.errors || []).join(', ') + '</li>').join('') +
          '</ul></div>';
        saveBtn.disabled = true;
      } else {
        previewErrors.innerHTML = '';
        previewErrors.style.display = 'none';
        saveBtn.disabled = !payload.token;
        window.ArcherToast?.show('PGN preview ready.', 'success');
      }
    } catch (error) {
      resetPreview();
      window.ArcherToast?.show(error.message || 'Preview failed.', 'error');
    } finally {
      previewBtn.disabled = false;
      previewBtn.textContent = 'Preview PGN';
    }
  });
})();
</script>
@endpush

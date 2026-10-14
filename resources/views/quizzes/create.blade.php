@extends('layouts.app')
@section('page-title', $quiz ? 'Edit quiz' : 'New quiz')

@section('sidebar-nav')
@include('quizzes._sidebar')
@endsection

@php
  $u = auth()->user();
  $role = $u->role;
  $rolePrefix = $role; // admin only
  $storeRoute  = "$rolePrefix.quizzes.store";
  $updateRoute = "$rolePrefix.quizzes.update";
  $indexRoute  = "$rolePrefix.quizzes.index";
  $editing = (bool) $quiz;
  $action = $editing ? route($updateRoute, $quiz) : route($storeRoute);
  $existingOptions = $editing ? ($quiz->options ?? []) : [];
@endphp

@push('styles')
<style>
  .qf-wrap{max-width:840px;margin:0 auto}

  .qf-section{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:18px;box-shadow:var(--shadow-xs)}
  .qf-section-head{display:flex;align-items:center;gap:10px;margin-bottom:6px}
  .qf-section-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:var(--accent);color:#fff;font-weight:800;font-size:13px;flex-shrink:0}
  .qf-section-title{font-size:15px;font-weight:700;letter-spacing:-.01em}
  .qf-section-desc{font-size:12px;color:var(--text3);margin-bottom:18px;margin-left:38px}

  .qf-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media (max-width:600px){.qf-grid-2{grid-template-columns:1fr}}

  /* Toggle pill — text vs picture */
  .qf-toggle{display:inline-flex;background:var(--surface3);border-radius:10px;padding:4px;gap:2px;width:100%}
  .qf-toggle label{flex:1;text-align:center;padding:10px 14px;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600;color:var(--text2);transition:all .15s var(--ease);user-select:none}
  .qf-toggle input{display:none}
  .qf-toggle input:checked + label{background:#fff;color:var(--accent);box-shadow:var(--shadow-xs)}

  /* Upload zone (image picker) */
  .qf-upload{border:2px dashed var(--border-strong);border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:all .2s var(--ease);background:var(--surface2);position:relative;overflow:hidden}
  .qf-upload:hover{border-color:var(--accent);background:var(--accent-50)}
  .qf-upload input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
  .qf-upload-prompt{font-size:13px;font-weight:600;color:var(--text)}
  .qf-upload-hint{font-size:11px;color:var(--text3);margin-top:4px}
  .qf-preview{margin-top:10px;display:flex;flex-direction:column;align-items:center;gap:6px}
  .qf-preview img{max-height:160px;max-width:100%;object-fit:contain;border-radius:8px;background:#f3f4f6;border:1px solid var(--border)}
  .qf-preview-label{font-size:11px;color:var(--text3)}

  /* Option rows */
  .qf-opt{border:2px solid var(--border);border-radius:12px;padding:16px;margin-bottom:10px;transition:all .2s var(--ease);background:var(--surface)}
  .qf-opt.is-correct{border-color:#10b981;background:#f0fdf4;box-shadow:0 0 0 4px rgba(16,185,129,.08)}
  .qf-opt-head{display:flex;align-items:center;gap:12px;margin-bottom:10px}
  .qf-opt-letter{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:var(--accent-50);color:var(--accent-dark);font-weight:800;font-size:15px;flex-shrink:0}
  .qf-opt.is-correct .qf-opt-letter{background:#10b981;color:#fff}
  .qf-correct-radio{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--text2);cursor:pointer;padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:#fff;transition:all .15s var(--ease)}
  .qf-correct-radio:hover{border-color:var(--accent)}
  .qf-correct-radio input{accent-color:var(--accent);cursor:pointer}
  .qf-opt.is-correct .qf-correct-radio{background:#10b981;border-color:#10b981;color:#fff}
  .qf-opt-kind-toggle{margin-left:auto}
  .qf-opt-kind-toggle select{font-size:12px;padding:5px 8px;border-radius:6px;border:1px solid var(--border);background:#fff;cursor:pointer}

  .qf-mini-upload{border:1.5px dashed var(--border-strong);border-radius:8px;padding:14px 12px;text-align:center;background:var(--surface2);position:relative;cursor:pointer;font-size:12px;color:var(--text3)}
  .qf-mini-upload:hover{border-color:var(--accent);color:var(--accent-dark);background:var(--accent-50)}
  .qf-mini-upload input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}

  .qf-actions{display:flex;gap:10px;justify-content:flex-end;padding-top:8px;flex-wrap:wrap}
</style>
@endpush

@section('content')
<div class="qf-wrap">
  <x-ui.page-header :title="$editing ? 'Edit Quiz' : 'Create Quiz'">
    <x-slot:actions><x-ui.button :href="route($indexRoute)" variant="secondary">Back</x-ui.button></x-slot:actions>
  </x-ui.page-header>

  @if($errors->any())
  <div class="ui-form-errors">
    <ul>@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
  </div>
  @endif

  @php
    // CREATE forms render fully blank; EDIT keeps current behaviour.
    $weekVal     = $editing ? old('week', $quiz->week) : old('week', '');
    $categoryVal = $editing ? old('category', $quiz->category) : old('category', '');
    $ageMinVal = $editing ? old('age_min', $quiz->age_min) : old('age_min', 5);
    $ageMaxVal = $editing ? old('age_max', $quiz->age_max) : old('age_max', 8);
    $typeVal     = $editing ? old('type', $quiz->type) : old('type', '');
    $questionTextVal = $editing ? old('question_text', $quiz->question_text) : old('question_text', '');
  @endphp

  <form method="POST" action="{{ $action }}" enctype="multipart/form-data" id="quizForm" autocomplete="off">
    @csrf
    @if($editing) @method('PUT') @endif

    {{-- ── 1. Group ──────────────────────────────────────────────────── --}}
    <div class="qf-section">
      <div class="qf-section-head">
        <span class="qf-section-num">1</span>
        <span class="qf-section-title">Where does this quiz belong?</span>
      </div>
      <div class="qf-section-desc">Used to organise the bank and so coaches can pick a week to broadcast.</div>

      <div class="qf-grid-2">
        <div class="fg" style="margin-bottom:0">
          <label>Week <span style="color:#dc2626">*</span></label>
          <input type="number" name="week" min="1" max="52" autocomplete="off"
                 value="{{ $weekVal }}" required>
        </div>
        <div class="fg" style="margin-bottom:0">
          <label>Category <span style="color:#dc2626">*</span></label>
          <input type="text" name="category" list="cat-list" autocomplete="off"
                 value="{{ $categoryVal }}"
                 placeholder="Pick a category…" required>
          <datalist id="cat-list">
            @foreach($defaultCategories as $c)<option value="{{ $c }}">@endforeach
          </datalist>
        </div>
      </div>
      <div class="qf-grid-2" style="margin-top:14px">
        <div class="fg" style="margin-bottom:0">
          <label>Minimum age <span style="color:#dc2626">*</span></label>
          <select name="age_min" required>
            @foreach(range(5, 8) as $age)<option value="{{ $age }}" @selected((int)$ageMinVal === $age)>{{ $age }} years</option>@endforeach
          </select>
        </div>
        <div class="fg" style="margin-bottom:0">
          <label>Maximum age <span style="color:#dc2626">*</span></label>
          <select name="age_max" required>
            @foreach(range(5, 8) as $age)<option value="{{ $age }}" @selected((int)$ageMaxVal === $age)>{{ $age }} years</option>@endforeach
          </select>
        </div>
      </div>
    </div>

    {{-- ── 2. Question ──────────────────────────────────────────────── --}}
    <div class="qf-section">
      <div class="qf-section-head">
        <span class="qf-section-num">2</span>
        <span class="qf-section-title">The question</span>
      </div>
      <div class="qf-section-desc">Choose whether the question is plain text or a picture students need to look at.</div>

      <div class="qf-toggle mb-4">
        <input type="radio" id="qt-text" name="type" value="text"
               {{ $typeVal === 'text' ? 'checked' : '' }}>
        <label for="qt-text">Text Question</label>

        <input type="radio" id="qt-picture" name="type" value="picture"
               {{ $typeVal === 'picture' ? 'checked' : '' }}>
        <label for="qt-picture">Picture Question</label>
      </div>

      <div id="textBlock" class="fg" style="{{ $typeVal === 'picture' ? 'display:none' : '' }};margin-bottom:0">
        <label>Question text</label>
        <textarea name="question_text" rows="2">{{ $questionTextVal }}</textarea>
      </div>

      <div id="pictureBlock" style="{{ $typeVal === 'text' || $typeVal === '' ? 'display:none' : '' }}">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--text2);margin-bottom:5px">Question image</label>
        <div class="qf-upload">
          <input type="file" name="question_image" accept="image/*" id="questionImageInput">
          <div class="qf-upload-prompt">Click or drop an image here</div>
          <div class="qf-upload-hint">PNG, JPG, GIF, WebP &middot; up to 4MB</div>
        </div>
        <div class="qf-preview" id="questionPreview" style="{{ $editing && $quiz->image_path ? '' : 'display:none' }}">
          @if($editing && $quiz->image_path)
            <img src="{{ $quiz->questionImageUrl() }}" id="questionPreviewImg">
            <div class="qf-preview-label">Current image — pick a new file to replace</div>
          @else
            <img id="questionPreviewImg">
            <div class="qf-preview-label">Preview</div>
          @endif
        </div>
      </div>
    </div>

    {{-- ── 3. Options ────────────────────────────────────────────────── --}}
    <div class="qf-section">
      <div class="qf-section-head">
        <span class="qf-section-num">3</span>
        <span class="qf-section-title">Four options &middot; pick the right one</span>
      </div>
      <div class="qf-section-desc">Each option can be plain text or an image. Mark exactly one as correct.</div>

      @for($i = 0; $i < 4; $i++)
        @php
          // CREATE: show 4 empty text-mode options with no "correct" pre-selected.
          $existing = $editing ? ($existingOptions[$i] ?? null) : null;
          $kind = old("option_kind.$i", $existing['kind'] ?? 'text');
          $text = old("option_text.$i", $existing && $existing['kind'] === 'text' ? $existing['value'] : '');
          $existingImage = $existing && $existing['kind'] === 'image' ? asset('storage/' . $existing['value']) : null;
          $correctOld = old('correct_index', $editing ? ($quiz->correct_index ?? null) : null);
          $isCorrect = $correctOld !== null && (string)$correctOld === (string)$i;
        @endphp
        <div class="qf-opt {{ $isCorrect ? 'is-correct' : '' }}" data-row="{{ $i }}">
          <div class="qf-opt-head">
            <span class="qf-opt-letter">{{ ['A','B','C','D'][$i] }}</span>

            <label class="qf-correct-radio">
              <input type="radio" name="correct_index" value="{{ $i }}" {{ $isCorrect ? 'checked' : '' }} required>
              {{ $isCorrect ? 'Correct answer' : 'Mark correct' }}
            </label>

            <div class="qf-opt-kind-toggle">
              <select name="option_kind[{{ $i }}]" class="opt-kind" data-row="{{ $i }}">
                <option value="text"  {{ $kind === 'text'  ? 'selected' : '' }}>Text</option>
                <option value="image" {{ $kind === 'image' ? 'selected' : '' }}>Image</option>
              </select>
            </div>
          </div>

          <div class="opt-text-{{ $i }}" style="{{ $kind === 'image' ? 'display:none' : '' }}">
            <input type="text" name="option_text[{{ $i }}]" placeholder="Option {{ ['A','B','C','D'][$i] }}…" value="{{ $text }}">
          </div>

          <div class="opt-image-{{ $i }}" style="{{ $kind === 'text' ? 'display:none' : '' }}">
            @if($existingImage)
              <div class="qf-preview" style="margin:0 0 10px">
                <img src="{{ $existingImage }}" style="max-height:80px">
                <div class="qf-preview-label">Current — pick a new file to replace</div>
              </div>
            @endif
            <div class="qf-mini-upload">
              <input type="file" name="option_image[{{ $i }}]" accept="image/*" class="opt-img-input" data-row="{{ $i }}">
              Click or drop an image here
            </div>
            <div class="qf-preview opt-img-preview-{{ $i }}" style="display:none;margin-top:8px">
              <img class="opt-img-preview-img-{{ $i }}" style="max-height:80px">
              <div class="qf-preview-label">New image preview</div>
            </div>
          </div>
        </div>
      @endfor
    </div>

    {{-- ── 4. Timing ─────────────────────────────────────────────────── --}}
    <div class="qf-section">
      <div class="qf-section-head">
        <span class="qf-section-num">4</span>
        <span class="qf-section-title">Answer time</span>
      </div>
      <div class="qf-section-desc">Every question gives children the same comfortable thinking time.</div>
      <div class="badge badge-active" style="font-size:13px;padding:9px 13px">2 minutes · 120 seconds</div>
    </div>

    <div class="qf-actions">
      <a class="btn btn-outline" href="{{ route($indexRoute) }}">Cancel</a>
      <button class="btn btn-primary btn-lg" type="submit">
        {{ $editing ? 'Update Quiz' : 'Save to Bank' }}
      </button>
    </div>
  </form>
</div>

@push('scripts')
<script>
(function () {
  // ── Toggle text vs picture for the question ────────────────────────
  document.querySelectorAll('input[name="type"]').forEach(function (r) {
    r.addEventListener('change', function () {
      var v = document.querySelector('input[name="type"]:checked').value;
      document.getElementById('textBlock').style.display    = v === 'text'    ? '' : 'none';
      document.getElementById('pictureBlock').style.display = v === 'picture' ? '' : 'none';
    });
  });

  // ── Question image preview ─────────────────────────────────────────
  var qInput = document.getElementById('questionImageInput');
  if (qInput) {
    qInput.addEventListener('change', function (e) {
      var file = e.target.files[0];
      if (!file) return;
      var url = URL.createObjectURL(file);
      document.getElementById('questionPreview').style.display = '';
      document.getElementById('questionPreviewImg').src = url;
    });
  }

  // ── Toggle text vs image per option ────────────────────────────────
  document.querySelectorAll('.opt-kind').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var i = sel.getAttribute('data-row');
      var v = sel.value;
      document.querySelector('.opt-text-' + i).style.display  = v === 'text'  ? '' : 'none';
      document.querySelector('.opt-image-' + i).style.display = v === 'image' ? '' : 'none';
    });
  });

  // ── Per-option image preview ───────────────────────────────────────
  document.querySelectorAll('.opt-img-input').forEach(function (inp) {
    inp.addEventListener('change', function (e) {
      var i = inp.getAttribute('data-row');
      var file = e.target.files[0];
      if (!file) return;
      var url = URL.createObjectURL(file);
      document.querySelector('.opt-img-preview-' + i).style.display = '';
      document.querySelector('.opt-img-preview-img-' + i).src = url;
    });
  });

  // ── Highlight the "correct" option visually ────────────────────────
  function syncCorrect() {
    var picked = document.querySelector('input[name="correct_index"]:checked');
    var pickedRow = picked ? picked.closest('.qf-opt').getAttribute('data-row') : null;
    document.querySelectorAll('.qf-opt').forEach(function (row) {
      var i = row.getAttribute('data-row');
      var isMe = (i === pickedRow);
      row.classList.toggle('is-correct', isMe);
      var lbl = row.querySelector('.qf-correct-radio');
      if (lbl) {
        lbl.lastChild.textContent = ' ' + (isMe ? 'Correct answer' : 'Mark correct');
      }
    });
  }
  document.querySelectorAll('input[name="correct_index"]').forEach(function (r) {
    r.addEventListener('change', syncCorrect);
  });
})();
</script>
@endpush
@endsection

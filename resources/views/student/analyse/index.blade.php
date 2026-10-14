@extends('layouts.app')
@section('page-title','Analyse')

@section('sidebar-nav') @include('student._sidebar') @endsection

{{-- Shared chess-UI design tokens (hero, classification colours, etc.). --}}
@include('partials._chess-ui')

@push('styles')
<style>
/* ─── Hero ──────────────────────────────────────────────────────────── */
.an-hero        { background:var(--color-primary); color:#fff; border-radius:12px; padding:22px 26px; margin-bottom:18px; display:flex; align-items:center; gap:18px; flex-wrap:wrap }
.an-hero h1     { color:#fff; font-size:24px; font-weight:600; margin:0; letter-spacing:-.02em }
.an-hero .lead  { color:rgba(255,255,255,.88); font-size:13px; margin-top:4px; line-height:1.5; max-width:520px }
.an-hero .badges{ display:flex; gap:8px; flex-wrap:wrap; margin-top:8px }
.an-hero .pill  { background:#1d4ed8; border:1px solid rgba(255,255,255,.35); padding:4px 10px; border-radius:99px; font-size:11px; font-weight:600 }

.an-grid       { display:grid; grid-template-columns: minmax(0, 1fr) 400px; gap:18px; align-items:flex-start }
@media (max-width: 1080px) { .an-grid { grid-template-columns: 1fr; } }

/* ─── Study cards (left) ──────────────────────────────────────────── */
.an-section-head { display:flex; align-items:baseline; gap:10px; margin-bottom:12px }
.an-section-head h2 { font-size:18px; font-weight:600; color:#0f172a; margin:0; letter-spacing:-.01em }
.an-section-head .count { font-size:11px; color:#94a3b8; font-weight:600 }
.an-section-head .right { margin-left:auto; font-size:11px; color:#64748b }

.an-study-list { display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:12px }
.an-study      { background:#fff; border:1px solid var(--border); border-radius:12px; padding:14px 14px 12px; display:flex; flex-direction:column; gap:8px; transition:all .15s; position:relative; overflow:hidden; min-height:130px }
.an-study:hover{ border-color:#a5b4fc; box-shadow:0 4px 14px rgba(79,70,229,.08); transform:translateY(-1px) }
.an-study::before{ content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--color-primary) }
.an-study-name { font-size:14px; font-weight:700; color:#0f172a; line-height:1.35; padding-left:8px; word-break:break-word; text-decoration:none; display:block }
.an-study-name:hover { color:#4338ca }
.an-study-meta { display:flex; gap:5px; font-size:11px; color:#64748b; padding-left:8px; flex-wrap:wrap; align-items:center }
.an-study-meta .pill { background:#f1f5f9; padding:2px 8px; border-radius:99px; font-size:10px; font-weight:600; color:#475569 }
.an-study-meta .pill.reviewed { background:#dcfce7; color:#166534 }
.an-study-meta .pill.commented { background:#dbeafe; color:#1e40af }
.an-study-meta .pill.tree { background:#ede9fe; color:#5b21b6 }
.an-study-when { font-size:10px; color:#94a3b8; padding-left:8px; font-family:ui-monospace, monospace }
.an-study-actions { display:flex; gap:5px; padding-left:8px; margin-top:auto; padding-top:6px; border-top:1px solid #f1f5f9 }
.an-study-actions .btn { font-size:11px; padding:5px 10px }

/* ─── Upload card (right) ─────────────────────────────────────────── */
.an-up-card    { background:#fff; border:1px solid var(--border); border-radius:14px; padding:0; overflow:hidden; position:sticky; top:14px }
.an-up-head    { padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0 }
.an-up-title   { font-size:15px; font-weight:800; color:#0f172a; letter-spacing:-.01em; display:flex; align-items:center; gap:8px }
.an-up-sub     { font-size:11px; color:#64748b; margin-top:3px }
.an-up-body    { padding:16px 18px }
.an-up-tabs    { margin-bottom:14px }
.an-up-tab     { flex:1 }
.an-up-pane    { display:none }
.an-up-pane.active { display:block }
.an-up-drop    { border:1px dashed #c7d2fe; border-radius:10px; padding:28px 20px; text-align:center; cursor:pointer; transition:all .15s; background:#f8fafc }
.an-up-drop:hover, .an-up-drop.drag { border-color:#4f46e5; background:#eef2ff }
.an-up-drop input{ display:none }
.an-up-drop-title { font-size:13px; font-weight:700; color:#334155 }
.an-up-help    { font-size:11px; color:#64748b; margin-top:6px; line-height:1.5 }
.an-up-fname   { font-size:12px; color:#4338ca; font-weight:600; margin-top:8px; display:none; padding:6px 10px; background:#eef2ff; border-radius:6px }
.an-up-textarea{ width:100%; min-height:160px; border:1px solid var(--border); border-radius:8px; padding:10px 12px; font-family:ui-monospace,monospace; font-size:12px; resize:vertical; line-height:1.5; color:#0f172a; background:#fafafa; box-sizing:border-box }
.an-up-textarea:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12); background:#fff }
.an-up-name    { width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; box-sizing:border-box; margin-top:12px; color:#0f172a }
.an-up-name:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12) }
.an-up-actions { display:flex; gap:8px; margin-top:14px }
.an-up-actions .btn { font-size:12px; padding:9px 14px }
.an-up-foot    { padding:10px 18px; background:#f8fafc; border-top:1px solid #f1f5f9; font-size:11px; color:#64748b; line-height:1.55 }
.an-up-foot code { background:#fff; padding:1px 5px; border-radius:3px; border:1px solid #e2e8f0; font-size:11px; color:#4338ca }

/* ─── Empty state ──────────────────────────────────────────────────── */
.an-empty      { text-align:center; padding:60px 24px; color:#94a3b8; border:2px dashed var(--border); border-radius:14px; background:#fafbff }
.an-empty h3   { font-size:16px; font-weight:700; color:#475569; margin-bottom:8px }
.an-empty p    { font-size:13px; max-width:340px; margin:0 auto; line-height:1.55 }
</style>
@endpush

@section('content')

{{-- ─── HERO ─── --}}
<div class="an-hero">
  <div style="flex:1;min-width:260px">
    <h1>Analyse</h1>
    <div class="lead">Upload a PGN, open it like a Lichess Study, then move on the board, branch into variations, annotate with NAGs and comments, and let the engine + AI tutor explain every move.</div>
    <div class="badges">
      <span class="pill">Stockfish multipv 5</span>
      <span class="pill">AI coach</span>
      <span class="pill">Variations and sub-variations</span>
      <span class="pill">Auto-saved studies</span>
    </div>
  </div>
</div>

<div class="an-grid">

  {{-- ─── STUDIES LIST ─── --}}
  <div>
    <div class="an-section-head">
      <h2>My studies</h2>
      <span class="count">{{ $analyses->count() }} total</span>
      @if($analyses->isNotEmpty())
        <span class="right">Sorted by recently updated</span>
      @endif
    </div>

    @if($analyses->isEmpty())
      <div class="an-empty">
        <h3>No studies yet</h3>
        <p>Upload a PGN on the right to start your first analysis. Studies stay saved here — come back anytime to keep reviewing.</p>
      </div>
    @else
      <div class="an-study-list">
        @foreach($analyses as $a)
          @php
            $reviewCount  = is_array($a->reviews)    ? count($a->reviews)    : 0;
            $commentCount = is_array($a->commentary) ? count($a->commentary) : 0;
            $hasTree      = is_array($a->tree)       && !empty($a->tree['games'] ?? null);
          @endphp
          <div class="an-study">
            <a href="{{ route('student.analyse.show', $a) }}" class="an-study-name">{{ $a->name }}</a>
            <div class="an-study-meta">
              <span class="pill">{{ $a->game_count }} {{ Str::plural('game', $a->game_count) }}</span>
              @if($hasTree)
                <span class="pill tree">Tree saved</span>
              @endif
              @if($reviewCount)
                <span class="pill reviewed">{{ $reviewCount }} reviewed</span>
              @endif
              @if($commentCount)
                <span class="pill commented">{{ $commentCount }} AI notes</span>
              @endif
            </div>
            <div class="an-study-when">
              Opened {{ optional($a->opened_at ?? $a->updated_at)->diffForHumans() }}
            </div>
            <div class="an-study-actions">
              <a href="{{ route('student.analyse.show', $a) }}" class="btn btn-primary btn-xs" style="flex:1;text-align:center">Open</a>
              <form method="POST" action="{{ route('student.analyse.destroy', $a) }}"
                    onsubmit="return confirm('Delete this study? Variations, comments and AI notes will be lost.');"
                    style="margin:0">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-xs" style="color:#dc2626">Delete</button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- ─── UPLOAD CARD (sticky on right) ─── --}}
  <div class="an-up-card">
    <div class="an-up-head">
      <div class="an-up-title">New study</div>
      <div class="an-up-sub">Upload a .pgn file or paste PGN text — multi-game files supported.</div>
    </div>

    <form method="POST" action="{{ route('student.analyse.store') }}" enctype="multipart/form-data" id="an-up-form">
      @csrf
      <div class="an-up-body">
        <div class="ui-tabs an-up-tabs" role="tablist">
          <button type="button" class="ui-tab an-up-tab is-active active" data-up-tab="file">Upload file</button>
          <button type="button" class="ui-tab an-up-tab" data-up-tab="paste">Paste PGN</button>
        </div>

        <div class="an-up-pane active" data-up-pane="file">
          <label class="an-up-drop" id="an-up-drop">
            <div class="an-up-drop-title">Drop a .pgn file here</div>
            <div class="an-up-help">or click to choose · max 1 MB</div>
            <div class="an-up-fname" id="an-up-fname"></div>
            <input type="file" name="file" id="an-up-file" accept=".pgn,text/plain">
          </label>
        </div>

        <div class="an-up-pane" data-up-pane="paste">
          <textarea name="pgn" id="an-up-pgn" class="an-up-textarea"
                    placeholder='[Event "..."]&#10;[White "..."]&#10;[Black "..."]&#10;&#10;1. e4 e5 2. Nf3 Nc6 3. Bc4 ...'></textarea>
          <div style="text-align:right;margin-top:6px">
            <button type="button" class="btn btn-ghost btn-xs" id="an-sample">Try Sample PGN</button>
          </div>
        </div>

        <input name="name" type="text" class="an-up-name" placeholder="Study name (auto-detected from PGN if blank)" maxlength="200">

        <div class="an-up-actions">
          <button type="submit" class="btn btn-primary" style="flex:1">Save Study</button>
        </div>
      </div>
    </form>

    <div class="an-up-foot">
      Multi-game PGN files (e.g. Lichess study exports) work too — each <code>[Event]</code> block becomes a separate position inside the study, navigable from the left panel.
    </div>
  </div>
</div>

<script>
(function(){
  // Tabs
  document.querySelectorAll('[data-up-tab]').forEach(t => {
    t.addEventListener('click', () => {
      document.querySelectorAll('.an-up-tab').forEach(x => x.classList.remove('active', 'is-active'));
      document.querySelectorAll('.an-up-pane').forEach(x => x.classList.remove('active'));
      t.classList.add('active', 'is-active');
      document.querySelector('[data-up-pane="' + t.dataset.upTab + '"]').classList.add('active');
    });
  });

  // Drag-and-drop
  const drop  = document.getElementById('an-up-drop');
  const file  = document.getElementById('an-up-file');
  const fname = document.getElementById('an-up-fname');
  const text  = document.getElementById('an-up-pgn');

  ['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('drag'); }));
  ['dragleave','drop'].forEach(ev   => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('drag'); }));
  drop.addEventListener('drop', e => {
    const f = e.dataTransfer?.files?.[0];
    if (f) {
      const dt = new DataTransfer(); dt.items.add(f); file.files = dt.files;
      showFile(f.name);
    }
  });
  file.addEventListener('change', () => { if (file.files[0]) showFile(file.files[0].name); });
  function showFile(name) { fname.textContent = name; fname.style.display = 'inline-block'; }

  document.getElementById('an-sample').addEventListener('click', () => {
    text.value = `[Event "Italian — model game"]\n[White "Student"]\n[Black "Coach"]\n[Result "*"]\n\n1. e4 e5 2. Nf3 Nc6 3. Bc4 Bc5 4. c3 Nf6 5. d4 exd4 6. cxd4 Bb4+ 7. Bd2 Bxd2+ 8. Nbxd2 d5 9. exd5 Nxd5 10. Qb3 Nce7 11. O-O O-O *`;
  });

  // Submit guard: at least one of file or pgn text is required.
  document.getElementById('an-up-form').addEventListener('submit', (e) => {
    if (!file.files[0] && !text.value.trim()) {
      e.preventDefault();
      alert('Please choose a .pgn file OR paste PGN text first.');
    }
  });
})();
</script>
@endsection

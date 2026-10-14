@include('partials._chess-ui')

@php
  // Keep this as the single shared puzzle editor. Callers only provide their
  // own PGN/answer data and save URL; all UI and behaviour stays here.
  $editorPgn = $editorPgn ?? (string) $chapter->pgn;
  $editorPuzzleCount = $editorPuzzleCount ?? (int) ($chapter->puzzle_count ?? 0);
  $editorPuzzleChecksums = $editorPuzzleChecksums ?? ($puzzleChecksums ?? []);
  $editorPuzzleAnswers = $editorPuzzleAnswers ?? ($puzzleAnswers ?? []);
  $editorSaveUrlTemplate = $editorSaveUrlTemplate
    ?? route('admin.library.chapter.puzzle.update', [$chapter, '__INDEX__']);
  $editorImportEnabled = $editorImportEnabled ?? true;
  $editorReadOnly = $editorReadOnly ?? false;
@endphp

@once
@push('styles')
<style>
.ape-shell{background:#fff;border:1px solid #dbe4ee;border-radius:10px;box-shadow:0 12px 32px rgba(15,23,42,.10);padding:0;overflow:hidden}
.ape-grid{display:grid;grid-template-columns:260px minmax(0,1fr) 400px;gap:0;align-items:stretch;max-width:100%;min-width:0;height:calc(100vh - 164px);min-height:720px;overflow:hidden;background:#fff}
.ape-left-col,.ape-center-col,.ape-right-col{min-width:0;min-height:0;overflow:hidden}
.ape-left-col{border-right:1px solid #e2e8f0;display:flex;flex-direction:column;background:#fff;color:#0f172a}
.ape-center-col{display:flex;flex-direction:column;min-width:0;background:#f8fafc}
.ape-right-col{border-left:1px solid #e2e8f0;display:flex;flex-direction:column;background:#fff;color:#0f172a}
.ape-panel-scroll{min-width:0;min-height:0;overflow:auto}
.ape-left-head,.ape-right-head{padding:14px 16px;border-bottom:1px solid #e2e8f0}
.ape-left-title,.ape-right-title{font-size:12px;font-weight:600;color:#334155}
.ape-left-tabs{display:flex;gap:8px;margin-top:10px}
.ape-side-tab{padding:7px 10px;border:none;border-radius:999px;background:#16a34a;color:#fff;font-size:12px;font-weight:700;cursor:pointer}
.ape-side-tab.active{background:#15803d;color:#fff}
.ape-search{padding:12px 16px}
.ape-search input{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;font-size:13px}
.ape-search input::placeholder{color:#64748b}
.ape-list{flex:1 1 auto;min-height:0;overflow:auto;padding:4px 8px 12px}
.ape-chapter-list{padding:8px;flex:0 0 56%;border-bottom:1px solid #e2e8f0}
.ape-puzzle-list{padding-top:8px;flex:1 1 auto}
.ape-left-lower{display:flex;flex-direction:column;min-height:0}
.ape-lower-head{padding:12px 16px 0;color:#334155;font-size:12px;font-weight:600}
.ape-chapter-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:8px;color:#0f172a;text-decoration:none;border:1px solid transparent}
.ape-chapter-row:hover{background:#f0fdf4}
.ape-chapter-row.active{background:#16a34a;color:#fff}
.ape-chapter-row-main,.ape-chapter-row-meta{display:flex;align-items:center;gap:8px;min-width:0}
.ape-chapter-seq{font-size:12px;font-weight:800;opacity:.82}
.ape-chapter-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ape-chapter-row-meta{font-size:11px;opacity:.82;flex-shrink:0}
.ape-chapter-dot{width:8px;height:8px;border-radius:50%;background:currentColor;opacity:.9}
.ape-row{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;cursor:pointer;background:transparent;min-width:0;max-width:100%;overflow:hidden;transition:background .12s ease,color .12s ease;color:#0f172a}
.ape-row:hover{background:#f0fdf4}
.ape-row.active{background:#16a34a;color:#fff}
.ape-row-index{font-size:11px;font-weight:800;color:#64748b;flex:0 0 28px}
.ape-row.active .ape-row-index{color:#dcfce7}
.ape-row-name,.ape-ellipsis{display:block;min-width:0;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ape-row-name{font-size:13px;font-weight:700;color:inherit}
.ape-row-dirty{width:8px;height:8px;border-radius:50%;background:#f59e0b;flex-shrink:0}
.ape-text-muted{color:#64748b}
.ape-left-foot{padding:16px;border-top:1px solid #e2e8f0}
.ape-flat-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px 12px;border:1px solid #16a34a;border-radius:8px;background:#16a34a;font-size:13px;font-weight:700;color:#fff}
.ape-flat-btn:disabled{opacity:.55;cursor:not-allowed}
.ape-workspace{display:flex;flex-direction:column;gap:14px;min-height:0;padding:18px;overflow:hidden}
.ape-board-wrap{display:flex;flex-direction:column;align-items:center;gap:12px;min-width:0;min-height:0;flex:0 0 auto;width:100%}
.ape-board-stage{position:relative;width:min(72vh,680px);height:min(72vh,680px);max-width:100%;max-height:calc(100vh - 320px);aspect-ratio:1/1;flex:0 0 auto;display:grid;place-items:center;overflow:hidden}
#ape-board{width:100%;height:100%;max-width:100%;max-height:100%;aspect-ratio:1/1;flex:0 0 auto}
#ape-board.lc-board{max-width:100%}
.annotation-overlay{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}
.ape-board-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;width:min(100%,min(72vh,680px));max-width:100%}
.ape-shell .btn,.ape-modal-panel .btn,.ape-drawer-panel .btn,#ape-move-menu .btn{border-color:#16a34a;background:#16a34a;color:#fff}
.ape-shell .btn:hover,.ape-modal-panel .btn:hover,.ape-drawer-panel .btn:hover,#ape-move-menu .btn:hover{background:#15803d;border-color:#15803d;color:#fff}
.board-controls{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;width:min(100%,min(72vh,680px));max-width:100%}
.board-controls .btn{flex:0 0 auto}
.ape-board-meta{display:flex;align-items:center;gap:10px;width:min(100%,min(72vh,680px));max-width:100%;font-size:12px;color:#0f172a}
.ape-board-meta-right{margin-left:auto}
.ape-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;padding:8px 0;width:min(100%,min(72vh,680px));max-width:100%;margin:0 auto}
.ape-toolbar .btn{min-height:34px}
.ape-tree-wrap{display:flex;flex-direction:column;min-height:280px;flex:1 1 auto;overflow:hidden}
.ape-tree{background:#fff;border:none;border-radius:0;padding:14px 16px;min-width:0;max-width:100%;min-height:280px;height:100%;overflow:auto;line-height:1.8;font-size:13px;color:#0f172a;overflow-wrap:anywhere;word-break:break-word}
.ape-tree-empty{text-align:center;color:#64748b;font-size:12px;font-style:italic;padding:40px 0}
.ape-tree .mn{font-family:ui-monospace,monospace;font-size:11px;color:#64748b;margin:0 4px 0 2px;font-weight:600}
.ape-tree .mv{display:inline-block;padding:1px 6px;border-radius:6px;cursor:pointer;font-family:ui-monospace,monospace;font-size:13px;color:#0f172a;transition:background .1s,color .1s}
.ape-tree .mv:hover{background:#f0fdf4}
.ape-tree .mv.active{background:#16a34a;color:#fff;font-weight:700}
.ape-tree .mv .nag{font-weight:700;margin-left:2px}
.ape-tree .mv.best{color:#15803d}.ape-tree .mv.best.active{background:#16a34a;color:#fff}
.ape-tree .mv.good{color:#0f766e}.ape-tree .mv.good.active{background:#14b8a6;color:#fff}
.ape-tree .mv.inaccuracy{color:#854d0e}.ape-tree .mv.inaccuracy.active{background:#ca8a04;color:#fff}
.ape-tree .mv.mistake{color:#9a3412}.ape-tree .mv.mistake.active{background:#ea580c;color:#fff}
.ape-tree .mv.blunder{color:#991b1b}.ape-tree .mv.blunder.active{background:#dc2626;color:#fff}
.ape-tree .var-line{color:#475569;font-style:italic;padding-left:8px;border-left:2px solid #cbd5e1;margin:3px 0 3px 2px;font-size:12px;display:block;line-height:1.7;max-width:100%}
.ape-tree .var-line .mv{font-style:normal}
.ape-tree .cmt-inline{color:#334155;font-size:12px;font-style:italic;background:#f8fafc;border:1px solid #e2e8f0;padding:1px 6px;border-radius:5px;margin:0 3px;overflow-wrap:anywhere;word-break:break-word}
.ape-tree .mark{display:inline-block;font-size:10px;font-weight:700;padding:1px 4px;border-radius:999px;margin-left:4px;background:#dcfce7;color:#166534}
.ape-tree .mark.draw{background:#dcfce7;color:#166534}
.ape-tree .mark.cmt{background:#dcfce7;color:#166534}
.ape-help{font-size:11px;color:#475569;overflow-wrap:anywhere;word-break:break-word}
.ape-right-scroll{display:flex;flex-direction:column;gap:0;min-height:0;overflow:auto;padding:0}
.ape-tabs{display:flex;gap:12px;align-items:center}
.ape-tab-btn{padding:0 0 8px;border:none;border-bottom:2px solid transparent;background:transparent;font-size:13px;font-weight:700;color:#475569;cursor:pointer}
.ape-tab-btn.active{color:#16a34a;border-color:#16a34a}
.ape-tab-panel{display:none;flex-direction:column;gap:14px;min-height:0}
.ape-tab-panel.active{display:flex;padding:16px;height:100%}
.ape-mode-btn{padding:6px 10px;border:1px solid #16a34a;border-radius:999px;background:#16a34a;font-size:11px;font-weight:700;color:#fff;cursor:pointer}
.ape-mode-btn.active{background:#15803d;border-color:#15803d;color:#fff}
.ape-panel-summary{display:flex;flex-direction:column;gap:4px}
.ape-panel-title-main{font-size:15px;font-weight:700;color:#0f172a}
.ape-study-status{display:flex;flex-direction:column;gap:6px;font-size:11px;color:#475569}
.ape-field{display:flex;flex-direction:column;gap:6px;min-width:0;max-width:100%}
.ape-field label{font-size:12px;font-weight:800;color:#0f172a}
.ape-field input,.ape-field textarea,.ape-field select{width:100%;max-width:100%;min-width:0;box-sizing:border-box;resize:vertical;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;font-size:13px}
.ape-field small,.ape-note{font-size:11px;color:#475569;overflow-wrap:anywhere;word-break:break-word}
.ape-save-area{display:flex;gap:10px;align-items:center;padding-top:8px;border-top:1px solid #e2e8f0;margin-top:auto}
.ape-save-msg{font-size:11px;margin-bottom:0}
.ape-comment-modal{display:none;position:fixed;inset:0;z-index:60}
.ape-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.42)}
.ape-drawer-panel{position:absolute;top:0;right:0;width:min(520px,100vw);height:100%;background:#fff;border-left:1px solid #e2e8f0;box-shadow:-18px 0 42px rgba(15,23,42,.15);display:flex;flex-direction:column}
.ape-modal-panel{position:relative;width:min(720px,calc(100vw - 32px));max-height:85vh;margin:7.5vh auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 24px 60px rgba(15,23,42,.18);display:flex;flex-direction:column;overflow:hidden;color:#0f172a}
.ape-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid #e2e8f0}
.ape-panel-title{font-size:14px;font-weight:800;color:#0f172a}
.ape-panel-body{padding:16px 18px;overflow:auto;min-height:0}
.ape-hidden-meta{display:none}
.ape-mono-wrap{overflow-wrap:anywhere;word-break:break-word}
.ape-details{border:1px solid #e2e8f0;border-radius:8px;background:#fff;padding:10px 12px}
.ape-details summary{cursor:pointer;font-size:12px;font-weight:700;color:#0f172a}
@media (max-width:1400px){
  .ape-grid{grid-template-columns:240px minmax(0,1fr) 360px}
}
@media (max-width:1180px){
  .ape-grid{grid-template-columns:minmax(0,1fr) 340px;height:calc(100vh - 164px);min-height:720px}
  .ape-left-col{position:fixed;top:88px;left:24px;bottom:24px;width:min(320px,calc(100vw - 48px));max-width:calc(100vw - 48px);z-index:40;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 16px 40px rgba(15,23,42,.16);display:none}
  .ape-left-col.is-open{display:flex}
  .ape-center-col{order:1}
  .ape-right-col{order:2}
}
@media (max-width:900px){
  .ape-shell{border-radius:8px}
  .ape-grid{grid-template-columns:1fr;height:auto;min-height:0}
  .ape-left-col,.ape-right-col{border:none}
  .ape-left-col{left:16px;right:16px;top:74px;bottom:16px;width:auto;max-width:none}
  .ape-center-col{order:1}
  .ape-right-col{order:2}
  .ape-workspace{padding:12px}
  .ape-right-scroll,.ape-left-head,.ape-right-head,.ape-search{padding:12px}
  .ape-board-stage{width:min(100%,86vw);height:min(100%,86vw)}
  .board-controls,.ape-board-meta{width:100%}
}
</style>
@endpush
@endonce

@push('scripts')
@include('partials._chess-engine')
@endpush

<div class="ape-shell">
  <div class="ape-grid" id="ape-root">
    <div class="ape-left-col">
      <div class="ape-left-head">
        <div class="ape-left-title">Puzzles</div>
      </div>
      @if($editorPuzzleCount > 8)
        <div class="ape-search">
          <input id="ape-search" type="text" placeholder="Search puzzles">
        </div>
      @endif
      <div id="ape-puzzle-list" class="ape-list ape-puzzle-list">
        <div class="text-xs ape-text-muted" style="padding:14px 12px">Loading puzzles…</div>
      </div>
    </div>

    <div class="ape-center-col">
      <div class="ape-workspace">
        <div class="ape-board-wrap">
          <div class="ape-board-stage">
            <div id="ape-board"></div>
            <div class="annotation-overlay" aria-hidden="true"></div>
          </div>
          <div class="board-controls ape-board-nav">
            <button class="btn btn-sm btn-ghost" type="button" data-ape-act="start">First</button>
            <button class="btn btn-sm btn-ghost" type="button" data-ape-act="prev">Previous</button>
            <button class="btn btn-sm btn-ghost" type="button" data-ape-act="next">Next</button>
            <button class="btn btn-sm btn-ghost" type="button" data-ape-act="end">Last</button>
            @unless($editorReadOnly)
              <button class="btn btn-sm btn-ghost" type="button" data-ape-act="reset-puzzle">Reset Answer</button>
              <button class="btn btn-sm btn-primary" type="button" id="ape-save-answer-inline">Save Answer</button>
            @endunless
          </div>
          <div class="ape-board-meta">
            <div class="flex items-center gap-2">
              <div id="ape-turn-dot" style="width:10px;height:10px;border-radius:50%;background:#fff;border:1.5px solid #888"></div>
              <span id="ape-turn-label" class="ape-help">White to move</span>
            </div>
            <span id="ape-counter-visual" class="ape-help">0 / 0</span>
          </div>
        </div>

        <div class="ape-toolbar">
        </div>
      </div>
    </div>

    <div class="ape-right-col">
      <div class="ape-right-head">
        <div class="flex items-center justify-between gap-2">
          <div class="ape-right-title">Answer</div>
        </div>
        <div class="ape-tabs" style="margin-top:12px">
          <button type="button" class="ape-tab-btn active" data-ape-tab="moves">Moves</button>
          <button type="button" class="ape-tab-btn" data-ape-tab="comments">Comments</button>
        </div>
      </div>
      <div class="ape-right-scroll">
        <section class="ape-tab-panel active" data-ape-panel="moves">
          <div class="ape-tree-wrap">
            <div id="ape-tree" class="ape-tree">
              <div class="ape-tree-empty">No moves yet.</div>
            </div>
          </div>
        </section>

        <section class="ape-tab-panel" data-ape-panel="comments">
          <div class="ape-field">
            <label for="ape-move-comment">Move Comment</label>
            <textarea id="ape-move-comment" rows="6" @readonly($editorReadOnly)></textarea>
            <small id="ape-move-comment-help">{{ $editorReadOnly ? 'Comments are read-only.' : 'Select a move in the tree to edit its comment.' }}</small>
          </div>
          <div id="ape-save-errors" class="ape-save-msg" style="display:none;color:#b91c1c"></div>
        </section>
        <input id="ape-name" type="hidden" autocomplete="off">
        <textarea id="ape-question" rows="3" style="display:none"></textarea>
        <textarea id="ape-answer" rows="3" style="display:none"></textarea>
        <textarea id="ape-hint" rows="3" style="display:none"></textarea>
        <textarea id="ape-explanation" rows="4" style="display:none"></textarea>
        <textarea id="ape-annotation" rows="4" style="display:none"></textarea>
        <small id="ape-annotation-original" style="display:none"></small>
        <textarea id="ape-notes" rows="5" style="display:none"></textarea>
        <button type="button" id="ape-save-btn" style="display:none">Save</button>
      </div>
    </div>
  </div>

<div class="ape-hidden-meta">
    <div id="ape-counter"></div>
    <div id="ape-current-title"></div>
    <div id="ape-current-sub"></div>
    <div id="ape-status"></div>
    <div id="ape-save-note"></div>
    <span id="ape-unsaved"></span>
  </div>
</div>

@unless($editorReadOnly)
<div id="ape-move-menu" style="display:none;position:fixed;z-index:90;min-width:180px;background:#201e1c;border:1px solid #3a3530;border-radius:8px;box-shadow:0 16px 36px rgba(15,23,42,.24);padding:6px">
  <button type="button" class="btn btn-sm btn-ghost" style="width:100%;justify-content:flex-start" data-ape-menu="comment">Add Comment</button>
  <button type="button" class="btn btn-sm btn-ghost" style="width:100%;justify-content:flex-start" data-ape-menu="delete-move">Delete Move</button>
</div>
@endunless

@if($editorImportEnabled)
<div class="ape-comment-modal" id="ape-import-modal" style="{{ $errors->any() ? 'display:block' : 'display:none' }}">
  <div class="ape-backdrop" data-ape-close="import"></div>
  <div class="ape-modal-panel">
    <div class="ape-panel-head">
      <div class="ape-panel-title">Import PGN</div>
      <button type="button" class="btn btn-sm btn-ghost" data-ape-close="import">Close</button>
    </div>
    <div class="ape-panel-body">
      <form method="POST" action="{{ route('admin.library.chapter.update', $chapter) }}" enctype="multipart/form-data" id="ape-import-form">
        @csrf
        @method('PUT')
        <input type="hidden" name="name" value="{{ old('name', $chapter->name) }}">
        <input type="hidden" name="level" value="{{ old('level', $chapter->level) }}">
        <input type="hidden" name="sequence_number" value="{{ old('sequence_number', $chapter->sequence_number) }}">

        <div class="ape-field">
          <label for="ape-import-file">Upload PGN File</label>
          <input id="ape-import-file" type="file" name="pgn_file" accept=".pgn,.txt" required>
          @error('pgn_file')
            <small style="color:#b91c1c">{{ $message }}</small>
          @enderror
        </div>

        <div class="flex gap-2 mt-3">
          <button type="button" class="btn btn-sm btn-ghost" data-ape-close="import">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Import</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

@unless($editorReadOnly)
<div class="ape-comment-modal" id="ape-comment-modal">
  <div class="ape-backdrop" data-ape-close="comment"></div>
  <div class="ape-modal-panel" style="width:min(560px,calc(100vw - 32px))">
    <div class="ape-panel-head">
      <div class="ape-panel-title">Move Actions</div>
      <button type="button" class="btn btn-sm btn-ghost" data-ape-close="comment">Close</button>
    </div>
    <div class="ape-panel-body">
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
        <button class="btn btn-sm btn-ghost" type="button" data-ape-act="reset-node">Reset Move</button>
      </div>
    </div>
  </div>
</div>
@endunless

@push('scripts')
<script>
(function(){
  const PGN = @json($editorPgn);
  const PUZZLE_CHECKSUMS = @json($editorPuzzleChecksums);
  const PUZZLE_ANSWERS = @json($editorPuzzleAnswers);
  const EDITOR_READ_ONLY = @json((bool) $editorReadOnly);
  const DEFAULT_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
  const games = MiniChess.parsePGNFile(PGN) || [];
  const stateByPuzzle = {};
  const NAG_TO_PGN = {'!':'$1','?':'$2','!!':'$3','??':'$4','!?':'$5','?!':'$6'};
  const NAG_TO_CLASS = {'!!':'best','!':'best','!?':'good','?!':'inaccuracy','?':'mistake','??':'blunder'};
  const PGN_TO_NAG = {'$1':'!','$2':'?','$3':'!!','$4':'??','$5':'!?','$6':'?!'};
  let currentPuzzle = -1;
  let board = null;
  let savePending = false;
  let nextId = 1;
  let activeDrawColor = 'green';
  let warnOnLeave = false;
  let playTimer = null;
  let menuPath = null;

  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const clone = (v) => JSON.parse(JSON.stringify(v));
  const setText = (id, value) => { const el = $(id); if (el) el.textContent = value; };
  const setHtml = (id, value) => { const el = $(id); if (el) el.innerHTML = value; };
  const showEl = (id, show = true) => { const el = $(id); if (el) el.style.display = show ? '' : 'none'; };

  function newNode(data = {}) {
    return {
      id: nextId++,
      san: data.san || '',
      fen: data.fen || '',
      moveNumber: data.moveNumber || 0,
      side: data.side || 'w',
      commentText: data.commentText || '',
      arrows: clone(data.arrows || []),
      highlights: clone(data.highlights || []),
      nag: data.nag || '',
      extraNags: clone(data.extraNags || []),
      children: [],
    };
  }

  function extractCommentParts(raw) {
    let text = String(raw || '');
    const arrows = [];
    const highlights = [];
    text = text.replace(/\[%cal\s+([^\]]+)\]/gi, (_, payload) => {
      payload.split(',').map(s => s.trim()).filter(Boolean).forEach(item => {
        const m = item.match(/^([GRBY])([a-h][1-8])([a-h][1-8])$/i);
        if (m) arrows.push({ from: m[2], to: m[3], color: ({G:'green',R:'red',B:'blue',Y:'yellow'})[m[1].toUpperCase()] || 'green' });
      });
      return '';
    });
    text = text.replace(/\[%csl\s+([^\]]+)\]/gi, (_, payload) => {
      payload.split(',').map(s => s.trim()).filter(Boolean).forEach(item => {
        const m = item.match(/^([GRBY])([a-h][1-8])$/i);
        if (m) highlights.push({ sq: m[2], color: ({G:'green',R:'red',B:'blue',Y:'yellow'})[m[1].toUpperCase()] || 'green' });
      });
      return '';
    });
    return { text: text.trim(), arrows, highlights };
  }

  function serializeComment(node) {
    const parts = [];
    if (node.arrows?.length) {
      const payload = node.arrows.map(a => ({green:'G',red:'R',blue:'B',yellow:'Y'})[a.color || 'green'] + a.from + a.to).join(',');
      if (payload) parts.push('[%cal ' + payload + ']');
    }
    if (node.highlights?.length) {
      const payload = node.highlights.map(h => ({green:'G',red:'R',blue:'B',yellow:'Y'})[h.color || 'green'] + h.sq).join(',');
      if (payload) parts.push('[%csl ' + payload + ']');
    }
    if ((node.commentText || '').trim()) parts.push(node.commentText.trim());
    return parts.join(' ').trim();
  }

  function pgnLineToTree(line, parent) {
    if (!Array.isArray(line) || !line.length) return;
    for (let i = 0; i < line.length; i++) {
      const move = line[i];
      const parsedComment = extractCommentParts(move.comment || '');
      const primaryNag = move.nag || ((move.nags || []).map(n => PGN_TO_NAG[n]).find(Boolean) || '');
      const extraNags = (move.nags || []).filter(n => PGN_TO_NAG[n] !== primaryNag);
      const node = newNode({
        san: move.san,
        fen: move.fen,
        moveNumber: move.moveNumber,
        side: move.side,
        commentText: parsedComment.text,
        arrows: parsedComment.arrows,
        highlights: parsedComment.highlights,
        nag: primaryNag,
        extraNags,
      });
      parent.children.push(node);
      if (Array.isArray(move.variations)) {
        move.variations.forEach(varLine => pgnLineToTree(varLine, parent));
      }
      parent = node;
    }
  }

  function buildRootFromSource(game, savedAnswerPgn = null) {
    const root = newNode({ fen: game?.startFen || DEFAULT_FEN, side: 'w' });
    const hasSavedAnswer = typeof savedAnswerPgn === 'string' && savedAnswerPgn.trim() !== '';
    const answerGame = hasSavedAnswer ? ((MiniChess.parsePGNFile(savedAnswerPgn) || [])[0] || null) : null;
    const sourceLine = hasSavedAnswer ? (answerGame?.mainLine || []) : (game?.mainLine || []);
    pgnLineToTree(sourceLine, root);
    return root;
  }

  function buildPuzzleLabel(game, index) {
    return 'Puzzle ' + (index + 1);
  }

  function buildPuzzleMeta(game) {
    const h = game.headers || {};
    const bits = [];
    if (h.event) bits.push(h.event);
    if (h.white || h.black) bits.push([h.white, h.black].filter(Boolean).join(' vs '));
    if (h.fen) bits.push('FEN start');
    return bits.join(' · ') || 'Parsed from PGN headers';
  }

  function makeSnapshot(editor) {
    return {
      name: editor.name,
      question: editor.question,
      answer: editor.answer,
      hint: editor.hint,
      explanation: editor.explanation,
      generalNotes: editor.generalNotes,
      annotation: editor.annotation,
      root: clone(editor.root),
      currentPath: clone(editor.currentPath),
      branchHint: editor.branchHint,
    };
  }

  function pushUndo(editor) {
    editor.undoStack.push(makeSnapshot(editor));
    if (editor.undoStack.length > 100) editor.undoStack.shift();
    editor.redoStack = [];
  }

  function restoreSnapshot(editor, snap) {
    editor.name = snap.name;
    editor.question = snap.question;
    editor.answer = snap.answer;
    editor.hint = snap.hint;
    editor.explanation = snap.explanation;
    editor.generalNotes = snap.generalNotes;
    editor.annotation = snap.annotation;
    editor.root = clone(snap.root);
    editor.currentPath = clone(snap.currentPath || []);
    editor.branchHint = !!snap.branchHint;
  }

  function ensureEditor(index) {
    if (stateByPuzzle[index]) return stateByPuzzle[index];
    const game = games[index] || {};
    const savedAnswer = PUZZLE_ANSWERS?.[index] || PUZZLE_ANSWERS?.[index + 1] || null;
    const root = buildRootFromSource(game, savedAnswer?.answer_pgn || null);
    const editor = {
      id: index,
      headers: clone(game.headers || {}),
      startFen: game.startFen || DEFAULT_FEN,
      root,
      originalRoot: clone(root),
      name: buildPuzzleLabel(game, index),
      question: game.headers?.archerquestion || '',
      answer: game.headers?.archeranswer || '',
      hint: game.headers?.archerhint || '',
      explanation: game.headers?.archerexplanation || '',
      generalNotes: game.headers?.archernotes || '',
      annotation: '',
      original: {
        name: buildPuzzleLabel(game, index),
        question: game.headers?.archerquestion || '',
        answer: game.headers?.archeranswer || '',
        hint: game.headers?.archerhint || '',
        explanation: game.headers?.archerexplanation || '',
        generalNotes: game.headers?.archernotes || '',
        annotation: '',
      },
      currentPath: [],
      undoStack: [],
      redoStack: [],
      originalChecksum: PUZZLE_CHECKSUMS[index] || null,
      branchHint: false,
    };
    stateByPuzzle[index] = editor;
    return editor;
  }

  function currentEditor() {
    return currentPuzzle >= 0 ? ensureEditor(currentPuzzle) : null;
  }

  function getNodeAtPath(root, path) {
    let node = root;
    for (const idx of (path || [])) {
      node = node?.children?.[idx] || null;
      if (!node) return null;
    }
    return node;
  }

  function getFenAt(editor, path) {
    const node = getNodeAtPath(editor.root, path);
    return node?.fen || editor.startFen || DEFAULT_FEN;
  }

  function currentNode(editor) {
    return (editor.currentPath || []).length ? getNodeAtPath(editor.root, editor.currentPath) : null;
  }

  function currentParent(editor) {
    if (!(editor.currentPath || []).length) return editor.root;
    return getNodeAtPath(editor.root, editor.currentPath.slice(0, -1));
  }

  function countMainMoves(root) {
    let n = root, c = 0;
    while (n.children[0]) { n = n.children[0]; c++; }
    return c;
  }

  function isDirty(editor) {
    return JSON.stringify({
      root: editor.root,
      name: editor.name,
      question: editor.question,
      answer: editor.answer,
      hint: editor.hint,
      explanation: editor.explanation,
      generalNotes: editor.generalNotes,
      annotation: editor.annotation,
    }) !== JSON.stringify({
      root: editor.originalRoot,
      name: editor.original.name,
      question: editor.original.question,
      answer: editor.original.answer,
      hint: editor.original.hint,
      explanation: editor.original.explanation,
      generalNotes: editor.original.generalNotes,
      annotation: editor.original.annotation,
    });
  }

  function anyDirty() {
    return Object.values(stateByPuzzle).some(isDirty);
  }

  function moveToken(node, lastMn, lastSide) {
    const bits = [];
    if (node.side === 'w') {
      if (node.moveNumber !== lastMn) bits.push(node.moveNumber + '.');
    } else if (node.moveNumber !== lastMn || lastSide !== 'w') {
      bits.push(node.moveNumber + '...');
    }
    let san = node.san;
    const nags = [];
    if (node.nag && NAG_TO_PGN[node.nag]) nags.push(NAG_TO_PGN[node.nag]);
    (node.extraNags || []).forEach(n => nags.push(n));
    san += nags.length ? ' ' + nags.join(' ') : '';
    bits.push(san);
    const comment = serializeComment(node);
    if (comment) bits.push('{' + comment + '}');
    return bits.join(' ');
  }

  function serializeVariation(node) {
    let out = [];
    let cur = node;
    let lastMn = -1, lastSide = null;
    while (cur) {
      out.push(moveToken(cur, lastMn, lastSide));
      cur.children.slice(1).forEach(v => out.push('(' + serializeVariation(v) + ')'));
      lastMn = cur.moveNumber;
      lastSide = cur.side;
      cur = cur.children[0] || null;
    }
    return out.join(' ');
  }

  function serializeMovetext(root) {
    if (!root.children.length) return '*';
    return serializeVariation(root.children[0]) + (root.children.slice(1).map(v => ' (' + serializeVariation(v) + ')').join('')) + ' *';
  }

  function serializePuzzle(editor) {
    const headers = {};
    headers.event = editor.name || ('Puzzle ' + (editor.id + 1));

    if ((editor.startFen || DEFAULT_FEN) !== DEFAULT_FEN) {
      headers.fen = editor.startFen;
      headers.setup = '1';
    } else {
      delete headers.fen;
      delete headers.setup;
    }

    const headerLabel = (k) => ({
      event: 'Event',
      fen: 'FEN',
      setup: 'SetUp',
      archerpuzzlename: 'ArcherPuzzleName',
      archerquestion: 'ArcherQuestion',
      archeranswer: 'ArcherAnswer',
      archerhint: 'ArcherHint',
      archerexplanation: 'ArcherExplanation',
      archernotes: 'ArcherNotes',
      white: 'White',
      black: 'Black',
    }[String(k).toLowerCase()] || String(k));
    const order = Object.entries(headers).filter(([,v]) => String(v ?? '').trim() !== '');
    const headerBlock = order.map(([k,v]) => '[' + headerLabel(k) + ' "' + String(v).replace(/\\/g,'\\\\').replace(/"/g,'\\"') + '"]').join('\n');
    return headerBlock + '\n\n' + serializeMovetext(editor.root);
  }

  function clearSaveErrors() {
    showEl('ape-save-errors', false);
    setText('ape-save-errors', '');
  }

  function showSaveErrors(lines) {
    showEl('ape-save-errors', true);
    setHtml('ape-save-errors', (Array.isArray(lines) ? lines : [lines]).map(line => '<div>' + esc(line) + '</div>').join(''));
  }

  function renderPuzzleList() {
    const root = $('ape-puzzle-list');
    const query = ($('ape-search')?.value || '').trim().toLowerCase();
    if (!games.length) {
      root.innerHTML = '<div class="text-xs text-muted" style="padding:14px 12px">No puzzles detected in this chapter.</div>';
      return;
    }
    root.innerHTML = '';
    games.forEach((game, index) => {
      const editor = ensureEditor(index);
      const label = editor.name || buildPuzzleLabel(game, index);
      if (query && !label.toLowerCase().includes(query)) return;
      const row = document.createElement('div');
      row.className = 'ape-row' + (index === currentPuzzle ? ' active' : '');
      row.innerHTML =
        '<span class="ape-row-index">' + String(index + 1).padStart(2, '0') + '</span>'
        + '<span class="ape-row-name">' + esc(label) + '</span>'
        + (isDirty(editor) ? '<span class="ape-row-dirty" title="Unsaved changes"></span>' : '');
      row.addEventListener('click', () => loadPuzzle(index));
      root.appendChild(row);
    });
    if (!root.children.length) {
      root.innerHTML = '<div class="text-xs text-muted" style="padding:14px 12px">No puzzles match your search.</div>';
    }
  }

  function renderMoveNumber(host, node, lastMn, lastSide) {
    const span = document.createElement('span');
    span.className = 'mn';
    if (node.side === 'w') {
      if (node.moveNumber !== lastMn) {
        span.textContent = node.moveNumber + '.';
        host.appendChild(span);
      }
    } else if (node.moveNumber !== lastMn || lastSide !== 'w') {
      span.textContent = node.moveNumber + '...';
      host.appendChild(span);
    }
    return node.moveNumber;
  }

  function samePath(a, b) {
    if (!a || !b || a.length !== b.length) return false;
    return a.every((v, i) => v === b[i]);
  }

  function renderMoveEl(node, path) {
    const el = document.createElement('span');
    el.className = 'mv';
    if (node.nag && NAG_TO_CLASS[node.nag]) el.classList.add(NAG_TO_CLASS[node.nag]);
    if (samePath(path, currentEditor().currentPath)) el.classList.add('active');
    const marks = [];
    if ((node.commentText || '').trim()) marks.push('<span class="mark cmt">Comment</span>');
    if ((node.arrows || []).length || (node.highlights || []).length) marks.push('<span class="mark draw">Board</span>');
    el.innerHTML = esc(node.san) + (node.nag ? '<span class="nag">' + esc(node.nag) + '</span>' : '') + marks.join('');
    el.addEventListener('click', () => {
      const editor = currentEditor();
      editor.currentPath = path.slice();
      syncCurrent(editor);
    });
    el.addEventListener('contextmenu', (event) => {
      if (EDITOR_READ_ONLY) return;
      event.preventDefault();
      const editor = currentEditor();
      editor.currentPath = path.slice();
      syncCurrent(editor);
      openMoveMenu(event.clientX, event.clientY, path);
    });
    return el;
  }

  function openMoveMenu(x, y, path) {
    menuPath = path.slice();
    const menu = $('ape-move-menu');
    if (!menu) return;
    menu.style.left = Math.max(12, x) + 'px';
    menu.style.top = Math.max(12, y) + 'px';
    menu.style.display = 'block';
  }

  function closeMoveMenu() {
    menuPath = null;
    showEl('ape-move-menu', false);
  }

  function maybeInlineComment(host, node) {
    if ((node.commentText || '').trim()) {
      const c = document.createElement('span');
      c.className = 'cmt-inline';
      const t = node.commentText.trim();
      c.textContent = t.length > 70 ? t.slice(0, 70) + '…' : t;
      c.title = t;
      host.appendChild(c);
    }
  }

  function renderBranch(host, parent, parentPath, isVariation) {
    if (!parent.children.length) return;
    let lastMoveNumber = -1;
    let lastSide = null;
    for (let i = 0; i < parent.children.length; i++) {
      const child = parent.children[i];
      if (i === 0) {
        const path = parentPath.concat([0]);
        lastMoveNumber = renderMoveNumber(host, child, lastMoveNumber, lastSide);
        lastSide = child.side;
        host.appendChild(renderMoveEl(child, path));
        maybeInlineComment(host, child);
      } else {
        const varHost = document.createElement('span');
        varHost.className = 'var-line';
        varHost.appendChild(document.createTextNode('('));
        let lmn = -1, lsd = null;
        const path = parentPath.concat([i]);
        lmn = renderMoveNumber(varHost, child, lmn, lsd);
        lsd = child.side;
        varHost.appendChild(renderMoveEl(child, path));
        maybeInlineComment(varHost, child);
        renderBranch(varHost, child, path, true);
        varHost.appendChild(document.createTextNode(')'));
        host.appendChild(varHost);
      }
    }
    if (parent.children[0]) {
      renderBranch(host, parent.children[0], parentPath.concat([0]), isVariation);
    }
  }

  function renderTree(editor) {
    const host = $('ape-tree');
    host.innerHTML = '';
    if (!editor.root.children.length) {
      host.innerHTML = '<div class="ape-tree-empty">No moves yet — drag a piece on the board to start a line.</div>';
      return;
    }
    const wrap = document.createElement('div');
    renderBranch(wrap, editor.root, [], false);
    host.appendChild(wrap);
    host.querySelector('.mv.active')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function syncBoard(editor) {
    const fen = getFenAt(editor, editor.currentPath);
    board.setPosition(fen);
    const state = MiniChess.parseFen(fen);
    $('ape-turn-dot').style.background = state.turn === 'w' ? '#fff' : '#222';
    $('ape-turn-label').textContent = state.turn === 'w' ? 'White to move' : 'Black to move';
    const node = currentNode(editor);
    board.setOverlay(node?.arrows || [], node?.highlights || []);
  }

  function syncDetails(editor) {
    $('ape-name').value = editor.name;
    $('ape-question').value = editor.question;
    $('ape-answer').value = editor.answer;
    $('ape-hint').value = editor.hint;
    $('ape-explanation').value = editor.explanation;
    $('ape-notes').value = editor.generalNotes;
    $('ape-annotation').value = editor.annotation;
    $('ape-annotation-original').textContent = 'Original comment: ' + (editor.original.annotation || 'none');
    const node = currentNode(editor);
    $('ape-move-comment').disabled = EDITOR_READ_ONLY || !node;
    $('ape-move-comment').value = node?.commentText || '';
    $('ape-move-comment-help').textContent = EDITOR_READ_ONLY
      ? 'Comments are read-only.'
      : (node ? 'Comment will be saved with this move.' : 'Select a move to edit its comment.');
  }

  function syncHeader(editor) {
    setText('ape-current-title', editor.name || ('Puzzle ' + (editor.id + 1)));
    setText('ape-current-sub', buildPuzzleMeta(games[editor.id]) + ' · ' + countMainMoves(editor.root) + ' main move' + (countMainMoves(editor.root) === 1 ? '' : 's'));
    setText('ape-counter', editor.currentPath.length + '/' + countMainMoves(editor.root));
    setText('ape-counter-visual', editor.currentPath.length + ' / ' + countMainMoves(editor.root));
    $('ape-unsaved')?.classList.toggle('show', isDirty(editor));
    setText('ape-top-status', savePending ? 'Saving...' : (isDirty(editor) ? 'Unsaved Changes' : 'Saved'));
    setText('ape-status', editor.branchHint
      ? 'Variation mode ready: play an alternative move from the current position.'
      : (isDirty(editor) ? 'Unsaved changes are kept for this puzzle until you save or reset.' : 'No unsaved changes on this puzzle.'));
    document.querySelectorAll('#ape-save-btn, #ape-save-answer-inline').forEach(btn => {
      btn.disabled = savePending || !editor.originalChecksum;
      btn.textContent = savePending ? 'Saving...' : 'Save Answer';
    });
    setText('ape-save-note', 'Only the selected puzzle is saved back into the same chapter PGN.');
    warnOnLeave = anyDirty();
  }

  function syncCurrent(editor) {
    renderPuzzleList();
    renderTree(editor);
    syncBoard(editor);
    syncHeader(editor);
    syncDetails(editor);
  }

  function markChanged(mutator) {
    const editor = currentEditor();
    if (!editor) return;
    clearSaveErrors();
    pushUndo(editor);
    mutator(editor);
    syncCurrent(editor);
  }

  function isSquareAttacked(state, rank, file, bySide) {
    for (let r = 0; r < 8; r++) {
      for (let f = 0; f < 8; f++) {
        const piece = MiniChess.getPiece(state.board, r, f);
        if (!piece) continue;
        const pieceSide = piece === piece.toUpperCase() ? 'w' : 'b';
        if (pieceSide !== bySide) continue;
        const type = piece.toLowerCase();
        if (type === 'p') {
          const dir = pieceSide === 'w' ? 1 : -1;
          if (r + dir === rank && (f + 1 === file || f - 1 === file)) return true;
          continue;
        }
        if (MiniChess.pieceMoves(state, r, f).some(([tr, tf]) => tr === rank && tf === file)) return true;
      }
    }
    return false;
  }

  function findKing(state, side) {
    const king = side === 'w' ? 'K' : 'k';
    for (let r = 0; r < 8; r++) {
      for (let f = 0; f < 8; f++) {
        if (MiniChess.getPiece(state.board, r, f) === king) return [r, f];
      }
    }
    return null;
  }

  function isKingInCheck(state, side) {
    const king = findKing(state, side);
    return king ? isSquareAttacked(state, king[0], king[1], side === 'w' ? 'b' : 'w') : false;
  }

  function applyMoveToState(state, fromSq, toSq) {
    const nextState = clone(state);
    const [fr, ff] = MiniChess.sqToIdx(fromSq);
    const [tr, tf] = MiniChess.sqToIdx(toSq);
    const moving = MiniChess.getPiece(nextState.board, fr, ff);
    if (!moving) return null;
    const isWhite = moving === moving.toUpperCase();
    const side = isWhite ? 'w' : 'b';
    const target = MiniChess.getPiece(nextState.board, tr, tf);
    const wasCapture = !!target;
    if (moving.toLowerCase() === 'k' && Math.abs(tf - ff) === 2) {
      const kingside = tf > ff;
      MiniChess.setPiece(nextState.board, fr, ff, null);
      MiniChess.setPiece(nextState.board, tr, tf, moving);
      MiniChess.setPiece(nextState.board, fr, kingside ? 7 : 0, null);
      MiniChess.setPiece(nextState.board, fr, kingside ? 5 : 3, isWhite ? 'R' : 'r');
      nextState.castling = (nextState.castling || '-').replace(isWhite ? /[KQ]/g : /[kq]/g, '') || '-';
      nextState.ep = '-';
    } else {
      if (moving.toLowerCase() === 'p' && state.ep !== '-' && toSq === state.ep && !target) {
        MiniChess.setPiece(nextState.board, tr + (isWhite ? -1 : 1), tf, null);
      }
      MiniChess.setPiece(nextState.board, fr, ff, null);
      let finalPiece = moving;
      if (moving.toLowerCase() === 'p' && (tr === 7 || tr === 0)) finalPiece = isWhite ? 'Q' : 'q';
      MiniChess.setPiece(nextState.board, tr, tf, finalPiece);
      nextState.ep = (moving.toLowerCase() === 'p' && Math.abs(tr - fr) === 2) ? MiniChess.idxToSq((fr + tr) / 2, ff) : '-';
      let castling = nextState.castling || '-';
      if (moving === 'K') castling = castling.replace(/[KQ]/g, '');
      if (moving === 'k') castling = castling.replace(/[kq]/g, '');
      if (moving === 'R') { if (fr === 0 && ff === 0) castling = castling.replace('Q', ''); if (fr === 0 && ff === 7) castling = castling.replace('K', ''); }
      if (moving === 'r') { if (fr === 7 && ff === 0) castling = castling.replace('q', ''); if (fr === 7 && ff === 7) castling = castling.replace('k', ''); }
      if (tr === 0 && tf === 0) castling = castling.replace('Q', '');
      if (tr === 0 && tf === 7) castling = castling.replace('K', '');
      if (tr === 7 && tf === 0) castling = castling.replace('q', '');
      if (tr === 7 && tf === 7) castling = castling.replace('k', '');
      nextState.castling = castling || '-';
    }
    nextState.turn = side === 'w' ? 'b' : 'w';
    if (side === 'b') nextState.fullMove += 1;
    nextState.halfMove = (moving.toLowerCase() === 'p' || wasCapture) ? 0 : nextState.halfMove + 1;
    return nextState;
  }

  function canCastle(state, side, dir) {
    const rank = side === 'w' ? 0 : 7;
    const rights = state.castling || '-';
    const king = MiniChess.getPiece(state.board, rank, 4);
    if (king !== (side === 'w' ? 'K' : 'k') || isKingInCheck(state, side)) return false;
    if (dir === 'kingside') {
      if (!rights.includes(side === 'w' ? 'K' : 'k')) return false;
      if (MiniChess.getPiece(state.board, rank, 7) !== (side === 'w' ? 'R' : 'r')) return false;
      if (MiniChess.getPiece(state.board, rank, 5) || MiniChess.getPiece(state.board, rank, 6)) return false;
      if (isSquareAttacked(state, rank, 5, side === 'w' ? 'b' : 'w')) return false;
      if (isSquareAttacked(state, rank, 6, side === 'w' ? 'b' : 'w')) return false;
      return true;
    }
    if (!rights.includes(side === 'w' ? 'Q' : 'q')) return false;
    if (MiniChess.getPiece(state.board, rank, 0) !== (side === 'w' ? 'R' : 'r')) return false;
    if (MiniChess.getPiece(state.board, rank, 1) || MiniChess.getPiece(state.board, rank, 2) || MiniChess.getPiece(state.board, rank, 3)) return false;
    if (isSquareAttacked(state, rank, 3, side === 'w' ? 'b' : 'w')) return false;
    if (isSquareAttacked(state, rank, 2, side === 'w' ? 'b' : 'w')) return false;
    return true;
  }

  function legalMovesForSquare(state, rank, file) {
    const piece = MiniChess.getPiece(state.board, rank, file);
    if (!piece) return [];
    const side = piece === piece.toUpperCase() ? 'w' : 'b';
    if (state.turn !== side) return [];
    const moves = MiniChess.pieceMoves(state, rank, file).filter(([tr, tf]) => {
      const next = applyMoveToState(state, MiniChess.idxToSq(rank, file), MiniChess.idxToSq(tr, tf));
      return next && !isKingInCheck(next, side);
    });
    if (piece.toLowerCase() === 'k') {
      if (canCastle(state, side, 'kingside')) moves.push([rank, 6]);
      if (canCastle(state, side, 'queenside')) moves.push([rank, 2]);
    }
    return moves;
  }

  function loadPuzzle(index) {
    currentPuzzle = index;
    const editor = ensureEditor(index);
    syncCurrent(editor);
  }

  function onUserMove(from, to, newFen) {
    if (EDITOR_READ_ONLY) return;
    const editor = currentEditor();
    if (!editor) return;
    const parent = currentNode(editor) || editor.root;
    const preFen = getFenAt(editor, editor.currentPath);
    const legalSquares = legalMovesForSquare(MiniChess.parseFen(preFen), ...MiniChess.sqToIdx(from)).map(([r, f]) => MiniChess.idxToSq(r, f));
    if (!legalSquares.includes(to)) {
      board.setPosition(preFen);
      return;
    }

    let san = null;
    try {
      const res = MiniChess.moveToSan(MiniChess.parseFen(preFen), from, to, newFen);
      san = typeof res === 'string' ? res : (res?.san || null);
    } catch (e) {}
    if (!san) san = from + to;

    markChanged(() => {
      const existingIdx = parent.children.findIndex(c => String(c.san).replace(/[+#!?]/g, '') === String(san).replace(/[+#!?]/g, ''));
      if (existingIdx >= 0 && !editor.branchHint) {
        editor.currentPath = editor.currentPath.concat([existingIdx]);
        return;
      }
      const parsed = MiniChess.parseFen(preFen);
      const moveNumber = parsed.fullMove;
      const side = parsed.turn;
      const node = newNode({
        san,
        fen: newFen,
        moveNumber,
        side,
      });
      parent.children.push(node);
      editor.currentPath = editor.currentPath.concat([parent.children.length - 1]);
      editor.branchHint = false;
    });
  }

  function deleteCurrentVariation() {
    const editor = currentEditor();
    if (!editor || !editor.currentPath.length) return;
    const idx = editor.currentPath[editor.currentPath.length - 1];
    if (idx === 0) return;
    markChanged(() => {
      const parent = currentParent(editor);
      parent.children.splice(idx, 1);
      editor.currentPath = editor.currentPath.slice(0, -1).concat([Math.max(0, idx - 1)]);
      if (editor.currentPath[editor.currentPath.length - 1] !== 0) editor.currentPath[editor.currentPath.length - 1] = 0;
    });
  }

  function promoteVariation() {
    const editor = currentEditor();
    if (!editor || !editor.currentPath.length) return;
    markChanged(() => {
      const path = editor.currentPath.slice();
      for (let depth = 0; depth < path.length; depth++) {
        const idx = path[depth];
        if (idx === 0) continue;
        const parent = depth === 0 ? editor.root : getNodeAtPath(editor.root, path.slice(0, depth));
        const node = parent.children[idx];
        parent.children.splice(idx, 1);
        parent.children.unshift(node);
        path[depth] = 0;
      }
      editor.currentPath = path;
    });
  }

  function moveVariation(delta) {
    const editor = currentEditor();
    if (!editor || !editor.currentPath.length) return;
    const idx = editor.currentPath[editor.currentPath.length - 1];
    if (idx === 0) return;
    const parent = currentParent(editor);
    const target = idx + delta;
    if (target < 1 || target >= parent.children.length) return;
    markChanged(() => {
      [parent.children[idx], parent.children[target]] = [parent.children[target], parent.children[idx]];
      editor.currentPath[editor.currentPath.length - 1] = target;
    });
  }

  function returnToMain() {
    const editor = currentEditor();
    if (!editor || !editor.currentPath.length) return;
    if (editor.currentPath[editor.currentPath.length - 1] === 0) return;
    markChanged(() => {
      editor.currentPath[editor.currentPath.length - 1] = 0;
    });
  }

  function resetSelectedNode() {
    const editor = currentEditor();
    const node = currentNode(editor);
    if (!editor || !node) return;
    markChanged(() => {
      node.commentText = '';
      node.nag = '';
      node.extraNags = [];
      node.arrows = [];
      node.highlights = [];
    });
  }

  function deleteSelectedMove() {
    const editor = currentEditor();
    if (!editor || !editor.currentPath.length) return;
    markChanged(() => {
      const path = editor.currentPath.slice();
      const idx = path[path.length - 1];
      const parent = getNodeAtPath(editor.root, path.slice(0, -1)) || editor.root;
      parent.children.splice(idx, 1);
      if (parent.children.length) {
        const nextIndex = Math.max(0, Math.min(idx - 1, parent.children.length - 1));
        editor.currentPath = path.slice(0, -1).concat([nextIndex]);
      } else {
        editor.currentPath = path.slice(0, -1);
      }
    });
  }

  async function saveEditorPuzzle(editor, options = {}) {
    const { showToast = true, updateUi = true } = options;
    if (EDITOR_READ_ONLY) return false;
    if (!editor || savePending) return false;
    if (!editor.originalChecksum) {
      showSaveErrors('This puzzle cannot be saved right now. Reload the chapter and try again.');
      window.ArcherToast?.show('This puzzle cannot be saved right now. Reload the chapter and try again.', 'error');
      return false;
    }
    clearSaveErrors();
    savePending = true;
    if (updateUi && editor === currentEditor()) {
      syncCurrent(editor);
    }
    const payload = {
      original_checksum: editor.originalChecksum,
      single_game_pgn: serializePuzzle(editor),
      puzzle_name: editor.name || '',
      question: editor.question || '',
      answer: editor.answer || '',
      hint: editor.hint || '',
      explanation: editor.explanation || '',
      general_notes: editor.generalNotes || '',
      annotation: editor.annotation || '',
    };
    try {
      const response = await fetch(@json($editorSaveUrlTemplate).replace('__INDEX__', String(editor.id)), {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': @json(csrf_token()),
        },
        body: JSON.stringify(payload),
      });
      const data = await response.json().catch(() => ({}));
      if (response.status === 409) {
        showSaveErrors('This chapter was changed in another session. Reload the chapter before saving.');
        window.ArcherToast?.show('This chapter was changed in another session. Reload and try again.', 'error');
        return false;
      }
      if (response.status === 422) {
        const lines = Object.values(data.errors || {}).flat();
        showSaveErrors(lines.length ? lines : (data.message || 'Puzzle could not be saved.'));
        window.ArcherToast?.show(lines[0] || data.message || 'Puzzle could not be saved.', 'error');
        return false;
      }
      if (!response.ok) {
        showSaveErrors(data.message || 'Failed to save this puzzle.');
        window.ArcherToast?.show(data.message || 'Failed to save this puzzle.', 'error');
        return false;
      }
      const savedGame = (MiniChess.parsePGNFile(data.saved_single_game_pgn || '') || [])[0];
      if (savedGame) {
        games[editor.id] = savedGame;
        PUZZLE_ANSWERS[editor.id] = {
          answer_pgn: data.saved_single_game_pgn || '',
          final_fen: data.final_fen || '',
          saved_at: new Date().toISOString(),
        };
        const freshRoot = newNode({ fen: editor.startFen || DEFAULT_FEN, side: 'w' });
        pgnLineToTree(savedGame.mainLine || [], freshRoot);
        editor.root = freshRoot;
        editor.originalRoot = clone(freshRoot);
        editor.original = {
          name: editor.name,
          question: editor.question,
          answer: editor.answer,
          hint: editor.hint,
          explanation: editor.explanation,
          generalNotes: editor.generalNotes,
          annotation: editor.annotation,
        };
        editor.currentPath = [];
      }
      editor.originalChecksum = data.checksum || editor.originalChecksum;
      editor.undoStack = [];
      editor.redoStack = [];
      setText('ape-save-note', data.message || 'Answer saved successfully.');
      if (showToast) {
        window.ArcherToast?.show(data.message || 'Answer saved successfully.', 'success');
      }
      if (updateUi && editor === currentEditor()) {
        syncCurrent(editor);
      } else {
        renderPuzzleList();
        warnOnLeave = anyDirty();
      }
      return true;
    } finally {
      savePending = false;
      if (updateUi && editor === currentEditor()) {
        syncCurrent(editor);
      } else {
        renderPuzzleList();
        warnOnLeave = anyDirty();
      }
    }
  }

  async function saveCurrentPuzzle(showToast = false) {
    const editor = currentEditor();
    if (!editor) return false;
    return saveEditorPuzzle(editor, { showToast, updateUi: true });
  }

  function wireFields() {
    $('ape-search')?.addEventListener('input', renderPuzzleList);
    if (EDITOR_READ_ONLY) return;
    $('ape-name').addEventListener('input', e => markChanged(editor => editor.name = e.target.value));
    $('ape-question').addEventListener('input', e => markChanged(editor => editor.question = e.target.value));
    $('ape-answer').addEventListener('input', e => markChanged(editor => editor.answer = e.target.value));
    $('ape-hint').addEventListener('input', e => markChanged(editor => editor.hint = e.target.value));
    $('ape-explanation').addEventListener('input', e => markChanged(editor => editor.explanation = e.target.value));
    $('ape-notes').addEventListener('input', e => markChanged(editor => editor.generalNotes = e.target.value));
    $('ape-annotation').addEventListener('input', e => markChanged(editor => editor.annotation = e.target.value));
    $('ape-move-comment').addEventListener('input', e => {
      const editor = currentEditor(); const node = currentNode(editor);
      if (!node) return;
      markChanged(() => node.commentText = e.target.value);
    });
  }

  function wireButtons() {
    $('ape-save-answer-inline')?.addEventListener('click', () => saveCurrentPuzzle(false));
    const submitChapterSave = async () => {
      const editor = currentEditor();
      const form = document.getElementById('chapter-import-form');
      const fileInput = document.getElementById('chapter-import-file');
      const text = document.getElementById('chapter-import-text');
      if (!form || !text) {
        return;
      }
      if (savePending) {
        return;
      }
      if (editor && isDirty(editor)) {
        const saved = await saveEditorPuzzle(editor, { showToast: false, updateUi: true });
        if (!saved) {
          return;
        }
      }
      const hasStagedImport = !!String(text.value || '').trim() || !!fileInput?.files?.length;
      if (!hasStagedImport) {
        return;
      }
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
      }
      form.submit();
    };
    $('ape-save-chapter-inline')?.addEventListener('click', submitChapterSave);
    $('ape-save-chapter-topbar')?.addEventListener('click', submitChapterSave);
    $('ape-open-board-editor')?.addEventListener('click', () => window.ArcherToast?.show('Board editor will be connected in the next phase.', 'info'));
    $('ape-open-fen')?.addEventListener('click', () => window.ArcherToast?.show('Set FEN will be connected in the next phase.', 'info'));
    document.querySelectorAll('[data-ape-close]').forEach(btn => btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-ape-close');
      if (target === 'comment') showEl('ape-comment-modal', false);
      if (target === 'import') showEl('ape-import-modal', false);
    }));
    document.querySelectorAll('[data-ape-tab]').forEach(btn => btn.addEventListener('click', () => {
      const target = btn.dataset.apeTab;
      document.querySelectorAll('[data-ape-tab]').forEach(item => item.classList.toggle('active', item === btn));
      document.querySelectorAll('[data-ape-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.apePanel === target));
    }));
    document.querySelectorAll('[data-ape-menu]').forEach(btn => btn.addEventListener('click', () => {
      const action = btn.dataset.apeMenu;
      const path = menuPath ? menuPath.slice() : null;
      closeMoveMenu();
      if (!path) return;
      const editor = currentEditor();
      if (!editor) return;
      editor.currentPath = path;
      syncCurrent(editor);
      if (action === 'comment') {
        document.querySelector('[data-ape-tab="comments"]')?.click();
        $('ape-move-comment')?.focus();
        return;
      }
      if (action === 'delete-move') {
        deleteSelectedMove();
      }
    }));
    document.querySelectorAll('[data-ape-act]').forEach(btn => btn.addEventListener('click', () => {
      const editor = currentEditor();
      if (!editor) return;
      const act = btn.dataset.apeAct;
      if (act === 'start') { editor.currentPath = []; return syncCurrent(editor); }
      if (act === 'prev') { if (editor.currentPath.length) editor.currentPath = editor.currentPath.slice(0, -1); return syncCurrent(editor); }
      if (act === 'next') {
        const node = currentNode(editor) || editor.root;
        if (node.children[0]) editor.currentPath = editor.currentPath.concat([0]);
        return syncCurrent(editor);
      }
      if (act === 'end') {
        let path = []; let node = editor.root;
        while (node.children[0]) { path.push(0); node = node.children[0]; }
        editor.currentPath = path; return syncCurrent(editor);
      }
      if (act === 'flip') return board.flip();
      if (act === 'reset-puzzle') {
        clearSaveErrors();
        closeMoveMenu();
        editor.root = newNode({ fen: editor.startFen || DEFAULT_FEN, side: 'w' });
        editor.currentPath = [];
        editor.answer = '';
        editor.annotation = '';
        editor.undoStack = [];
        editor.redoStack = [];
        editor.branchHint = false;
        board.setPosition(editor.startFen || DEFAULT_FEN);
        board.setOverlay([], []);
        return syncCurrent(editor);
      }
      if (act === 'reset-node') return resetSelectedNode();
    }));
    document.addEventListener('click', (event) => {
      const menu = $('ape-move-menu');
      if (!menu || menu.style.display === 'none') return;
      if (!menu.contains(event.target)) closeMoveMenu();
    });
  }

  function wireKeyboard() {
    document.addEventListener('keydown', (e) => {
      const activeTag = document.activeElement?.tagName;
      const inInput = ['INPUT','TEXTAREA','SELECT'].includes(activeTag);
      const editor = currentEditor();
      if (!editor) return;
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        if (EDITOR_READ_ONLY) return;
        e.preventDefault(); saveCurrentPuzzle(false); return;
      }
      if (inInput) return;
      if (e.key === 'ArrowLeft') { e.preventDefault(); document.querySelector('[data-ape-act="prev"]').click(); }
      if (e.key === 'ArrowRight') { e.preventDefault(); document.querySelector('[data-ape-act="next"]').click(); }
      if (e.key === 'Home') { e.preventDefault(); document.querySelector('[data-ape-act="start"]').click(); }
      if (e.key === 'End') { e.preventDefault(); document.querySelector('[data-ape-act="end"]').click(); }
      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (EDITOR_READ_ONLY) return;
        const idx = editor.currentPath[editor.currentPath.length - 1];
        if (idx > 0) { e.preventDefault(); deleteCurrentVariation(); }
      }
    });
  }

  window.addEventListener('archer:chapter-import-staged', () => {
    ['ape-save-chapter-inline', 'ape-save-chapter-topbar'].forEach((id) => {
      const button = $(id);
      if (button) button.textContent = 'Save Chapter';
    });
  });

  function initBoard() {
    const stage = document.querySelector('.ape-board-stage');
    const boardSize = () => {
      const width = Math.floor(stage?.clientWidth || 640);
      return Math.max(280, Math.min(680, width));
    };

    board = new MiniChess.Board('ape-board', {
      size: boardSize(),
      draggable: !EDITOR_READ_ONLY,
      draw: !EDITOR_READ_ONLY,
      drawColor: activeDrawColor,
      position: DEFAULT_FEN,
      coords: true,
      getLegalMoves(state, rank, file) { return legalMovesForSquare(state, rank, file); },
      onMove: onUserMove,
      onDraw({ arrows, highlights }) {
        if (EDITOR_READ_ONLY) return;
        const editor = currentEditor(); const node = currentNode(editor);
        if (!node) return;
        markChanged(() => {
          node.arrows = clone(arrows || []);
          node.highlights = clone(highlights || []);
        });
      },
    });

    if (stage && typeof ResizeObserver !== 'undefined') {
      const resizeObserver = new ResizeObserver(() => {
        if (!board) return;
        board.size = boardSize();
        board.render();
      });
      resizeObserver.observe(stage);
    }
  }

  initBoard();
  window.addEventListener('resize', () => {
    if (!board) return;
    const stage = document.querySelector('.ape-board-stage');
    const width = Math.floor(stage?.clientWidth || 640);
    board.size = Math.max(280, Math.min(680, width));
    board.render();
  });
  wireFields();
  wireButtons();
  wireKeyboard();
  document.querySelectorAll('#ape-save-btn, #ape-save-header').forEach(btn => btn.addEventListener('click', saveCurrentPuzzle));
  renderPuzzleList();
  if (games.length) loadPuzzle(0);
  else {
    setText('ape-current-title', 'No puzzles found');
    setText('ape-current-sub', 'This chapter has no parseable PGN games.');
  }
})();
</script>
@endpush

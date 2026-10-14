@extends('layouts.app')
@section('page-title','Live Class — '.$liveClass->name)

@section('sidebar-nav') @include('coach._sidebar') @endsection

@section('topbar-actions')
<div class="live-dot"></div>
<span style="font-weight:600;font-size:13px">{{ $liveClass->name }}</span>
<span class="badge badge-student" title="Total students in this batch">{{ $liveClass->group->students->count() }} in batch</span>
<span id="topbar-online" class="badge" style="background:#dcfce7;color:#166534" title="Students online right now">● 0 online</span>
<form method="POST" action="{{ route('coach.class.end', $liveClass) }}" onsubmit="return confirm('End class? Homework will be auto-assigned.')">
  @csrf
  <button class="btn btn-danger btn-sm">End Class</button>
</form>
@endsection

{{-- Shared chess-UI design tokens (board size, classification colours, scoresheet styles). --}}
@include('partials._chess-ui')

@push('styles')
<style>
/* ── Three-column Lichess-style layout ─────────────────────────────── */
.live-layout    { display:grid; grid-template-columns: 280px minmax(420px, 1fr) 360px; gap:14px; align-items:flex-start; }
@media (max-width: 1180px) {
  .live-layout  { grid-template-columns: 240px minmax(360px, 1fr) 320px; gap:10px; }
}
@media (max-width: 980px) {
  .live-layout  { grid-template-columns: 1fr; }
}
.col-left       { display:flex; flex-direction:column; gap:10px; min-width:0; }
.col-center     { display:flex; flex-direction:column; gap:10px; min-width:0; align-items:center; }
.col-right      { display:flex; flex-direction:column; gap:10px; min-width:0; }

/* Tabs (left column) */
.tabs           { width:100%; padding-inline:8px }
.tabs .tab      { flex:1 }
.tab-panel      { display:none }
.tab-panel.active { display:block }

/* Chapters tab */
.ch-list        { max-height:420px; overflow:auto }
.ch-item        { display:flex; gap:8px; align-items:center; padding:7px 9px; border-radius:6px; border:1px solid transparent }
.ch-item:hover  { background:#f1f5f9 }
.ch-item.active { background:#fee2e2; border-color:#fca5a5 }
.ch-item.done   { opacity:.6 }
.ch-item.locked { opacity:.45; cursor:not-allowed }

/* Positions tab — list of position NAMES only (one per [Event] in the PGN) */
.pn-list        { max-height:480px; overflow:auto }
.pn-row         { padding:9px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:13px; color:#334155; line-height:1.35 }
.pn-row:hover   { background:#f1f5f9 }
.pn-row.active  { background:#dbeafe; color:#1e40af; font-weight:700; border-left:3px solid #2563eb; padding-left:9px }
.pn-num         { font-family:ui-monospace, monospace; font-size:11px; color:#64748b; margin-right:6px }
.pn-empty       { color:#94a3b8; font-style:italic; padding:14px 12px; font-size:12px }

/* Right column: compact move list + annotation + sub-variation */
.mv-strip       { font-size:13px; line-height:1.7; color:#0f172a; max-height:160px; overflow:auto; padding:4px 2px }
.mv-strip .mn   { color:#64748b; margin-right:3px; font-family:ui-monospace, monospace; font-size:11px }
.mv-strip .mv   { display:inline-block; padding:1px 5px; border-radius:4px; cursor:pointer; margin-right:3px; }
.mv-strip .mv:hover  { background:#f1f5f9 }
.mv-strip .mv.active { background:#dbeafe; color:#1e40af; font-weight:700 }
.mv-strip .mv-empty  { color:#94a3b8; font-style:italic; font-size:12px; }

/* Recorded variations from PGN '(...)' — clickable inline alternates */
.rec-var        { display:inline-block; padding:0 4px; margin:0 4px; font-style:italic; color:#475569; border-left:2px solid #cbd5e1 }
.rec-var .mv    { font-style:normal; }

.annot-card     { background:#fef9c3; border-color:#fde047 }
.annot-card .card-title { color:#854d0e }
.annot-text     { font-size:13px; color:#713f12; line-height:1.5; white-space:pre-wrap; word-wrap:break-word }
.annot-empty    { color:#94a3b8; font-style:italic; font-size:12px }

/* Live sub-variation (transient, not persisted) */
.subvar-card    { background:#fef3c7; border-color:#fcd34d }
.subvar-card .card-title { color:#92400e; font-size:13px }
.subvar-list    { font-size:13px; line-height:1.7; color:#78350f; padding:2px 0 }
.subvar-list .sv-mv { display:inline-block; background:#fde68a; padding:2px 7px; border-radius:4px; margin:2px 5px 2px 0; font-family:ui-monospace, monospace; cursor:pointer }
.subvar-list .sv-mv:hover { background:#fcd34d }
.subvar-list .sv-mv.active { background:#f59e0b; color:#fff; font-weight:700 }
.subvar-hint    { font-size:11px; color:#92400e; opacity:.8; margin-top:6px }

/* Bottom row */
.bottom-row     { display:grid; grid-template-columns: 2fr 1fr; gap:14px; margin-top:18px; }
@media (max-width: 980px) { .bottom-row { grid-template-columns: 1fr; } }

/* Question modal */
.qm-modal-copy { color:#64748b; font-size:13px; margin-bottom:12px }
.qm-tools   { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px }

/* Students grid (during question) */
.sg-grid    { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:14px; }
.sg-card    { border:1px solid var(--border); border-radius:10px; padding:10px; background:#fff }
.sg-head    { display:flex; align-items:center; gap:8px; margin-bottom:8px }
.sg-board   { width:100% !important; aspect-ratio:1 / 1 }
.sg-stats   { display:flex; gap:6px; margin-top:6px; font-size:11px }
.sg-pt      { background:#dcfce7; color:#166534; padding:2px 6px; border-radius:4px; font-weight:700 }
.sg-wr      { background:#fee2e2; color:#991b1b; padding:2px 6px; border-radius:4px }
.sg-done    { background:#fde68a; color:#92400e; padding:2px 6px; border-radius:4px; font-weight:600 }

/* Leaderboard */
.lb-row     { display:flex; align-items:center; gap:8px; padding:6px 8px; border-bottom:1px solid var(--border); font-size:13px }
.lb-row:last-child { border-bottom:none }
.lb-rank    { width:24px; text-align:center; font-weight:700; color:var(--text3) }
.lb-rank.top{ color:#ca8a04 }

/* Students roster (bottom row) */
.roster     { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:8px }
.roster-card{ display:flex; gap:8px; align-items:center; padding:6px 8px; border:1px solid var(--border); border-radius:8px; background:#fff }

/* Set/Edit position modal */
.editor-card .hint { color:#64748b; font-size:12px; margin-bottom:10px }

/* Position-name header above moves */
.pos-head   { font-size:13px; font-weight:700; color:#1e293b; }
.pos-sub    { font-size:11px; color:#64748b; margin-top:1px }
</style>
@endpush

@section('content')
<div class="live-layout">

  {{-- ── LEFT COLUMN: Chapters / Positions tabs ───────────────────────── --}}
  <div class="col-left">
    <div class="card card-sm" style="padding:0">
      @php
        // Restrict the coach's chapter list to TODAY + PAST only.
        // Today  = the chapter attached to this live class.
        // Past   = chapters from earlier ended classes of this group (revision).
        // Future = hidden — coach must not see what's coming next.
        $todayCh    = $liveClass->chapter;
        $reviewable = collect($pastChapters)
            ->reject(fn ($ch) => $todayCh && (int) $ch->id === (int) $todayCh->id)
            ->values();
        $visibleCount = $reviewable->count() + ($todayCh ? 1 : 0);
      @endphp
      <div class="ui-tabs tabs">
        <button type="button" class="ui-tab tab is-active active" data-tab="chapters" onclick="switchTab('chapters')">
          Chapters <span style="opacity:.6">{{ $visibleCount }}</span>
        </button>
        <button type="button" class="ui-tab tab" data-tab="positions" onclick="switchTab('positions')">Positions <span id="pn-count" style="opacity:.7">0</span></button>
        <button type="button" class="ui-tab tab" data-tab="engine" onclick="switchTab('engine')">Engine</button>
      </div>

      {{-- Chapters panel — TODAY's chapter on top, then PAST chapters for revision.
           Future / locked chapters are intentionally not rendered for the coach. --}}
      <div id="tab-chapters" class="tab-panel active ch-list" style="padding:8px">
        @if($todayCh)
          <div class="ch-item active"
               data-ch-id="{{ $todayCh->id }}"
               onclick="loadChapterById({{ $todayCh->id }}, {!! json_encode($todayCh->name) !!}, {!! json_encode($todayCh->effective_pgn) !!})"
               style="cursor:pointer">
            <div style="flex:1;min-width:0">
              <div style="font-size:12px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                {{ $todayCh->name }}
              </div>
              <div class="text-xs text-muted">Today · {{ ucfirst($todayCh->level) }} · #{{ $todayCh->sequence_number ?? '—' }}</div>
            </div>
          </div>
        @endif
        @if($reviewable->isNotEmpty())
          <div class="text-xs text-muted" style="padding:8px 6px 4px;font-weight:600">
            Past chapters · revision
          </div>
          @foreach($reviewable as $ch)
            <div class="ch-item done"
                 data-ch-id="{{ $ch->id }}"
                 onclick="loadChapterById({{ $ch->id }}, {!! json_encode($ch->name) !!}, {!! json_encode($ch->effective_pgn) !!})"
                 style="cursor:pointer">
              <div style="flex:1;min-width:0">
                <div style="font-size:12px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  {{ $ch->name }}
                </div>
                <div class="text-xs text-muted">{{ ucfirst($ch->level) }} · #{{ $ch->sequence_number ?? '—' }}</div>
              </div>
            </div>
          @endforeach
        @endif
        @if(!$todayCh && $reviewable->isEmpty())
          <div class="empty" style="padding:14px 6px">No chapter for today, and no past chapters yet.</div>
        @endif
      </div>

      {{-- Positions panel — list of position NAMES only (one per [Event] block) --}}
      <div id="tab-positions" class="tab-panel pn-list">
        <div id="positions-list">
          <div class="pn-empty">Click a chapter to load its positions.</div>
        </div>
      </div>

      {{-- Engine panel — Stockfish analysis of the current board --}}
      <div id="tab-engine" class="tab-panel" style="padding:8px">
        @include('partials._engine', ['engHostId' => 'eng', 'getFenName' => 'engineGetFen'])
      </div>
    </div>
  </div>

  {{-- ── CENTER COLUMN: Board ─────────────────────────────────────────── --}}
  <div class="col-center">
    <div id="board"></div>
    <div class="board-controls mt-2">
      <button class="btn btn-sm btn-ghost" onclick="goStart()">⏮</button>
      <button class="btn btn-sm btn-ghost" onclick="goPrev()">◀</button>
      <span id="move-counter" style="font-size:12px;color:var(--text3);padding:0 10px">0/0</span>
      <button class="btn btn-sm btn-ghost" onclick="goNext()">▶</button>
      <button class="btn btn-sm btn-ghost" onclick="goEnd()">⏭</button>
      <button class="btn btn-sm btn-ghost" onclick="flipBoard()">⇅ Flip</button>
      <button class="btn btn-sm btn-ghost" onclick="resetBoard()">↺ Reset</button>
    </div>
    <div class="flex items-center gap-2 mt-2" style="width:480px;max-width:100%">
      <div id="turn-dot" style="width:10px;height:10px;border-radius:50%;background:#fff;border:1.5px solid #888"></div>
      <span id="turn-label" style="font-size:12px;color:var(--text2)">White to move</span>
      <span id="status-label" class="text-xs text-muted" style="margin-left:auto">Click pieces to move · right-click for arrows</span>
    </div>

    {{-- Action row --}}
    <div class="flex gap-2 mt-3" style="flex-wrap:wrap;width:480px;max-width:100%">
      <button class="btn btn-sm" style="background:#f59e0b;color:#fff" onclick="openQuestionModal()">Ask Question</button>
      <button class="btn btn-sm" style="background:#0ea5e9;color:#fff" onclick="openSetPositionModal()">Set Position</button>
      <button class="btn btn-sm" style="background:#8b5cf6;color:#fff" onclick="openEditPositionModal()">Edit Position</button>
      <button class="btn btn-sm btn-ghost" onclick="clearOverlay()">Clear arrows/highlights</button>
    </div>

    <div id="move-feedback" class="text-xs mt-2" style="min-height:18px;color:var(--danger);width:480px;max-width:100%"></div>
  </div>

  {{-- ── RIGHT COLUMN: Position name + Recorded moves + Annotation + Live variation ─ --}}
  <div class="col-right">
    {{-- Active question banner --}}
    <div id="question-banner" class="card card-sm" style="display:none;background:#fef3c7;border-color:#fcd34d">
      <div style="font-size:13px;font-weight:700;color:#92400e">Question is live</div>
      <div id="qb-text" class="text-sm mt-1" style="color:#78350f"></div>
      <div id="qb-meta" class="text-xs mt-1" style="color:#92400e"></div>
      <button class="btn btn-sm btn-danger mt-2" onclick="endQuestion()">End question</button>
    </div>

    <div class="card card-sm">
      <div class="card-title" style="font-size:13px">
        <span id="chapter-title">{{ $liveClass->chapter->name }}</span>
      </div>
      <div class="pos-head" id="position-name">No position selected</div>
      <div class="pos-sub" id="position-sub" style="display:none"></div>
      <hr style="border:none;border-top:1px solid #e2e8f0;margin:10px 0 8px">
      <div id="move-strip" class="mv-strip"><span class="mv-empty">Pick a position from the left.</span></div>
    </div>

    <div class="card card-sm annot-card">
      <div class="card-title" style="font-size:13px">Annotation</div>
      <div id="annot-text" class="annot-text"><span class="annot-empty">No annotation for this position.</span></div>
    </div>

    {{-- Live sub-variation panel (only when coach has played divergent moves) --}}
    <div id="subvar-card" class="card card-sm subvar-card" style="display:none">
      <div class="card-title">Live variation</div>
      <div id="subvar-list" class="subvar-list"></div>
      <div class="subvar-hint">Not saved · click a recorded move above to return.</div>
      <div style="display:flex;gap:6px;margin-top:8px">
        <button class="btn btn-xs btn-ghost" onclick="returnToRecorded()">Back to recorded line</button>
      </div>
    </div>
  </div>
</div>

{{-- ── BOTTOM ROW: Students list + Leaderboard ──────────────────────── --}}
<div class="bottom-row">
  <div class="card card-sm" id="students-card">
    <div class="card-title">
      <span id="students-card-title">Students in class</span>
      <span id="student-count" class="badge badge-student">{{ $liveClass->group->students->count() }}</span>
      <span id="students-hint" class="text-xs text-muted" style="margin-left:8px;display:none">Updates every 2s · students cannot see each other's boards</span>
    </div>

    {{-- Default roster (when no question active) --}}
    <div id="students-roster" class="roster">
      @foreach($liveClass->group->students as $s)
      <div class="roster-card" data-uid="{{ $s->id }}">
        <span class="presence-dot" data-uid="{{ $s->id }}"
              style="width:9px;height:9px;border-radius:50%;background:#ef4444;flex-shrink:0;box-shadow:0 0 0 2px #fff"
              title="Offline"></span>
        <div class="av av-sm" style="background:{{ $s->avatar_color }}">{{ $s->initials }}</div>
        <span style="font-size:13px">{{ $s->name }}</span>
      </div>
      @endforeach
    </div>

    {{-- Live student boards (during question) --}}
    <div id="students-grid" class="sg-grid" style="display:none"></div>
  </div>

  <div class="col-stack" style="display:flex;flex-direction:column;gap:10px">
    {{-- Always-on cumulative leaderboard (every question's points combined) --}}
    @include('partials._class-leaderboard', ['liveClass' => $liveClass, 'role' => 'coach'])

    {{-- Per-question quick scores (only populated while a question is live) --}}
    <div class="card card-sm" id="leaderboard-card" style="display:none">
      <div class="card-title" style="font-size:13px">This question</div>
      <div id="leaderboard-list"></div>
    </div>

    @include('partials._class-chat', ['liveClass' => $liveClass, 'role' => 'coach'])
  </div>
</div>

{{-- Set / Edit position modal (shared host element, swapped in/out) --}}
<x-ui.modal id="editor-modal" max-width="720px">
  <x-slot:header>
    <div class="ui-modal-heading">
      <div class="ui-modal-title" id="editor-title">Set Position</div>
      <x-ui.button type="button" variant="secondary" size="sm" class="ui-button--icon" onclick="closeEditorModal()" aria-label="Close" title="Close">
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
      </x-ui.button>
    </div>
  </x-slot:header>
  <div class="editor-card">
    <div class="hint" id="editor-hint">Click a piece in the palette, then click squares to place. Right-click any square to erase.</div>
    <div id="editor-host"></div>
  </div>
</x-ui.modal>

{{-- Question modal --}}
<x-ui.modal id="question-modal" max-width="720px">
  <x-slot:header>
    <div class="ui-modal-heading">
      <div class="ui-modal-title">Ask a Question</div>
      <x-ui.button type="button" variant="secondary" size="sm" class="ui-button--icon" onclick="closeQuestionModal()" aria-label="Close" title="Close">
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
      </x-ui.button>
    </div>
  </x-slot:header>
    <p class="qm-modal-copy">Play the correct move(s) on the mini-board below. The starting position you broadcast will be the puzzle. Each correct move a student plays = 1 point.</p>

    <div class="qm-tools">
      <button class="btn btn-xs btn-ghost" onclick="qmReset()">Reset Solution</button>
      <button class="btn btn-xs btn-ghost" onclick="qmFlip()">Flip Board</button>
    </div>

    <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
      <div id="qm-board"></div>
      <div style="flex:1;min-width:200px">
        <div style="font-size:12px;font-weight:600;color:#475569">Solution moves</div>
        <div id="qm-moves" class="move-list" style="margin-top:6px;max-height:240px"></div>
        <div style="margin-top:14px;font-size:12px;font-weight:600;color:#475569">Optional prompt to students</div>
        <textarea id="qm-text" rows="2" class="ui-textarea" placeholder="e.g. White to play and win" style="margin-top:6px"></textarea>
      </div>
    </div>
  <x-slot:footer>
    <x-ui.button type="button" variant="secondary" onclick="closeQuestionModal()">Cancel</x-ui.button>
    <x-ui.button type="button" onclick="broadcastQuestion()">Broadcast to Students</x-ui.button>
  </x-slot:footer>
</x-ui.modal>
@endsection

@push('scripts')
@include('partials._chess-engine')
<script>
const ROOM_ID    = '{{ $liveClass->room_id }}';
const CLASS_ID   = {{ $liveClass->id }};
const UPDATE_URL = '{{ route('coach.class.board', $liveClass) }}';
const Q_STORE_URL = '{{ route('coach.class.question.store', $liveClass) }}';
const Q_END_URL   = '{{ route('coach.class.question.end', $liveClass) }}';
const Q_ACTIVE_URL= '{{ route('coach.class.question.active', $liveClass) }}';
const CSRF       = '{{ csrf_token() }}';

const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

// ── State ────────────────────────────────────────────────────────────
// chapter = ONE PGN file. Positions[] = each [Event] block inside it.
let chapterTitle    = '';            // PGN file / chapter name shown top-right
let chapterPositions = [];           // [{name, startFen, mainLine:[], annotation, headers}]
let currentPosIdx   = -1;            // which entry in chapterPositions is loaded
let currentPos      = null;          // alias: chapterPositions[currentPosIdx]

// recordedFens[i] is the FEN AFTER move i. recordedFens[0] = startFen.
let recordedFens    = [STARTING_FEN];
let recordedIdx     = 0;             // index in recordedFens (= 0..mainLine.length)

// liveSubvar[]: divergent moves — render-only, never persisted. Each entry: {san:'?', fen, from, to, branchAt}
// branchAt is the recordedIdx where the divergence began. Cleared when user navigates back.
let liveSubvar      = [];
let activeQuestionId = null;

// ── Board ────────────────────────────────────────────────────────────
const board = new MiniChess.Board('board', {
  size: 480,
  draggable: true,
  draw: true,                  // right-click arrows + highlights
  position: STARTING_FEN,
  onMove: (from, to, fen) => onCoachMove(from, to, fen),
  // Whenever the coach draws / clears an arrow or highlight, push it to students immediately.
  onDraw: () => broadcastMove(),
});

function currentFen() {
  if (liveSubvar.length > 0) return liveSubvar[liveSubvar.length - 1].fen;
  return recordedFens[recordedIdx];
}

function setBoardFromState() {
  const fen = currentFen();
  board.setPosition(fen);
  // setPosition wipes overlay; restore current overlay
  board.setOverlay(board.arrows, board.highlights);
  const state = MiniChess.parseFen(fen);
  document.getElementById('turn-dot').style.background = state.turn === 'w' ? '#fff' : '#222';
  document.getElementById('turn-label').textContent = state.turn === 'w' ? 'White to move' : 'Black to move';
  // Counter shows recorded progress (or sub-var depth when in variation)
  const recTotal = recordedFens.length - 1;
  if (liveSubvar.length > 0) {
    document.getElementById('move-counter').textContent =
      'var ' + liveSubvar.length + (recTotal ? ' (off ' + recordedIdx + '/' + recTotal + ')' : '');
  } else {
    document.getElementById('move-counter').textContent = recordedIdx + '/' + recTotal;
  }
  renderMoveStrip();
  renderAnnotation();
  renderSubvar();
}

// ── Tab switcher ─────────────────────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.tabs .tab').forEach(t => {
    const selected = t.dataset.tab === name;
    t.classList.toggle('active', selected);
    t.classList.toggle('is-active', selected);
  });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  const panel = document.getElementById('tab-' + name);
  if (panel) panel.classList.add('active');
}

// ── Chapter loading ──────────────────────────────────────────────────
// User clicks a chapter (= one PGN file in the curriculum).
function loadChapterById(chId, chName, pgn) {
  chapterTitle = chName || 'Chapter';
  document.getElementById('chapter-title').textContent = chapterTitle;
  // Visual: highlight the active chapter row
  document.querySelectorAll('.ch-item').forEach(el => {
    el.classList.toggle('active', String(el.dataset.chId) === String(chId));
  });
  // Parse multi-game PGN into positions
  const games = MiniChess.parsePGNFile(pgn) || [];
  chapterPositions = games;
  currentPosIdx = -1;
  currentPos = null;
  recordedFens = [STARTING_FEN];
  recordedIdx = 0;
  liveSubvar = [];
  renderPositionsList();
  // Auto-load first position if any
  if (chapterPositions.length > 0) {
    loadPosition(0);
    switchTab('positions');
  } else {
    document.getElementById('position-name').textContent = 'No positions in this chapter';
    document.getElementById('position-sub').style.display = 'none';
    setBoardFromState();
  }
}

// ── Render positions list — NAMES only ───────────────────────────────
function renderPositionsList() {
  const el = document.getElementById('positions-list');
  document.getElementById('pn-count').textContent = chapterPositions.length;
  if (chapterPositions.length === 0) {
    el.innerHTML = '<div class="pn-empty">No positions found in this chapter.</div>';
    return;
  }
  el.innerHTML = '';
  chapterPositions.forEach((pos, i) => {
    const row = document.createElement('div');
    row.className = 'pn-row' + (i === currentPosIdx ? ' active' : '');
    row.dataset.idx = i;
    row.innerHTML = '<span class="pn-num">' + (i + 1) + '.</span>' + escapeHtml(pos.name || ('Position ' + (i + 1)));
    row.addEventListener('click', () => loadPosition(i));
    el.appendChild(row);
  });
}

function setActivePositionRow() {
  document.querySelectorAll('#positions-list .pn-row').forEach(r => {
    r.classList.toggle('active', parseInt(r.dataset.idx, 10) === currentPosIdx);
  });
}

// ── Load a specific position (= one [Event] block) ───────────────────
function loadPosition(idx) {
  if (idx < 0 || idx >= chapterPositions.length) return;
  const pos = chapterPositions[idx];
  currentPosIdx = idx;
  currentPos = pos;

  // Build recordedFens from start + every mainLine entry
  recordedFens = [pos.startFen];
  (pos.mainLine || []).forEach(m => recordedFens.push(m.fen));
  recordedIdx = 0;
  liveSubvar = [];

  // Header
  document.getElementById('position-name').textContent = pos.name || ('Position ' + (idx + 1));
  const sub = (pos.mainLine && pos.mainLine.length)
    ? (pos.mainLine.length + ' recorded move' + (pos.mainLine.length === 1 ? '' : 's'))
    : 'No recorded moves';
  const subEl = document.getElementById('position-sub');
  subEl.textContent = sub;
  subEl.style.display = 'block';

  setActivePositionRow();
  document.getElementById('move-feedback').textContent = '';
  setBoardFromState();
  broadcastMove();
}

// ── Coach move handling ──────────────────────────────────────────────
// If the resulting FEN matches the next recorded fen, advance recordedIdx.
// Otherwise, push to liveSubvar. Sub-vars never persist — they live in JS only.
function onCoachMove(from, to, fen) {
  document.getElementById('move-feedback').textContent = '';

  // Compute Standard Algebraic Notation from the PRE-move state.
  // (Our state hasn't been updated yet, so currentFen() still returns the pre-move FEN.)
  const preFen = currentFen();
  let san;
  try {
    san = MiniChess.moveToSan(MiniChess.parseFen(preFen), from, to, fen);
  } catch (e) {
    san = from + to;
  }

  // If we're already in a sub-variation, every additional move just extends it.
  if (liveSubvar.length > 0) {
    liveSubvar.push({ san: san, fen: fen, from: from, to: to, branchAt: liveSubvar[0].branchAt });
    setBoardFromState();
    broadcastMove();
    return;
  }

  // Are we at the position right before a recorded next move? Compare resulting FEN.
  const nextFen = recordedFens[recordedIdx + 1];
  if (nextFen && fenMatchesPositions(nextFen, fen)) {
    recordedIdx++;
    setBoardFromState();
    broadcastMove();
    return;
  }

  // Diverged. Start a sub-variation rooted at recordedIdx.
  liveSubvar = [{ san: san, fen: fen, from: from, to: to, branchAt: recordedIdx }];
  setBoardFromState();
  broadcastMove();
}

// Compare board state of two FENs (ignore halfmove/fullmove counters which
// can drift on edge cases like castling rights bookkeeping).
function fenMatchesPositions(a, b) {
  if (!a || !b) return false;
  const aP = a.split(' ').slice(0, 4).join(' ');
  const bP = b.split(' ').slice(0, 4).join(' ');
  return aP === bP;
}

// ── Move strip render (recorded line) ────────────────────────────────
function renderMoveStrip() {
  const el = document.getElementById('move-strip');
  if (!el) return;
  if (!currentPos || !currentPos.mainLine || currentPos.mainLine.length === 0) {
    el.innerHTML = '<span class="mv-empty">No recorded moves for this position.</span>';
    return;
  }
  el.innerHTML = '';
  let lastMoveNumber = -1;
  currentPos.mainLine.forEach((node, i) => {
    // Move number prefix (only when changing)
    if (node.side === 'w') {
      if (node.moveNumber !== lastMoveNumber) {
        const mn = document.createElement('span');
        mn.className = 'mn';
        mn.textContent = node.moveNumber + '.';
        el.appendChild(mn);
        lastMoveNumber = node.moveNumber;
      }
    } else if (node.side === 'b') {
      if (node.moveNumber !== lastMoveNumber) {
        const mn = document.createElement('span');
        mn.className = 'mn';
        mn.textContent = node.moveNumber + '...';
        el.appendChild(mn);
        lastMoveNumber = node.moveNumber;
      }
    }
    const mv = document.createElement('span');
    mv.className = 'mv' + ((liveSubvar.length === 0 && (i + 1) === recordedIdx) ? ' active' : '');
    mv.dataset.idx = (i + 1);
    mv.textContent = node.san;
    mv.addEventListener('click', () => jumpToRecorded(i + 1));
    el.appendChild(mv);

    // Inline recorded variations from PGN parens — clickable but read-only.
    if (node.variations && node.variations.length) {
      node.variations.forEach(varLine => {
        const wrap = document.createElement('span');
        wrap.className = 'rec-var';
        let varLastMn = -1;
        varLine.forEach(vNode => {
          if (vNode.moveNumber !== varLastMn) {
            const vmn = document.createElement('span');
            vmn.className = 'mn';
            vmn.textContent = vNode.moveNumber + (vNode.side === 'w' ? '.' : '...');
            wrap.appendChild(vmn);
            varLastMn = vNode.moveNumber;
          }
          const vmv = document.createElement('span');
          vmv.className = 'mv';
          vmv.textContent = vNode.san;
          vmv.title = 'Recorded variation';
          vmv.addEventListener('click', () => loadRecordedVariation(varLine, vNode));
          wrap.appendChild(vmv);
        });
        el.appendChild(wrap);
      });
    }
  });
  const active = el.querySelector('.mv.active');
  if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
}

// Jump straight to a snapshot inside a recorded sub-variation (read-only viewer).
function loadRecordedVariation(varLine, target) {
  // Treat as ad-hoc — show the target FEN and seed liveSubvar so user knows it's not the main line.
  liveSubvar = [];
  // Walk the variation up to target, building entries.
  for (const node of varLine) {
    liveSubvar.push({ san: node.san, fen: node.fen, from: '?', to: '?', branchAt: recordedIdx, recorded: true });
    if (node === target) break;
  }
  setBoardFromState();
  broadcastMove();
}

// ── Annotation render ────────────────────────────────────────────────
function renderAnnotation() {
  const el = document.getElementById('annot-text');
  if (!el) return;
  if (!currentPos) {
    el.innerHTML = '<span class="annot-empty">No position selected.</span>';
    return;
  }
  // If we're parked on a recorded move that has its own comment, show that;
  // otherwise show the position-level annotation.
  let note = '';
  if (liveSubvar.length === 0 && recordedIdx > 0 && currentPos.mainLine[recordedIdx - 1]) {
    note = (currentPos.mainLine[recordedIdx - 1].comment || '').trim();
  }
  if (!note) note = (currentPos.annotation || '').trim();
  if (note) {
    el.textContent = note;
  } else {
    el.innerHTML = '<span class="annot-empty">No annotation for this position.</span>';
  }
}

// ── Live sub-variation render ────────────────────────────────────────
function renderSubvar() {
  const card = document.getElementById('subvar-card');
  const list = document.getElementById('subvar-list');
  if (liveSubvar.length === 0) {
    card.style.display = 'none';
    return;
  }
  card.style.display = 'block';
  list.innerHTML = '';
  // Build "1. e4 e5 2. Nf3 ..." style numbering. We need to know whose turn it
  // was at the branch point, derived from the recorded FEN at branchAt.
  const branchAt = liveSubvar[0].branchAt;
  const branchFen = recordedFens[branchAt] || STARTING_FEN;
  const branchState = MiniChess.parseFen(branchFen);
  let mover    = branchState.turn;          // 'w' or 'b' — the side ABOUT to move
  let moveNum  = branchState.fullMove;
  let needNum  = true;
  liveSubvar.forEach((s, i) => {
    if (mover === 'w' && needNum) {
      const mn = document.createElement('span');
      mn.className = 'mn';
      mn.style.cssText = 'color:#92400e;opacity:.7;font-family:ui-monospace,monospace;font-size:11px;margin-right:3px';
      mn.textContent = moveNum + '.';
      list.appendChild(mn);
      needNum = false;
    } else if (mover === 'b' && needNum) {
      const mn = document.createElement('span');
      mn.className = 'mn';
      mn.style.cssText = 'color:#92400e;opacity:.7;font-family:ui-monospace,monospace;font-size:11px;margin-right:3px';
      mn.textContent = moveNum + '...';
      list.appendChild(mn);
      needNum = false;
    }
    const m = document.createElement('span');
    m.className = 'sv-mv' + (i === liveSubvar.length - 1 ? ' active' : '');
    m.textContent = (s.san && s.san !== '?') ? s.san : (s.from + '-' + s.to);
    m.title = 'Live variation move (not saved)';
    list.appendChild(m);
    if (mover === 'b') { moveNum++; needNum = true; mover = 'w'; }
    else               { mover = 'b'; }
  });
}

function returnToRecorded() {
  // recordedIdx already holds the branch-point — just clear the sub-var.
  liveSubvar = [];
  setBoardFromState();
  broadcastMove();
}

// ── Navigation within recorded line ──────────────────────────────────
function jumpToRecorded(i) {
  if (i < 0 || i >= recordedFens.length) return;
  recordedIdx = i;
  liveSubvar = [];
  document.getElementById('move-feedback').textContent = '';
  setBoardFromState();
  broadcastMove();
}

function goStart() { jumpToRecorded(0); }
function goPrev()  { if (liveSubvar.length > 0) { liveSubvar.pop(); setBoardFromState(); broadcastMove(); }
                    else if (recordedIdx > 0) jumpToRecorded(recordedIdx - 1); }
function goNext()  { if (liveSubvar.length > 0) return; // no recorded "next" while in variation
                    if (recordedIdx < recordedFens.length - 1) jumpToRecorded(recordedIdx + 1); }
function goEnd()   { jumpToRecorded(recordedFens.length - 1); }
function flipBoard() { board.flip(); }
function clearOverlay() { board.clearOverlay(); broadcastMove(); }

function resetBoard() {
  if (currentPosIdx >= 0) {
    loadPosition(currentPosIdx);   // reload current position fresh
  } else {
    recordedFens = [STARTING_FEN];
    recordedIdx = 0;
    liveSubvar = [];
    setBoardFromState();
    broadcastMove();
  }
}

// ── Helpers ──────────────────────────────────────────────────────────
function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// ── Broadcast (server side) ──────────────────────────────────────────
// Coach board → DB. Students poll the DB every ~1 s, so this fetch is
// the single source of truth that drives student boards. Don't swallow
// failures: if a CSRF token expires or the route 5xxs, the coach has
// to know — otherwise their moves silently fail to reach students.
function showBroadcastStatus(text, color) {
  const fb = document.getElementById('move-feedback');
  if (!fb) return;
  fb.style.color = color || 'var(--danger)';
  fb.textContent = text || '';
}

async function broadcastMove() {
  const fen = currentFen();
  // For server logging we report the most-recent move name (recorded or live).
  let moveLabel = '';
  if (liveSubvar.length > 0) {
    const last = liveSubvar[liveSubvar.length - 1];
    moveLabel = last.san || (last.from + '-' + last.to);
  } else if (recordedIdx > 0 && currentPos && currentPos.mainLine[recordedIdx - 1]) {
    moveLabel = currentPos.mainLine[recordedIdx - 1].san;
  }
  try {
    const r = await fetch(UPDATE_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      cache: 'no-store',
      body: JSON.stringify({
        move_index:  recordedIdx,
        board_state: fen,
        move:        moveLabel,
        arrows:      JSON.stringify(board.arrows || []),
        highlights:  JSON.stringify(board.highlights || []),
      }),
    });
    if (!r.ok) {
      // 419 = CSRF expired (page open too long). 403/5xx = role/server problem.
      const hint = r.status === 419
        ? 'Session expired — refresh this page so students stay in sync.'
        : ('Broadcast failed (HTTP ' + r.status + ') — students may be out of sync.');
      showBroadcastStatus(hint, 'var(--danger)');
    }
  } catch (e) {
    showBroadcastStatus('Network error — students did not get this update.', 'var(--danger)');
  }
}

// ── Set / Edit position modal ───────────────────────────────────────────
let activeEditor = null;

function openSetPositionModal() {
  openEditor({ startFen: '8/8/8/8/8/8/8/8 w - - 0 1', title: 'Set Position', hint: 'Empty board — click a piece in the palette, then click a square to place. Use Starting button for the standard setup. The new position will replace the live board (not saved to chapter).' });
}

function openEditPositionModal() {
  const fen = currentFen();
  openEditor({ startFen: fen, title: 'Edit Position', hint: 'The current board position has been loaded. Click a piece in the palette to swap it in; right-click any square to erase. Save to push your edited position to the live board (not saved to chapter).' });
}

function openEditor(opts) {
  document.getElementById('editor-title').textContent = opts.title;
  document.getElementById('editor-hint').textContent  = opts.hint;
  const host = document.getElementById('editor-host');
  host.innerHTML = '';
  document.getElementById('editor-modal').style.display = 'flex';
  activeEditor = new MiniChess.PositionEditor(host, {
    startFen: opts.startFen,
    size: 360,
    onSave: (fen) => { applyEditedPosition(fen); closeEditorModal(); },
    onCancel: () => { closeEditorModal(); },
  });
}

function closeEditorModal() {
  document.getElementById('editor-modal').style.display = 'none';
  document.getElementById('editor-host').innerHTML = '';
  activeEditor = null;
}

// Editor "Save" pushes a one-shot custom position to students. It does NOT
// persist a new chapter / position record — purely live-class transient state.
function applyEditedPosition(fen) {
  // Detach from any chapter position so the user clearly sees this is custom.
  currentPosIdx = -1;
  currentPos = null;
  setActivePositionRow();
  recordedFens = [fen];
  recordedIdx = 0;
  liveSubvar = [];
  document.getElementById('position-name').textContent = 'Custom position';
  document.getElementById('position-sub').style.display = 'none';
  document.getElementById('annot-text').innerHTML = '<span class="annot-empty">Custom position (not saved to chapter).</span>';
  board.clearOverlay();
  setBoardFromState();
  broadcastMove();
  const fb = document.getElementById('move-feedback');
  fb.style.color = 'var(--success)';
  fb.textContent = 'Custom position pushed to all students.';
  setTimeout(() => { fb.textContent = ''; fb.style.color = 'var(--danger)'; }, 1800);
}

// ── Question modal ──────────────────────────────────────────────────────
let qmBoard = null;
let qmStartFen = '';
let qmSolutionMoves = [];

function openQuestionModal() {
  qmStartFen = currentFen();
  qmSolutionMoves = [];
  document.getElementById('qm-text').value = '';
  document.getElementById('question-modal').style.display = 'flex';
  if (qmBoard) {
    qmBoard.setPosition(qmStartFen);
    qmBoard.setOverlay(board.arrows, board.highlights);
    renderQmMoves();
    return;
  }
  qmBoard = new MiniChess.Board('qm-board', {
    size: 320,
    draggable: true,
    position: qmStartFen,
    arrows: board.arrows.slice(),
    highlights: board.highlights.slice(),
    onMove: (from, to, fen) => {
      qmSolutionMoves.push(from + '-' + to);
      renderQmMoves();
    }
  });
  renderQmMoves();
}

function renderQmMoves() {
  const el = document.getElementById('qm-moves');
  if (!qmSolutionMoves.length) {
    el.innerHTML = '<div class="text-xs text-muted">No solution recorded yet — play the correct line on the mini-board.</div>';
    return;
  }
  el.innerHTML = '';
  qmSolutionMoves.forEach((m, i) => {
    const span = document.createElement('span');
    span.className = 'mv';
    span.textContent = (i + 1) + '. ' + m;
    el.appendChild(span);
  });
}

function qmReset() {
  qmSolutionMoves = [];
  qmBoard.setPosition(qmStartFen);
  qmBoard.setOverlay(board.arrows, board.highlights);
  renderQmMoves();
}

function qmFlip() { qmBoard.flip(); }

function closeQuestionModal() {
  document.getElementById('question-modal').style.display = 'none';
}

async function broadcastQuestion() {
  if (qmSolutionMoves.length === 0) {
    alert('Play at least one solution move on the mini-board before broadcasting.');
    return;
  }
  const text = document.getElementById('qm-text').value.trim();
  try {
    const r = await fetch(Q_STORE_URL, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' },
      body: JSON.stringify({
        position_fen:   qmStartFen,
        expected_moves: qmSolutionMoves,
        arrows:         board.arrows || [],
        highlights:     board.highlights || [],
        text:           text || null,
      })
    });
    const data = await r.json();
    if (!data.ok) throw new Error('Broadcast failed');
    activeQuestionId = data.question_id;
    closeQuestionModal();
    enterQuestionMode();
    document.getElementById('qb-text').textContent = text || '(no prompt)';
    document.getElementById('qb-meta').textContent = qmSolutionMoves.length + ' move(s) expected · ' + qmSolutionMoves.join(', ');
    pollActiveQuestion();
  } catch (e) {
    alert('Could not broadcast: ' + e.message);
  }
}

async function endQuestion() {
  if (!activeQuestionId) return;
  if (!confirm('End this question? Students stop scoring.')) return;
  try {
    await fetch(Q_END_URL, { method:'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' } });
  } catch (e) {}
  activeQuestionId = null;
  exitQuestionMode();
}

function enterQuestionMode() {
  document.getElementById('question-banner').style.display = 'block';
  document.getElementById('students-roster').style.display = 'none';
  document.getElementById('students-grid').style.display   = 'grid';
  document.getElementById('students-card-title').textContent = 'Live student boards';
  document.getElementById('students-hint').style.display = 'inline';
  // Reveal the per-question quick-score panel (cumulative leaderboard stays visible above)
  const lc = document.getElementById('leaderboard-card');
  if (lc) lc.style.display = '';
}

function exitQuestionMode() {
  document.getElementById('question-banner').style.display = 'none';
  document.getElementById('students-roster').style.display = 'grid';
  document.getElementById('students-grid').style.display   = 'none';
  document.getElementById('students-card-title').textContent = 'Students in class';
  document.getElementById('students-hint').style.display = 'none';
  // Hide the per-question panel; cumulative leaderboard keeps showing totals.
  const lc = document.getElementById('leaderboard-card');
  if (lc) lc.style.display = 'none';
  const lbEl = document.getElementById('leaderboard-list');
  if (lbEl) lbEl.innerHTML = '';
}

// ── Poll active question + render student boards + leaderboard ─────────
const studentBoards = {};

let activeQuestionPollTimer = null;
let activeQuestionPollInFlight = false;

async function pollActiveQuestion() {
  if (!activeQuestionId) return;
  if (activeQuestionPollTimer) clearTimeout(activeQuestionPollTimer);
  if (activeQuestionPollInFlight) return;
  activeQuestionPollInFlight = true;
  try {
    const r = await fetch(Q_ACTIVE_URL, { headers:{ 'Accept':'application/json' }});
    const data = await r.json();
    if (!data.active) {
      activeQuestionId = null;
      exitQuestionMode();
      return;
    }
    renderStudentsGrid(data.question, data.students);
    renderLeaderboard(data.students);
  } catch (e) {
  } finally {
    activeQuestionPollInFlight = false;
    if (activeQuestionId) activeQuestionPollTimer = setTimeout(pollActiveQuestion, 2000);
  }
}

function renderStudentsGrid(question, students) {
  const grid = document.getElementById('students-grid');
  students.forEach(s => {
    let card = document.getElementById('sg-' + s.user_id);
    if (!card) {
      card = document.createElement('div');
      card.className = 'sg-card';
      card.id = 'sg-' + s.user_id;
      card.innerHTML = `
        <div class="sg-head">
          <div class="av av-sm" style="background:${s.avatar_color}">${s.initials}</div>
          <span style="font-size:13px;font-weight:600;flex:1">${s.name}</span>
        </div>
        <div id="sgb-${s.user_id}" class="sg-board"></div>
        <div class="sg-stats">
          <span class="sg-pt" id="sgpt-${s.user_id}">${s.correct_count} pt</span>
          <span class="sg-wr" id="sgwr-${s.user_id}">${s.wrong_count} miss</span>
          <span id="sgdone-${s.user_id}" class="sg-done" style="${s.completed ? '' : 'display:none'}">Done</span>
        </div>`;
      grid.appendChild(card);
      const w = card.querySelector('.sg-board').clientWidth || 200;
      studentBoards[s.user_id] = new MiniChess.Board('sgb-' + s.user_id, {
        size: w,
        position: s.current_fen,
        arrows: question.arrows,
        highlights: question.highlights,
        coords: false,
      });
    } else {
      studentBoards[s.user_id]?.setPosition(s.current_fen);
      studentBoards[s.user_id]?.setOverlay(question.arrows, question.highlights);
      document.getElementById('sgpt-' + s.user_id).textContent = s.correct_count + ' pt';
      document.getElementById('sgwr-' + s.user_id).textContent = s.wrong_count + ' miss';
      document.getElementById('sgdone-' + s.user_id).style.display = s.completed ? '' : 'none';
    }
  });
}

function renderLeaderboard(students) {
  const el = document.getElementById('leaderboard-list');
  el.innerHTML = '';
  students.forEach((s, i) => {
    const row = document.createElement('div');
    row.className = 'lb-row';
    row.innerHTML = `
      <span class="lb-rank ${i < 3 ? 'top' : ''}">${i+1}</span>
      <div class="av av-sm" style="background:${s.avatar_color};font-size:10px">${s.initials}</div>
      <span style="flex:1">${s.name}</span>
      <span class="sg-pt">${s.correct_count}</span>`;
    el.appendChild(row);
  });
}

async function checkActiveOnEntry() {
  try {
    const r = await fetch(Q_ACTIVE_URL, { headers:{ 'Accept':'application/json' }});
    const data = await r.json();
    if (data.active) {
      activeQuestionId = data.question.id;
      enterQuestionMode();
      document.getElementById('qb-text').textContent = data.question.text || '(no prompt)';
      document.getElementById('qb-meta').textContent = data.question.expected_count + ' move(s) expected';
      pollActiveQuestion();
    }
  } catch (e) {}
}

// Auto-load the class's active chapter PGN
loadChapterById(
  {{ $liveClass->chapter->id }},
  @json($liveClass->chapter->name),
  @json($liveClass->chapter->effective_pgn)
);

function subscribeRealtimeQuestionReview() {
  if (!window.Echo) return;
  window.Echo.channel('class.' + ROOM_ID)
    .listen('.question.changed', payload => {
      if (payload.action === 'attempted' && activeQuestionId) {
        pollActiveQuestion();
      } else if (payload.action === 'ended') {
        activeQuestionId = null;
        exitQuestionMode();
      }
    });
}
if (window.Echo) subscribeRealtimeQuestionReview();
else window.addEventListener('archer:realtime-ready', subscribeRealtimeQuestionReview, { once:true });
checkActiveOnEntry();

document.addEventListener('keydown', e => {
  if (e.key === 'ArrowLeft')  goPrev();
  if (e.key === 'ArrowRight') goNext();
});

// ── Stockfish engine — analyses whatever position the coach is on ────
window.engineGetFen = () => currentFen();
window.MiniChessEngine?.attach({ hostId: 'eng', getFen: window.engineGetFen, depth: 14 });

// ── Presence polling — green dot when student heartbeat is fresh ─────
const ROSTER_URL = @json(route('coach.class.roster', $liveClass));
async function pollRoster() {
  try {
    const r = await fetch(ROSTER_URL, { headers: {'Accept':'application/json'} });
    const data = await r.json();
    document.getElementById('topbar-online').textContent = '● ' + (data.online || 0) + ' online';
    document.querySelectorAll('.presence-dot').forEach(dot => {
      const uid = parseInt(dot.dataset.uid, 10);
      const info = data.students && data.students[uid];
      const live = info && info.online;
      dot.style.background = live ? '#22c55e' : '#ef4444';
      dot.title = live ? 'Online — last seen ' + (info.last_seen_at || '') : 'Offline';
    });
  } catch (e) {}
  setTimeout(pollRoster, 5000);
}
pollRoster();
</script>
@endpush

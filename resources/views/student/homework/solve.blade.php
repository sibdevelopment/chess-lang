@extends('layouts.app')
@section('page-title', 'Solve: '.$homework->title)

@section('sidebar-nav') @include('student._sidebar') @endsection

{{-- Shared chess-UI design tokens (board size, classification colours, scoresheet styles). --}}
@include('partials._chess-ui')

@push('styles')
<style>
.tr-wrap     { display:grid; grid-template-columns: 280px minmax(420px, 1fr) 320px; gap:14px; align-items:flex-start; }
@media (max-width: 980px) { .tr-wrap { grid-template-columns: 1fr; } }
.tr-pn-list  { max-height:520px; overflow:auto }
.tr-pn-row   { padding:9px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:13px; color:#334155; display:flex; gap:8px; align-items:center }
.tr-pn-row:hover { background:#f1f5f9 }
.tr-pn-row.active { background:#dbeafe; color:#1e40af; font-weight:700; border-left:3px solid #2563eb; padding-left:9px }
.tr-pn-num   { font-family:ui-monospace, monospace; font-size:11px; color:#64748b; min-width:24px }
.tr-pn-status{ font-size:11px; min-width:56px; color:var(--color-text-muted) }
.tr-mv-strip { font-size:13px; line-height:1.7; color:#0f172a; padding:4px 2px; min-height:24px }
.tr-mv-strip .mn { color:#64748b; margin-right:3px; font-family:ui-monospace, monospace; font-size:11px }
.tr-mv-strip .mv { display:inline-block; padding:2px 7px; border-radius:4px; margin-right:3px; background:#dcfce7; color:#166534; font-family:ui-monospace, monospace; font-size:12px; font-weight:600 }
.tr-fb       { padding:10px 12px; border-radius:8px; font-size:13px; font-weight:600; min-height:42px; display:flex; align-items:center; gap:8px }
.tr-fb.ok    { background:#dcfce7; color:#166534; border:1px solid #86efac }
.tr-fb.err   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5 }
.tr-fb.info  { background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe }
.tr-fb.done  { background:#fef3c7; color:#78350f; border:1px solid #f59e0b; font-weight:700 }
.tr-prog     { display:flex; align-items:center; gap:8px; font-size:12px; color:#64748b }
.tr-prog-bar { flex:1; height:6px; background:#e2e8f0; border-radius:99px; overflow:hidden }
.tr-prog-bar > div { height:100%; background:var(--color-success); transition:width .3s }
</style>
@endpush

@section('content')
<x-ui.page-header
  :title="$homework->title"
  subtitle="{{ $homework->chapter->name }} · {{ $homework->group->name }}{{ $homework->due_date ? ' · Due '.$homework->due_date->format('d M Y') : '' }}"
>
  <x-slot:actions>
    <div class="flex items-center gap-2 flex-wrap">
    @if($submission->marked_correct !== null)
      <x-ui.badge :status="$submission->marked_correct ? 'completed' : 'cancelled'" :label="$submission->marked_correct ? 'Correct' : 'Wrong'" />
    @endif
      <x-ui.button :href="route('student.homework')" variant="secondary">Back</x-ui.button>
    </div>
  </x-slot:actions>
</x-ui.page-header>

<div class="tr-wrap" id="tr-root">

  {{-- LEFT: position list with per-position completion status --}}
  <div class="card card-sm" style="padding:0">
    <div style="padding:10px 12px;border-bottom:1px solid var(--border);font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px">
      <span>Positions</span>
      <span id="tr-pn-count" class="text-xs text-muted">0</span>
      <span style="margin-left:auto" id="tr-overall" class="text-xs text-muted">0/0 solved</span>
    </div>
    <div id="tr-pn-list" class="tr-pn-list">
      <div class="text-xs text-muted" style="padding:14px 12px">Loading…</div>
    </div>
  </div>

  {{-- CENTER: board --}}
  <div style="display:flex;flex-direction:column;align-items:center;gap:10px">
    <div id="board"></div>
    <div class="tr-prog" style="width:480px;max-width:100%">
      <span id="tr-turn-lbl">White to move</span>
      <div class="tr-prog-bar"><div id="tr-prog-fill" style="width:0%"></div></div>
      <span id="tr-prog-text">0/0</span>
    </div>
    <div class="board-controls" style="width:480px;max-width:100%;justify-content:center">
      <button class="btn btn-sm btn-ghost" onclick="trResetPosition()" title="Restart this position">↺ Restart position</button>
      <button class="btn btn-sm btn-ghost" onclick="trFlip()">⇅ Flip</button>
      @if($submission->status !== 'done')
      <button class="btn btn-sm btn-ghost" onclick="trSkipPosition()" title="Skip this position">↷ Skip</button>
      @endif
    </div>
  </div>

  {{-- RIGHT: prompt + feedback + your moves + submit --}}
  <div style="display:flex;flex-direction:column;gap:10px">
    <div class="card card-sm">
      <div class="card-title" style="font-size:13px">Your Task</div>
      <div id="tr-prompt" style="font-size:13px;color:#1e293b;font-weight:600">Pick a position from the left.</div>
      <div id="tr-prompt-sub" class="text-xs text-muted mt-1"></div>
    </div>

    <div id="tr-fb" class="tr-fb info">Pick a position to begin.</div>

    <div class="card card-sm">
      <div class="card-title" style="font-size:13px">Your moves this position</div>
      <div id="tr-my-moves" class="tr-mv-strip">
        <span class="text-xs text-muted">No moves yet.</span>
      </div>
    </div>

    @if($submission->coach_note)
    <div class="card card-sm" style="background:#f0f0ff;border-color:#c7d2fe">
      <div style="font-size:12px;font-weight:700;color:var(--accent);margin-bottom:4px">Coach Note</div>
      <div class="text-sm">{{ $submission->coach_note }}</div>
    </div>
    @endif

    @if($submission->marked_correct !== null)
    <div class="card card-sm" style="background:{{ $submission->marked_correct ? '#f0fdf4' : '#fef2f2' }};border-color:{{ $submission->marked_correct ? '#86efac' : '#fca5a5' }}">
      <x-ui.badge :variant="$submission->marked_correct ? 'success' : 'danger'">
        {{ $submission->marked_correct ? 'Correct' : 'Needs Practice' }}
      </x-ui.badge>
      <div style="font-weight:700;color:{{ $submission->marked_correct ? 'var(--success)' : 'var(--danger)' }}">
        {{ $submission->marked_correct ? 'Correct! Well done.' : 'Not quite right. Keep practising!' }}
      </div>
    </div>
    @endif

    @if($submission->status !== 'done')
    <form method="POST" action="{{ route('student.homework.submit', $homework) }}" id="submit-form">
      @csrf
      <input type="hidden" name="moves_played" id="moves-played-input">
      <input type="hidden" name="result" id="result-input">
      <button type="button" class="btn btn-primary w-full" onclick="trSubmit()" id="submit-btn">
        Submit to Coach
      </button>
    </form>
    @else
    <div class="card card-sm" style="background:#f0fdf4;border-color:#86efac">
      <div style="font-weight:600;color:var(--success);margin-bottom:4px">Submitted</div>
      <div class="text-xs text-muted">Awaiting coach review.</div>
      @if($submission->submitted_at)
      <div class="text-xs text-muted mt-1">Submitted {{ $submission->submitted_at->diffForHumans() }}</div>
      @endif
    </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
@include('partials._chess-engine')
<script>
const PGN     = @json($homework->chapter->pgn);
const IS_DONE = {{ $submission->status === 'done' ? 'true' : 'false' }};
const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

// Parse the chapter into multi-position study format.
const positions = MiniChess.parsePGNFile(PGN) || [];

// Per-position progress: 'pending' | 'solving' | 'done' | 'skipped'
const progress = positions.map(() => ({ status: 'pending', myMoves: [] }));

let curIdx       = -1;     // which position is loaded
let expectedStep = 0;      // index into positions[curIdx].mainLine that the student must play next
let displayFen   = STARTING_FEN;

const board = new MiniChess.Board('board', {
  size: 480,
  draggable: !IS_DONE,
  position: STARTING_FEN,
  onMove: (from, to, fen) => onStudentMove(from, to, fen),
});

// ── Position list rendering ────────────────────────────────────────
function renderList() {
  const el = document.getElementById('tr-pn-list');
  document.getElementById('tr-pn-count').textContent = positions.length;
  if (!positions.length) {
    el.innerHTML = '<div class="text-xs text-muted" style="padding:14px 12px">No positions in this chapter.</div>';
    return;
  }
  el.innerHTML = '';
  positions.forEach((p, i) => {
    const r = document.createElement('div');
    r.className = 'tr-pn-row' + (i === curIdx ? ' active' : '');
    const statusLabel = progress[i].status === 'done' ? 'Done'
              : progress[i].status === 'solving' ? 'In progress'
              : progress[i].status === 'skipped' ? 'Skipped' : 'Pending';
    r.innerHTML = '<span class="tr-pn-status">' + statusLabel + '</span>'
                + '<span class="tr-pn-num">' + (i + 1) + '.</span>'
                + '<span style="flex:1">' + escHtml(p.name || ('Position ' + (i + 1))) + '</span>';
    r.addEventListener('click', () => loadPosition(i));
    el.appendChild(r);
  });
  const solved = progress.filter(p => p.status === 'done').length;
  document.getElementById('tr-overall').textContent = solved + '/' + positions.length + ' solved';
}

// ── Load a position fresh ──────────────────────────────────────────
function loadPosition(i) {
  if (i < 0 || i >= positions.length) return;
  curIdx = i;
  const p = positions[i];
  expectedStep = 0;
  displayFen = p.startFen || STARTING_FEN;
  if (progress[i].status === 'pending') progress[i].status = 'solving';
  // If already done, leave it alone but still allow review.
  board.setPosition(displayFen);
  setSideLabel();
  setProgressBar();

  document.getElementById('tr-prompt').textContent = p.name || ('Position ' + (i + 1));
  const total = (p.mainLine || []).length;
  document.getElementById('tr-prompt-sub').textContent =
    total ? ('Find the right ' + total + ' move' + (total === 1 ? '' : 's') + ' — they are hidden until you guess them.')
          : 'No recorded moves to find — explore the position.';

  document.getElementById('tr-my-moves').innerHTML = '<span class="text-xs text-muted">No moves yet.</span>';

  if (progress[i].status === 'done') {
    setFb('done', 'Already solved. Try another position from the list.');
  } else if (!total) {
    progress[i].status = 'done';
    setFb('done', 'No moves to solve here; counted as solved.');
  } else {
    setFb('info', '▶ Make your move on the board.');
  }
  renderList();
}

// ── Student plays a move ───────────────────────────────────────────
function onStudentMove(from, to, fen) {
  if (curIdx < 0) return;
  const p = positions[curIdx];

  if (progress[curIdx].status === 'done') {
    // No more moves expected — ignore drag, snap back.
    board.setPosition(displayFen);
    return;
  }

  const expected = (p.mainLine || [])[expectedStep];
  if (!expected) {
    // No more recorded moves — anything is "done".
    progress[curIdx].status = 'done';
    setFb('done', 'Position complete.');
    advanceOrCongrats();
    return;
  }

  // Compute the SAN for what the student played (from PRE-move state).
  let playedSan;
  try {
    playedSan = MiniChess.moveToSan(MiniChess.parseFen(displayFen), from, to, fen);
  } catch (e) { playedSan = from + to; }

  // Record this attempt for submission tracking (right or wrong).
  progress[curIdx].myMoves.push(playedSan);

  if (fenMatches(fen, expected.fen)) {
    // Correct
    expectedStep++;
    displayFen = fen;
    setFb('ok', 'Correct: ' + expected.san);
    setSideLabel();
    setProgressBar();
    renderMyMoves();

    // If there's an opponent reply, auto-play it.
    const reply = (p.mainLine || [])[expectedStep];
    const studentSide = MiniChess.parseFen(p.startFen || STARTING_FEN).turn;
    if (reply && reply.side !== studentSide) {
      // Opponent reply — animate after a short delay.
      setTimeout(() => {
        displayFen = reply.fen;
        board.setPosition(displayFen);
        progress[curIdx].myMoves.push('(' + reply.san + ')');   // mark as opponent
        renderMyMoves();
        expectedStep++;
        setSideLabel();
        setProgressBar();
        afterStep();
      }, 600);
    } else {
      afterStep();
    }
  } else {
    // Wrong — revert the board and tell the student.
    setFb('err', 'Not quite; try again.');
    setTimeout(() => {
      board.setPosition(displayFen);
    }, 500);
  }
}

function afterStep() {
  const p = positions[curIdx];
  if (expectedStep >= (p.mainLine || []).length) {
    progress[curIdx].status = 'done';
    setFb('done', 'Position solved.');
    setProgressBar();
    renderList();
    advanceOrCongrats();
  } else {
    setFb('info', '▶ Make your move on the board.');
  }
}

function advanceOrCongrats() {
  renderList();
  // Next unsolved
  const next = progress.findIndex(p => p.status !== 'done' && p.status !== 'skipped');
  if (next === -1) {
    setFb('done', 'Congratulations — you finished every position.');
  } else if (next !== curIdx) {
    setTimeout(() => loadPosition(next), 1200);
  }
}

// FEN comparison ignoring halfmove/fullmove counters
function fenMatches(a, b) {
  if (!a || !b) return false;
  return a.split(' ').slice(0, 4).join(' ') === b.split(' ').slice(0, 4).join(' ');
}

// ── UI helpers ─────────────────────────────────────────────────────
function setSideLabel() {
  try {
    const t = MiniChess.parseFen(displayFen).turn;
    document.getElementById('tr-turn-lbl').textContent = t === 'w' ? 'White to move' : 'Black to move';
  } catch (e) {}
}

function setProgressBar() {
  const p = positions[curIdx];
  const total = p ? (p.mainLine || []).length : 0;
  const pct   = total ? Math.round((expectedStep / total) * 100) : 0;
  document.getElementById('tr-prog-fill').style.width = pct + '%';
  document.getElementById('tr-prog-text').textContent = expectedStep + '/' + total;
}

function setFb(kind, text) {
  const el = document.getElementById('tr-fb');
  el.className = 'tr-fb ' + kind;
  el.textContent = text;
}

function renderMyMoves() {
  const el = document.getElementById('tr-my-moves');
  const moves = progress[curIdx]?.myMoves || [];
  if (!moves.length) { el.innerHTML = '<span class="text-xs text-muted">No moves yet.</span>'; return; }
  el.innerHTML = moves.map(m => '<span class="mv">' + escHtml(m) + '</span>').join(' ');
}

function escHtml(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function trResetPosition() {
  if (curIdx < 0) return;
  progress[curIdx].myMoves = [];
  progress[curIdx].status = 'solving';
  loadPosition(curIdx);
}
function trFlip() { board.flip(); }
function trSkipPosition() {
  if (curIdx < 0) return;
  progress[curIdx].status = 'skipped';
  setFb('info', 'Position skipped.');
  advanceOrCongrats();
}

function trSubmit() {
  // Concatenate every position's myMoves with a separator for the coach view.
  const parts = [];
  progress.forEach((pg, i) => {
    if (!pg.myMoves.length) return;
    parts.push('[' + (positions[i].name || ('Position ' + (i + 1))) + '] ' + pg.myMoves.join(' '));
  });
  document.getElementById('moves-played-input').value = parts.join('  ||  ');
  const solved = progress.filter(p => p.status === 'done').length;
  document.getElementById('result-input').value = solved + '/' + positions.length + ' solved';
  document.getElementById('submit-form').submit();
}

// Boot
renderList();
if (positions.length) loadPosition(0);
else setFb('info', 'No positions in this chapter.');
</script>
@endpush

@extends('layouts.app')
@section('page-title','Live Class')

@section('sidebar-nav') @include('student._sidebar') @endsection

{{-- Shared chess-UI design tokens (board size, classification colours, scoresheet styles). --}}
@include('partials._chess-ui')

@push('styles')
<style>
.rev-pgn       { border:1px solid var(--border); border-radius:8px; margin-bottom:6px; overflow:hidden; }
.rev-pgn-head  { display:flex; align-items:center; gap:8px; padding:8px 10px; cursor:pointer; background:var(--surface2); font-size:12px; }
.rev-pgn-head:hover { background:#eef2ff; }
.rev-pgn-body  { display:none; padding:6px 8px; }
.rev-pgn.open .rev-pgn-body { display:block; }
.rev-pos       { display:flex; align-items:center; gap:8px; padding:5px 8px; border-radius:5px; cursor:pointer; font-size:12px; }
.rev-pos:hover { background:#e0e7ff; }
.rev-pos.active{ background:#4f46e5; color:#fff; font-weight:600; }

.student-board-frame { position:relative; width:fit-content; }
.student-watch-shield {
  position:absolute;
  inset:0;
  z-index:6;
  cursor:not-allowed;
  background:transparent;
  border-radius:8px;
}
.student-question-stage {
  display:none;
  position:fixed;
  inset:0;
  z-index:3000;
  overflow:auto;
  padding:24px;
  background:rgba(15,23,42,.88);
  backdrop-filter:blur(5px);
  align-items:center;
  justify-content:center;
}
.student-question-stage.active { display:flex; }
.student-question-panel {
  width:min(1040px, 100%);
  max-height:calc(100vh - 48px);
  overflow:auto;
  padding:18px;
  border:1px solid rgba(255,255,255,.3);
  border-radius:18px;
  background:#f8fafc;
  box-shadow:0 28px 80px rgba(15,23,42,.42);
}
.student-question-head {
  display:flex;
  align-items:center;
  gap:12px;
  margin-bottom:16px;
  padding:12px 14px;
  border-radius:12px;
  color:#fff;
  background:linear-gradient(135deg,#4f46e5,#7c3aed);
}
.student-question-pulse {
  width:10px;
  height:10px;
  flex:0 0 auto;
  border-radius:50%;
  background:#fef08a;
  box-shadow:0 0 0 6px rgba(254,240,138,.18);
}
.student-question-grid {
  display:grid;
  grid-template-columns:minmax(460px, 1fr) minmax(300px, 390px);
  gap:18px;
  align-items:start;
}
.student-question-board-pane,
.student-question-side { min-width:0; }
.student-question-board-pane {
  display:flex;
  justify-content:center;
  padding:14px;
  border:1px solid #e2e8f0;
  border-radius:14px;
  background:#fff;
}
.student-question-side {
  display:flex;
  flex-direction:column;
  gap:12px;
}
body.student-question-open { overflow:hidden; }

@media (max-width:900px) {
  .student-question-stage { padding:10px; align-items:flex-start; }
  .student-question-panel { max-height:none; padding:12px; border-radius:14px; }
  .student-question-grid { grid-template-columns:1fr; }
  .student-question-board-pane { overflow:auto; justify-content:flex-start; }
}
</style>
@endpush

@section('content')
<div class="live-banner">
  <span class="live-dot"></span>
  <div style="flex:1">
    <div style="font-weight:700;font-size:15px">{{ $liveClass->name }}</div>
    <div style="font-size:12px;opacity:.8">Coach: {{ $liveClass->coach->name }} · Chapter: {{ $liveClass->chapter->name }}</div>
  </div>
  <span id="sync-badge" class="badge" style="background:rgba(255,255,255,.2);color:#fff">● Syncing</span>
</div>

<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start">
  {{-- Main board --}}
  <div id="live-board-slot">
    <div id="student-board-shell">
      <div class="student-board-frame">
        <div id="board"></div>
        <div id="watch-board-shield" class="student-watch-shield" aria-label="Watch-only board" title="Watch-only: the coach controls this board"></div>
      </div>
      <div class="flex items-center gap-2 mt-2">
        <div id="turn-dot" style="width:10px;height:10px;border-radius:50%;background:#fff;border:1.5px solid #888"></div>
        <span id="turn-label" style="font-size:12px;color:var(--text2)">White to move</span>
        <span id="move-info" class="text-xs text-muted" style="margin-left:auto">Move 0</span>
      </div>

      <div id="revision-bar" style="display:none;margin-top:10px;padding:8px 10px;background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;font-size:12px">
        You are reviewing a past position — the live board is paused.
        <button class="btn btn-xs btn-primary" style="margin-left:6px" onclick="resumeLive()">Return to live</button>
      </div>
    </div>
  </div>

  {{-- Side column --}}
  <div style="flex:1;min-width:260px;display:flex;flex-direction:column;gap:12px;max-width:380px">

    <div id="watching-card" class="card card-sm" style="background:#eff6ff;border-color:#bfdbfe">
      <div style="font-size:13px;font-weight:700;color:#1e40af">You are watching live</div>
      <div class="text-xs mt-1" style="color:#475569">Watch-only mode · moves and arrows are controlled by your coach.</div>
    </div>

    {{-- Current chapter info (no moves shown) --}}
    <div class="card card-sm">
      <div class="card-title" style="font-size:13px">Current chapter</div>
      <div class="flex items-center gap-2">
        <span class="badge badge-{{ $liveClass->chapter->level }}">#{{ $liveClass->chapter->sequence_number ?? '—' }}</span>
        <span style="flex:1;font-weight:600;font-size:13px">{{ $liveClass->chapter->name }}</span>
      </div>
    </div>

    <div class="card card-sm" id="ended-card" style="display:none;background:#f0fdf4;border-color:#86efac">
      <div style="font-weight:700;color:#166534">Class ended</div>
      <a href="{{ route('student.homework') }}" class="btn btn-success btn-sm mt-2">Go to Homework</a>
    </div>

    {{-- Cumulative leaderboard — same totals the coach sees, updated live. --}}
    <div id="leaderboard-home">
      @include('partials._class-leaderboard', ['liveClass' => $liveClass, 'role' => 'student'])
    </div>

    @include('partials._class-chat', ['liveClass' => $liveClass, 'role' => 'student'])
  </div>
</div>

{{-- A coach question takes over the student workspace so it cannot be missed. --}}
<div id="question-stage" class="student-question-stage" role="dialog" aria-modal="true" aria-labelledby="question-stage-title" aria-hidden="true">
  <div class="student-question-panel">
    <div class="student-question-head">
      <span class="student-question-pulse" aria-hidden="true"></span>
      <div>
        <div id="question-stage-title" style="font-weight:800;font-size:17px">Live question from your coach</div>
        <div style="font-size:12px;opacity:.86">Solve on the board. Every correct move adds 1 point.</div>
      </div>
      <span class="badge" style="margin-left:auto;background:rgba(255,255,255,.18);color:#fff">LIVE</span>
    </div>

    <div class="student-question-grid">
      <div id="question-board-slot" class="student-question-board-pane"></div>
      <div class="student-question-side">
        <div id="question-card" class="card card-sm" style="display:none;background:#fef3c7;border-color:#fcd34d">
          <div style="font-size:13px;font-weight:700;color:#92400e">Question from your coach</div>
          <div id="question-text" class="text-sm mt-1" style="color:#78350f"></div>
          <div id="question-progress" class="text-xs text-muted mt-2"></div>
          <div id="question-feedback" class="text-xs mt-1" style="min-height:16px"></div>
        </div>
        <div id="question-leaderboard-slot"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@include('partials._chess-engine')
<script>
const ROOM_ID  = '{{ $liveClass->room_id }}';
const POLL_URL = '{{ route('student.class.poll', $liveClass) }}';
const Q_ACTIVE_URL = '{{ route('student.class.question.active', $liveClass) }}';
const Q_MOVE_URL_TPL = '{{ url('/student/classes/'.$liveClass->id.'/question') }}'; // append /{id}/move
const CSRF = '{{ csrf_token() }}';

const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
let currentFen    = '{{ $liveClass->board_state ?: 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1' }}';
let currentMoveIdx = {{ $liveClass->current_move_index }};
let inRevision    = false;

// Question / puzzle state
let activeQuestionId = null;
let inPuzzle         = false;       // true when a question is live and student is solving
let puzzleStartFen   = null;
let puzzleCurrentFen = null;
let lastFeedbackAt   = 0;

const board = new MiniChess.Board('board', {
  size: 440,
  position: currentFen,
  draggable: false,
  onMove: handleStudentMove,
});

function setBoardFen(fen) {
  board.setPosition(fen);
  try {
    const state = MiniChess.parseFen(fen);
    const turn = state.turn;
    document.getElementById('turn-dot').style.background = turn === 'w' ? '#fff' : '#222';
    document.getElementById('turn-label').textContent = turn === 'w' ? 'White to move' : 'Black to move';
  } catch (e) {}
}
setBoardFen(currentFen);

// ── Overlay state mirrored from the coach board ─────────────────
let currentArrows     = [];
let currentHighlights = [];
let lastOverlayKey    = '';

function safeParseArr(v) {
  if (!v) return [];
  if (Array.isArray(v)) return v;
  try { const p = JSON.parse(v); return Array.isArray(p) ? p : []; } catch (e) { return []; }
}

function applyCoachOverlay(arrowsRaw, highlightsRaw) {
  // Skip while puzzle/revision so we don't fight the student's own work
  if (inRevision || inPuzzle) return;
  const arrows     = safeParseArr(arrowsRaw);
  const highlights = safeParseArr(highlightsRaw);
  // Only re-render the board when something actually changed
  const key = JSON.stringify(arrows) + '|' + JSON.stringify(highlights);
  if (key === lastOverlayKey) return;
  lastOverlayKey    = key;
  currentArrows     = arrows;
  currentHighlights = highlights;
  board.setOverlay(arrows, highlights);
}

// ── Poll for coach moves ─────────────────────────────────────────
let lastMoveIdx = -1;
let lastUpdatedAt = '';
let pollErrors = 0;
let lastSyncAt  = null;

function fmtTime(d) {
  const pad = n => String(n).padStart(2, '0');
  return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
}

function setSyncBadge(label) {
  const el = document.getElementById('sync-badge');
  if (!el) return;
  el.textContent = label;
}

async function poll() {
  try {
    // Cache-bust URL so no proxy / browser ever serves a stale board.
    const url = POLL_URL + (POLL_URL.includes('?') ? '&' : '?') + '_=' + Date.now();
    const r = await fetch(url, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    if (!r.ok) {
      pollErrors++;
      setSyncBadge('● Reconnecting…');
      throw new Error('Poll failed');
    }
    pollErrors = 0;
    lastSyncAt = new Date();
    const data = await r.json();

    if (data.status === 'ended' || data.status === 'completed') {
      document.getElementById('ended-card').style.display = 'block';
      setSyncBadge('● Class ended');
      return;  // stop polling — class is over
    }

    // Apply board position when it changes (FEN, move index, or row updated_at)
    const rowChanged = data.updated_at && data.updated_at !== lastUpdatedAt;
    lastUpdatedAt = data.updated_at || lastUpdatedAt;

    if (rowChanged || data.move_index !== lastMoveIdx || data.board_state !== currentFen) {
      lastMoveIdx    = data.move_index;
      currentMoveIdx = data.move_index;
      currentFen     = data.board_state || START_FEN;
      document.getElementById('move-info').textContent = 'Move ' + currentMoveIdx;
      // While solving a puzzle, do NOT overwrite the student's puzzle board
      if (!inRevision && !inPuzzle) {
        setBoardFen(currentFen);
        lastOverlayKey = '';   // setPosition wipes overlay — force re-apply
      }
    }

    // Always apply the latest overlay (arrows / highlights) — even when the FEN didn't change
    applyCoachOverlay(data.arrows, data.highlights);

    setSyncBadge(inRevision
      ? '● Paused (revision)'
      : (inPuzzle ? '● Solving puzzle' : '● Live'));
  } catch (e) {
    pollErrors++;
  }
  // 1 s while healthy; back off to 4 s only after sustained failures.
  setTimeout(poll, pollErrors > 3 ? 4000 : 1000);
}

function subscribeRealtimeClass() {
  if (!window.Echo) return;
  window.Echo.channel('class.' + ROOM_ID)
    .listen('.board.moved', data => {
      const fenChanged = (data.move_index !== currentMoveIdx) || (data.board_state !== currentFen);
      currentMoveIdx = data.move_index;
      currentFen = data.board_state || START_FEN;
      document.getElementById('move-info').textContent = 'Move ' + currentMoveIdx;
      if (!inRevision && !inPuzzle && fenChanged) {
        setBoardFen(currentFen);
        lastOverlayKey = '';
      }
      applyCoachOverlay(data.arrows, data.highlights);
      document.getElementById('sync-badge').textContent = inRevision
        ? '● Paused (revision)'
        : (inPuzzle ? '● Solving puzzle' : '● Instant sync');
    })
    .listen('.class.ended', () => {
      document.getElementById('ended-card').style.display = 'block';
      document.getElementById('sync-badge').textContent = '● Ended';
    })
    .listen('.question.changed', () => {
      pollQuestion();
    });
}
if (window.Echo) subscribeRealtimeClass();
else window.addEventListener('archer:realtime-ready', subscribeRealtimeClass, { once:true });
// Always poll the live coach board too — Echo is optional.
poll();

function resumeLive() {
  inRevision = false;
  document.getElementById('revision-bar').style.display = 'none';
  setBoardFen(currentFen);
  document.getElementById('sync-badge').textContent = inPuzzle ? '● Solving puzzle' : '● Live';
}

// ── Question / puzzle flow ─────────────────────────────────────
function showQuestionCard(show) {
  document.getElementById('question-card').style.display = show ? 'block' : 'none';
}

function setPuzzleMode(on) {
  inPuzzle = on;
  const boardShell = document.getElementById('student-board-shell');
  const leaderboard = document.getElementById('lb-root');
  const shield = document.getElementById('watch-board-shield');
  const watchingCard = document.getElementById('watching-card');
  const questionStage = document.getElementById('question-stage');

  // The live board is strictly watch-only. It becomes interactive only while
  // the coach has an active question, when it moves into the question layer.
  board.opts.draggable = on;
  shield.style.display = on ? 'none' : 'block';
  watchingCard.style.display = on ? 'none' : '';

  if (on) {
    document.getElementById('question-board-slot').appendChild(boardShell);
    if (leaderboard) document.getElementById('question-leaderboard-slot').appendChild(leaderboard);
    questionStage.classList.add('active');
    questionStage.setAttribute('aria-hidden', 'false');
    document.body.classList.add('student-question-open');
  } else {
    document.getElementById('live-board-slot').appendChild(boardShell);
    if (leaderboard) document.getElementById('leaderboard-home').appendChild(leaderboard);
    questionStage.classList.remove('active');
    questionStage.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('student-question-open');
  }

  // when leaving puzzle, clear overlay and restore the live coach board
  if (!on) {
    activeQuestionId = null;
    puzzleStartFen   = null;
    puzzleCurrentFen = null;
    board.clearOverlay();
    if (!inRevision) {
      setBoardFen(currentFen);
      // Re-apply whatever the coach currently has drawn (arrows + highlights)
      lastOverlayKey = '';
      applyCoachOverlay(currentArrows, currentHighlights);
    }
    document.getElementById('sync-badge').textContent = '● Live';
  } else {
    document.getElementById('sync-badge').textContent = '● Solving puzzle';
  }
}

function setQuestionFeedback(html, color) {
  const el = document.getElementById('question-feedback');
  el.style.color = color || '#78350f';
  el.innerHTML = html || '';
  lastFeedbackAt = Date.now();
}

function setQuestionProgress(correct, expected, wrong, completed) {
  const el = document.getElementById('question-progress');
  const exp = expected || 0;
  let html = '<b>Score:</b> ' + correct + ' / ' + exp + ' correct';
  if (wrong) html += ' &nbsp;·&nbsp; ' + wrong + ' wrong';
  if (completed) html += ' &nbsp;<span style="color:#15803d;font-weight:700">Solved</span>';
  el.innerHTML = html;
}

async function handleStudentMove(from, to, fen) {
  if (!inPuzzle || !activeQuestionId) return;
  // Optimistically update — server responds with truth
  puzzleCurrentFen = fen;
  try {
    const url = Q_MOVE_URL_TPL + '/' + activeQuestionId + '/move';
    const r = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
      },
      body: JSON.stringify({ from: from, to: to, resulting_fen: fen }),
    });
    if (!r.ok) {
      setQuestionFeedback('Move could not be sent (' + r.status + ')', '#b91c1c');
      // Revert board
      board.setPosition(puzzleStartFen);
      return;
    }
    const data = await r.json();
    setQuestionProgress(data.correct_count, data.expected_count, data.wrong_count, data.completed);
    if (data.correct) {
      setQuestionFeedback('Correct! +1 point', '#15803d');
      // Use the server's authoritative next_fen
      if (data.next_fen) {
        puzzleCurrentFen = data.next_fen;
        board.setPosition(data.next_fen);
      }
      if (data.completed) {
        setQuestionFeedback('Puzzle solved! Wait for the next question.', '#15803d');
      }
    } else {
      setQuestionFeedback('Not the right move — try again', '#b91c1c');
      // Reset board to the current correct position so they can try again
      if (data.next_fen) {
        board.setPosition(data.next_fen);
      } else if (puzzleStartFen) {
        board.setPosition(puzzleStartFen);
      }
    }
  } catch (e) {
    setQuestionFeedback('Network error — please try again', '#b91c1c');
    if (puzzleStartFen) board.setPosition(puzzleStartFen);
  }
}

let questionPollTimer = null;
let questionPollInFlight = false;

async function pollQuestion() {
  if (questionPollTimer) clearTimeout(questionPollTimer);
  if (questionPollInFlight) return;
  questionPollInFlight = true;
  try {
    // Cache-bust so a stale 'active=false' response is never served from cache
    // when the coach has just asked a question — that's the difference between
    // "live" and "needs a refresh".
    const url = Q_ACTIVE_URL + (Q_ACTIVE_URL.includes('?') ? '&' : '?') + '_=' + Date.now();
    const r = await fetch(url, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    const data = await r.json();

    if (!data.active) {
      if (inPuzzle) {
        // Question just ended
        setQuestionFeedback('Question closed by your coach.', '#78350f');
        setTimeout(() => { showQuestionCard(false); setPuzzleMode(false); }, 1500);
      }
      // IMPORTANT: do NOT return here. Falling through reaches the
      // setTimeout(pollQuestion, …) below so the poller keeps running
      // and picks up the NEXT question the coach asks. (Earlier bug:
      // an early return killed the poller and forced students to
      // refresh the page to see questions.)
    } else if (activeQuestionId !== data.question_id) {
      // New question — set up puzzle mode
      activeQuestionId = data.question_id;
      puzzleStartFen   = data.position_fen;
      puzzleCurrentFen = data.current_fen || data.position_fen;
      showQuestionCard(true);
      document.getElementById('question-text').textContent = data.text || 'Find the best moves.';
      setQuestionProgress(data.correct_count || 0, data.expected_count || 0, data.wrong_count || 0, !!data.completed);
      setQuestionFeedback(data.completed
        ? 'You already solved this puzzle.'
        : 'Make your move on the board.',
        data.completed ? '#15803d' : '#78350f');
      setPuzzleMode(true);
      board.setPosition(puzzleCurrentFen);
      board.setOverlay(data.arrows || [], data.highlights || []);
    } else {
      // Same question — only refresh score from server (don't touch the board mid-thought)
      setQuestionProgress(data.correct_count || 0, data.expected_count || 0, data.wrong_count || 0, !!data.completed);
    }
  } catch (e) {
  } finally {
    questionPollInFlight = false;
    // Always keep a fallback in case the WebSocket server is unavailable.
    questionPollTimer = setTimeout(pollQuestion, 1000);
  }
}

pollQuestion();

// ── Presence heartbeat — every 15s while this page is open ─────────────
const HEARTBEAT_URL = @json(route('student.class.heartbeat', $liveClass));
async function heartbeat() {
  try {
    await fetch(HEARTBEAT_URL, {
      method:'POST',
      headers:{'X-CSRF-TOKEN': CSRF, 'Accept':'application/json'},
    });
  } catch (e) {}
  setTimeout(heartbeat, 15000);
}
heartbeat();
</script>
@endpush

@extends('layouts.app')
@section('page-title','Homework Report')

@section('sidebar-nav') @include('coach._sidebar') @endsection

{{-- Shared chess-UI design tokens (board size, classification colours, scoresheet styles). --}}
@include('partials._chess-ui')

@push('styles')
<style>
.hw-sub      { border:1px solid var(--border); border-radius:10px; padding:14px; margin-bottom:14px; background:#fff }
.hw-sub-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; gap:10px; flex-wrap:wrap }
.hw-replay   { display:grid; grid-template-columns: minmax(260px, 320px) 1fr; gap:14px; align-items:flex-start; margin-top:10px }
@media (max-width: 720px) { .hw-replay { grid-template-columns: 1fr; } }
.hw-board    { width:300px; max-width:100%; aspect-ratio:1/1 }
.hw-controls { display:flex; gap:6px; margin-top:8px; align-items:center }
.hw-counter  { font-size:11px; color:#64748b; font-family:ui-monospace, monospace }
.hw-mvlist   { font-size:13px; line-height:1.7; color:#0f172a; max-height:160px; overflow:auto; padding:6px 2px }
.hw-mvlist .mn { color:#64748b; margin-right:3px; font-family:ui-monospace, monospace; font-size:11px }
.hw-mvlist .mv { display:inline-block; padding:2px 7px; border-radius:4px; cursor:pointer; margin-right:4px; background:#f1f5f9; font-family:ui-monospace, monospace; font-size:12px }
.hw-mvlist .mv.active { background:#2563eb; color:#fff; font-weight:700 }
.hw-mvlist .mv.bad    { background:#fee2e2; color:#991b1b }
.hw-empty    { color:#94a3b8; font-style:italic; font-size:12px; padding:8px 0 }
.hw-side h4  { font-size:13px; font-weight:600; color:#64748b; margin:8px 0 4px }
</style>
@endpush

@section('content')
<x-ui.page-header
  :title="$homework->title"
  subtitle="{{ $homework->group->name }} · {{ $homework->chapter->name }}{{ $homework->due_date ? ' · Due '.$homework->due_date->format('d M Y') : '' }}"
>
  <x-slot:actions><x-ui.button :href="route('coach.homework')" variant="secondary">Back</x-ui.button></x-slot:actions>
</x-ui.page-header>

<form method="GET" class="ui-filter-bar">
  <div class="ui-filter-bar__main">
    <x-ui.select label="Student" name="student_id" onchange="this.form.submit()">
      <option value="">All students</option>
      @foreach($studentOptions as $student)
        <option value="{{ $student->id }}" {{ $selectedStudentId === (int) $student->id ? 'selected' : '' }}>
          {{ $student->name }} ({{ $student->username }})
        </option>
      @endforeach
    </x-ui.select>
    @if($selectedStudentId)
      <x-ui.button :href="route('coach.homework.show', $homework)" variant="secondary">Reset</x-ui.button>
    @endif
  </div>
</form>

<div class="stats mb-4">
  <div class="stat"><div class="stat-label">Total students</div><div class="stat-val">{{ $stats['total'] }}</div></div>
  <div class="stat"><div class="stat-label">Solved</div><div class="stat-val" style="color:var(--success)">{{ $stats['done'] }}</div></div>
  <div class="stat stat-accent"><div class="stat-label">Completion</div><div class="stat-val">{{ $stats['completion_percent'] }}%</div></div>
  <div class="stat"><div class="stat-label">Coach checked</div><div class="stat-val" style="color:var(--info)">{{ $stats['reviewed'] }}</div></div>
  <div class="stat"><div class="stat-label">Awaiting review</div><div class="stat-val" style="color:var(--warning)">{{ $stats['awaiting_review'] }}</div></div>
  <div class="stat"><div class="stat-label">Feedback given</div><div class="stat-val">{{ $stats['feedback_count'] }}</div></div>
</div>

<div class="card">
  <div class="card-title">Student submissions</div>

  @forelse($visibleStudents as $student)
    @php $sub = $homework->submissions->firstWhere('user_id', $student->id); @endphp
    <div class="hw-sub">
      <div class="hw-sub-head">
        <div class="flex items-center gap-2">
          <div class="av" style="background:{{ $student->avatar_color }}">{{ $student->initials }}</div>
          <div>
            <div style="font-weight:600;font-size:14px">{{ $student->name }}</div>
            <div class="text-xs text-muted">{{ $student->username }}</div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          @if($sub)
            <span class="badge badge-{{ $sub->status }}">{{ str_replace('_',' ',$sub->status) }}</span>
            @if($sub->marked_correct !== null)
              <x-ui.badge :status="$sub->marked_correct ? 'completed' : 'cancelled'" :label="$sub->marked_correct ? 'Correct' : 'Wrong'" />
            @elseif($sub->status === 'done')
              <span class="badge badge-pending">Awaiting review</span>
            @endif
          @else
            <span class="badge badge-pending">Not started</span>
          @endif
        </div>
      </div>

      @if($sub && $sub->status === 'done')
        @if($sub->moves_played)
          <div class="hw-replay" data-replay-host="{{ $sub->id }}"
               data-moves="{{ $sub->moves_played }}">
            <div>
              <div id="hw-board-{{ $sub->id }}" class="hw-board"></div>
              <div class="hw-controls">
                <button class="btn btn-xs btn-ghost" data-rep-act="start" data-rep="{{ $sub->id }}">⏮</button>
                <button class="btn btn-xs btn-ghost" data-rep-act="prev"  data-rep="{{ $sub->id }}">◀</button>
                <span class="hw-counter" id="hw-counter-{{ $sub->id }}">0/0</span>
                <button class="btn btn-xs btn-ghost" data-rep-act="next"  data-rep="{{ $sub->id }}">▶</button>
                <button class="btn btn-xs btn-ghost" data-rep-act="end"   data-rep="{{ $sub->id }}">⏭</button>
                <button class="btn btn-xs btn-ghost" data-rep-act="flip"  data-rep="{{ $sub->id }}">⇅</button>
              </div>
            </div>
            <div class="hw-side">
              <h4>Moves played ({{ count(array_filter(explode(' ', $sub->moves_played))) }})</h4>
              <div class="hw-mvlist" id="hw-mvlist-{{ $sub->id }}">
                <span class="hw-empty">Loading…</span>
              </div>

              <div class="flex items-center gap-2 flex-wrap mt-3">
                <button class="btn btn-sm btn-success {{ $sub->marked_correct===true?'':'btn-ghost' }}"
                        onclick="markSubmission({{ $sub->id }}, true)">Correct</button>
                <button class="btn btn-sm btn-danger {{ $sub->marked_correct===false?'':'btn-ghost' }}"
                        onclick="markSubmission({{ $sub->id }}, false)">Wrong</button>
                <input type="text" id="note-{{ $sub->id }}" placeholder="Add coach note…"
                       value="{{ $sub->coach_note }}"
                       style="flex:1;min-width:160px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:12px">
                <button class="btn btn-sm" onclick="saveNote({{ $sub->id }}, this)">Save feedback</button>
              </div>

              @if($sub->coach_note)
                <div style="margin-top:8px;font-size:12px;color:var(--accent);padding:6px 10px;background:#f0f0ff;border-radius:6px">
                  {{ $sub->coach_note }}
                </div>
              @endif
              @if($sub->submitted_at)
                <div class="text-xs text-muted mt-2">Submitted: {{ $sub->submitted_at->timezone('Asia/Kolkata')->format('d M Y, g:i A') }} <span style="color:#94a3b8">IST</span></div>
              @endif
            </div>
          </div>
        @else
          <div class="text-sm text-muted">Marked done, but no moves recorded.</div>
          <div class="flex items-center gap-2 flex-wrap mt-3">
            <button class="btn btn-sm btn-success {{ $sub->marked_correct===true?'':'btn-ghost' }}"
                    onclick="markSubmission({{ $sub->id }}, true)">Correct</button>
            <button class="btn btn-sm btn-danger {{ $sub->marked_correct===false?'':'btn-ghost' }}"
                    onclick="markSubmission({{ $sub->id }}, false)">Needs improvement</button>
            <input type="text" id="note-{{ $sub->id }}" placeholder="Add coach feedback…"
                   value="{{ $sub->coach_note }}" maxlength="500"
                   style="flex:1;min-width:160px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:12px">
            <button class="btn btn-sm" onclick="saveNote({{ $sub->id }}, this)">Save feedback</button>
          </div>
          @if($sub->submitted_at)
            <div class="text-xs text-muted mt-2">Submitted: {{ $sub->submitted_at->timezone('Asia/Kolkata')->format('d M Y, g:i A') }} <span style="color:#94a3b8">IST</span></div>
          @endif
        @endif
      @elseif(!$sub || $sub->status === 'pending')
        <div class="text-sm text-muted">Student has not started yet.</div>
      @endif
    </div>
  @empty
    <x-ui.empty-state title="No student found" description="No student is assigned to this homework." />
  @endforelse
</div>
@endsection

@push('scripts')
@include('partials._chess-engine')
<script>
const MARK_URLS    = @json($homework->submissions->pluck('id')->mapWithKeys(fn($id) => [$id => route('coach.homework.mark', $id)]));
const HOMEWORK_PGN = @json($homework->chapter->pgn);

async function markSubmission(subId, correct) {
  const note = document.getElementById('note-'+subId)?.value || '';
  const r = await fetch(MARK_URLS[subId], {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':window.CSRF || '{{ csrf_token() }}','Accept':'application/json'},
    body: JSON.stringify({ marked_correct: correct, coach_note: note })
  });
  if (r.ok) location.reload();
}
async function saveNote(subId, button) {
  const note = document.getElementById('note-'+subId)?.value || '';
  const r = await fetch(MARK_URLS[subId], {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':window.CSRF || '{{ csrf_token() }}','Accept':'application/json'},
    body: JSON.stringify({ marked_correct: null, coach_note: note })
  });
  if (r.ok) { button.textContent='Saved'; setTimeout(()=>button.textContent='Save feedback',2000); }
  else alert('Feedback could not be saved. Please try again.');
}

// ── Per-submission move replay ──────────────────────────────────────
// Entry-point FEN = last position in the chapter PGN (where the student began).
const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
const chapterPositions = MiniChess.parsePGNFile(HOMEWORK_PGN) || [];
const entryFen = (() => {
  if (!chapterPositions.length) return STARTING_FEN;
  const last = chapterPositions[chapterPositions.length - 1];
  if (last.mainLine && last.mainLine.length) return last.mainLine[last.mainLine.length - 1].fen;
  return last.startFen || STARTING_FEN;
})();

function applyOneMove(state, token) {
  const before = JSON.parse(JSON.stringify(state));
  if (MiniChess.applySAN(state, token)) {
    return { ok: true, san: token, fen: MiniChess.toFen(state) };
  }
  // Restore and try coord form (e2-e4 / e2e4)
  Object.assign(state, before);
  const m = token.match(/^([a-h][1-8])-?([a-h][1-8])([qrbnQRBN])?$/);
  if (!m) return { ok: false, san: token };
  const [sr, sf] = MiniChess.sqToIdx(m[1]);
  const [tr, tf] = MiniChess.sqToIdx(m[2]);
  const piece = state.board[7 - sr][sf];
  if (!piece) return { ok: false, san: token };
  state.board[7 - sr][sf] = null;
  state.board[7 - tr][tf] = m[3] ? (state.turn === 'w' ? m[3].toUpperCase() : m[3].toLowerCase()) : piece;
  state.turn = state.turn === 'w' ? 'b' : 'w';
  return { ok: true, san: m[1] + '-' + m[2], fen: MiniChess.toFen(state) };
}

const replays = {};

function bootReplay(host) {
  const subId  = host.dataset.replayHost;
  const tokens = (host.dataset.moves || '').split(/\s+/).filter(Boolean);

  let state = MiniChess.parseFen(entryFen);
  const fens  = [entryFen];
  const moves = [];     // {san, fen, bad}
  for (const t of tokens) {
    const before = JSON.parse(JSON.stringify(state));
    const out = applyOneMove(state, t);
    if (out.ok) {
      fens.push(out.fen);
      moves.push({ san: out.san, fen: out.fen, bad: false });
    } else {
      // illegal/unparseable token — keep original token as label, mark bad, don't advance
      Object.assign(state, before);
      moves.push({ san: t, fen: fens[fens.length - 1], bad: true });
      fens.push(fens[fens.length - 1]);
    }
  }

  let idx = 0;
  const board = new MiniChess.Board('hw-board-' + subId, {
    size: 300,
    draggable: false,
    position: fens[0],
  });
  replays[subId] = { fens, moves, idx, board };

  renderList(subId);
  renderCounter(subId);
}

function renderList(subId) {
  const r = replays[subId];
  const el = document.getElementById('hw-mvlist-' + subId);
  if (!r.moves.length) { el.innerHTML = '<span class="hw-empty">No moves recorded.</span>'; return; }
  el.innerHTML = '';
  // Walk fen-by-fen to know whose move it was
  let baseState = MiniChess.parseFen(entryFen);
  let mover    = baseState.turn;
  let moveNum  = baseState.fullMove;
  let needNum  = true;
  r.moves.forEach((m, i) => {
    if (mover === 'w' && needNum) {
      const mn = document.createElement('span'); mn.className = 'mn'; mn.textContent = moveNum + '.'; el.appendChild(mn); needNum = false;
    } else if (mover === 'b' && needNum) {
      const mn = document.createElement('span'); mn.className = 'mn'; mn.textContent = moveNum + '...'; el.appendChild(mn); needNum = false;
    }
    const span = document.createElement('span');
    span.className = 'mv' + ((i + 1) === r.idx ? ' active' : '') + (m.bad ? ' bad' : '');
    span.title = m.bad ? 'Could not parse this move' : '';
    span.textContent = m.san;
    span.addEventListener('click', () => jumpTo(subId, i + 1));
    el.appendChild(span);
    if (mover === 'b') { moveNum++; needNum = true; mover = 'w'; }
    else                { mover = 'b'; }
  });
}

function renderCounter(subId) {
  const r = replays[subId];
  document.getElementById('hw-counter-' + subId).textContent = r.idx + '/' + (r.fens.length - 1);
}

function jumpTo(subId, n) {
  const r = replays[subId];
  r.idx = Math.max(0, Math.min(n, r.fens.length - 1));
  r.board.setPosition(r.fens[r.idx]);
  renderList(subId);
  renderCounter(subId);
}

document.querySelectorAll('[data-rep-act]').forEach(btn => {
  btn.addEventListener('click', () => {
    const subId = btn.dataset.rep;
    const r = replays[subId];
    if (!r) return;
    const a = btn.dataset.repAct;
    if (a === 'start') jumpTo(subId, 0);
    else if (a === 'prev')  jumpTo(subId, r.idx - 1);
    else if (a === 'next')  jumpTo(subId, r.idx + 1);
    else if (a === 'end')   jumpTo(subId, r.fens.length - 1);
    else if (a === 'flip')  r.board.flip();
  });
});

document.querySelectorAll('[data-replay-host]').forEach(bootReplay);
</script>
@endpush

@extends('layouts.app')
@section('page-title', $set->name . ' — Puzzle ' . $puzzle->puzzle_order)
@section('sidebar-nav') @include('student._sidebar') @endsection

@include('partials._chess-ui')
@push('styles')
<style>
.pl-student-wrap{display:grid;grid-template-columns:260px minmax(420px,1fr) 320px;gap:16px;align-items:flex-start}
.pl-student-left,.pl-student-right{display:flex;flex-direction:column;gap:12px}
.pl-student-list{max-height:640px;overflow:auto}
.pl-student-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid var(--border)}
.pl-student-row.active{background:#dbeafe}
.pl-student-row a{font-weight:700}
.pl-student-board-card{display:flex;flex-direction:column;align-items:center}
.pl-student-hint{min-height:64px;padding:12px;border:1px dashed var(--border);border-radius:12px;background:#fff}
.pl-student-solution{font-size:13px;line-height:1.8}
.pl-student-solution .mn{font-family:ui-monospace,monospace;color:var(--text3);margin-right:4px}
.pl-student-solution .mv{display:inline-block;padding:1px 5px;border-radius:5px;margin-right:3px;background:#f1f5f9}
@media (max-width:1100px){.pl-student-wrap{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<x-ui.page-header title="Puzzle {{ $puzzle->puzzle_order }}" subtitle="{{ $set->name }} · Puzzle {{ $puzzle->puzzle_order }} of {{ $set->total_puzzles }}">
  <x-slot:actions>
    <div class="flex gap-2 flex-wrap">
      <x-ui.button :href="route('student.puzzle-library.show', $set)" variant="secondary">Back</x-ui.button>
      <span class="badge">{{ $progress['completed_puzzles'] ?? 0 }}/{{ $set->total_puzzles }} Solved</span>
      <span class="badge"><span id="student-total-points">{{ $progress['total_points'] ?? 0 }}</span> Points</span>
    </div>
  </x-slot:actions>
</x-ui.page-header>

<div class="pl-student-wrap">
  <div class="pl-student-left">
    <div class="card" style="padding:14px">
      <div class="font-semibold">{{ $set->name }}</div>
      <div class="text-sm text-muted mt-2">Puzzle {{ $puzzle->puzzle_order }} of {{ $set->total_puzzles }}</div>
      <div class="text-sm text-muted">Status: <span id="student-puzzle-status">{{ $attempt->status === 'completed' ? 'Correct' : ($attempt->status === 'in_progress' ? 'In Progress' : 'Not Started') }}</span></div>
    </div>

    <div class="card" style="padding:0">
      <div class="card-title" style="padding:12px 14px;border-bottom:1px solid var(--border)">Puzzles</div>
      <div class="pl-student-list">
        @foreach($puzzles as $listPuzzle)
          @php
            $listAttempt = $listPuzzle->attempts->first();
            $listStatus = $listAttempt?->status ?? 'not_started';
            $listLabel = match($listStatus) {
              'completed' => 'Correct',
              'in_progress' => 'In Progress',
              default => 'Not Started',
            };
          @endphp
          <div class="pl-student-row {{ $listPuzzle->id === $puzzle->id ? 'active' : '' }}">
            <a href="{{ route('student.puzzle-library.solve', [$set, $listPuzzle]) }}">Puzzle {{ $listPuzzle->puzzle_order }}</a>
            <span class="badge badge-{{ $listStatus === 'completed' ? 'active' : ($listStatus === 'in_progress' ? 'warning' : 'inactive') }}">{{ $listLabel }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="card pl-student-board-card">
    <div id="student-puzzle-board"></div>
    <div class="board-controls">
      <button type="button" class="btn btn-sm btn-ghost" id="student-reset-btn">Reset</button>
      <button type="button" class="btn btn-sm btn-ghost" id="student-flip-btn">Flip</button>
      <button type="button" class="btn btn-sm btn-primary" id="student-save-progress-btn" disabled>Progress Saved</button>
    </div>
    <div class="text-sm text-muted mt-2" id="student-board-message">{{ $attempt->status === 'completed' ? 'Puzzle solved successfully.' : ' ' }}</div>
  </div>

  <div class="pl-student-right">
    <div class="card">
      <div class="card-title">Puzzle Information</div>
      <div class="text-sm text-muted">Wrong Attempts: <span id="student-wrong-attempts">{{ (int) $attempt->wrong_attempts }}</span></div>
      <div class="text-sm text-muted mt-2">Points: <span id="student-points">{{ $attempt->points }}</span></div>
      <div class="text-sm text-muted mt-2">Hints Used: <span id="student-hint-count">{{ (int) $attempt->hint_count }}</span></div>
      <div class="text-sm text-muted mt-2">Progress: {{ $progress['progress_percent'] ?? 0 }}%</div>
      <div class="mt-3">
        <button type="button" class="btn btn-primary" id="student-hint-btn" {{ $attempt->status === 'completed' ? 'disabled' : '' }}>Hint</button>
      </div>
      <div class="pl-student-hint mt-3" id="student-hint-box"></div>
    </div>

    <div class="card">
      <div class="card-title">{{ $attempt->status === 'completed' ? 'Moves' : 'Progress' }}</div>
      <div id="student-solution-wrap" class="pl-student-solution"></div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@include('partials._chess-engine')
<script>
(function(){
  let currentFen = @json($attempt->current_fen ?: $puzzle->original_fen);
  const originalFen = @json($puzzle->original_fen);
  const isCompleted = @json($attempt->status === 'completed');
  let moveHistory = @json($attempt->student_moves ?? []);
  const solutionPgn = @json($attempt->status === 'completed' ? $puzzle->answer_pgn : null);
  const moveRoute = @json(route('student.puzzle-library.move', [$set, $puzzle]));
  const hintRoute = @json(route('student.puzzle-library.hint', [$set, $puzzle]));
  const csrf = @json(csrf_token());

  const board = new MiniChess.Board('student-puzzle-board', {
    size: 520,
    draggable: !isCompleted,
    position: currentFen,
    onMove: handleMove,
  });

  const messageEl = document.getElementById('student-board-message');
  const statusEl = document.getElementById('student-puzzle-status');
  const wrongEl = document.getElementById('student-wrong-attempts');
  const pointsEl = document.getElementById('student-points');
  const totalPointsEl = document.getElementById('student-total-points');
  const hintCountEl = document.getElementById('student-hint-count');
  const hintBox = document.getElementById('student-hint-box');
  const hintBtn = document.getElementById('student-hint-btn');
  const saveBtn = document.getElementById('student-save-progress-btn');
  const solutionWrap = document.getElementById('student-solution-wrap');

  function setMessage(text, type) {
    messageEl.textContent = text || ' ';
    messageEl.style.color = type === 'error' ? '#dc2626' : (type === 'success' ? '#15803d' : '');
  }

  function renderProgressMoves() {
    if (solutionPgn && isCompleted) {
      const game = (MiniChess.parsePGNFile(solutionPgn) || [])[0];
      const moves = game?.mainLine || [];
      if (!moves.length) {
        solutionWrap.innerHTML = '<span class="text-muted">No moves saved.</span>';
        return;
      }
      let html = '';
      let lastMoveNumber = null;
      moves.forEach((move) => {
        if (move.moveNumber !== lastMoveNumber) {
          html += `<span class="mn">${move.moveNumber}${move.side === 'w' ? '.' : '...'}</span>`;
          lastMoveNumber = move.moveNumber;
        }
        html += `<span class="mv">${move.san}</span>`;
      });
      solutionWrap.innerHTML = html;
      return;
    }

    if (!moveHistory.length) {
      solutionWrap.innerHTML = '<span class="text-muted">No moves yet.</span>';
      return;
    }

    solutionWrap.innerHTML = moveHistory.map((move, index) => `
      <div class="text-sm" style="margin-bottom:6px">Move ${index + 1}: <strong>${move.from} → ${move.to}</strong></div>
    `).join('');
  }

  async function handleMove(from, to, fenAfterLocalMove) {
    board.setPosition(currentFen);
    setMessage('Saving move...', 'info');
    hintBox.textContent = '';
    hintBtn.disabled = true;

    try {
      const response = await fetch(moveRoute, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ from, to }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload.message || 'Move could not be saved.');
      }

      wrongEl.textContent = payload.attempt?.wrong_attempts ?? wrongEl.textContent;
      hintCountEl.textContent = payload.attempt?.hint_count ?? hintCountEl.textContent;
      if (typeof payload.attempt?.points !== 'undefined') pointsEl.textContent = payload.attempt.points;
      if (typeof payload.progress?.total_points !== 'undefined') totalPointsEl.textContent = payload.progress.total_points;
      statusEl.textContent = payload.completed ? 'Correct' : (payload.correct ? 'In Progress' : 'In Progress');

      if (!payload.correct) {
        board.setPosition(payload.current_fen || currentFen);
        setMessage(payload.message || 'Wrong move. Try again.', 'error');
        window.ArcherToast?.show(payload.message || 'Wrong move. Try again.', 'error');
        hintBtn.disabled = false;
        return;
      }

      moveHistory.push({ from, to });
      renderProgressMoves();
      saveBtn.textContent = 'Progress Saved';
      currentFen = payload.current_fen || currentFen;
      board.setPosition(currentFen);
      setMessage(payload.message || 'Correct move.', 'success');

      if (payload.completed) {
        window.ArcherToast?.show('Puzzle solved successfully.', 'success');
        setTimeout(() => window.location.reload(), 700);
        return;
      }

      if (payload.auto_reply?.san) {
        window.ArcherToast?.show(`Reply played: ${payload.auto_reply.san}`, 'info');
      } else {
        window.ArcherToast?.show(payload.message || 'Correct move.', 'success');
      }
    } catch (error) {
      board.setPosition(currentFen);
      setMessage(error.message || 'Move could not be saved.', 'error');
      window.ArcherToast?.show(error.message || 'Move could not be saved.', 'error');
    } finally {
      hintBtn.disabled = isCompleted;
    }
  }

  document.getElementById('student-reset-btn')?.addEventListener('click', () => {
    board.setPosition(originalFen);
    hintBox.textContent = '';
    setMessage(isCompleted ? 'Puzzle solved successfully.' : ' ', '');
  });

  document.getElementById('student-flip-btn')?.addEventListener('click', () => board.flip());

  hintBtn?.addEventListener('click', async () => {
    hintBtn.disabled = true;
    try {
      const response = await fetch(hintRoute, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload.message || 'Hint unavailable.');
      }

      hintCountEl.textContent = payload.attempt?.hint_count ?? hintCountEl.textContent;
      if (typeof payload.attempt?.points !== 'undefined') pointsEl.textContent = payload.attempt.points;
      statusEl.textContent = payload.attempt?.status === 'completed' ? 'Correct' : 'In Progress';

      if (payload.level === 1) {
        hintBox.textContent = `From square: ${payload.from}`;
      } else if (payload.level === 2) {
        hintBox.textContent = `To square: ${payload.to}`;
      } else if (payload.level === 3) {
        hintBox.textContent = `Move: ${payload.notation}`;
      } else {
        hintBox.textContent = 'Puzzle solved successfully.';
      }

      window.ArcherToast?.show('Hint revealed.', 'info');
    } catch (error) {
      window.ArcherToast?.show(error.message || 'Hint unavailable.', 'error');
    } finally {
      hintBtn.disabled = isCompleted;
    }
  });

  renderProgressMoves();
  if (!hintBox.textContent.trim()) {
    hintBox.textContent = isCompleted ? 'Puzzle solved successfully.' : 'No hint used.';
  }
})();
</script>
@endpush

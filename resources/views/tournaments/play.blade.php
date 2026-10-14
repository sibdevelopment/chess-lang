@extends('layouts.app')
@section('page-title','Play — '.$tournament->name)

@section('sidebar-nav')
@include('tournaments._sidebar')
@endsection

@push('styles')
<style>
  /* ── Tournament play screen takeover ──────────────────────────────────────
     Lichess-style: dark stage, clocks dominant, board centred, slim sidebar.
     All chrome (sidebar, topbar, drawer) is hidden while the game is live —
     the only legal exits are resign / draw / checkmate / timeout, plus
     admin-cancel which forces a redirect on the next poll. */
  body.tournament-locked .ui-admin-sidebar,
  body.tournament-locked .ui-admin-topbar,
  body.tournament-locked .ui-admin-footer { display:none !important; }
  body.tournament-locked .ui-admin-shell,
  body.tournament-locked .ui-admin-main,
  body.tournament-locked .ui-admin-page { background:#161512; }
  body.tournament-locked .ui-admin-main { width:100% !important; max-width:100% !important; margin-left:0 !important; }
  body.tournament-locked .ui-admin-page { padding:24px !important; }
  body.tournament-locked .ui-admin-page > * { max-width:1180px; margin-inline:auto; }
  @media (max-width:1199px) { body.tournament-locked .ui-admin-page { padding:20px !important; } }
  @media (max-width:767px) { body.tournament-locked .ui-admin-page { padding:16px !important; } }
  body.tournament-locked { background:#161512 }            /* lichess-ish stage */

  /* Lock banner — slim, top-of-screen, lichess crimson on hostile back-press */
  .tlock-banner{position:sticky;top:0;z-index:30;background:var(--color-primary);color:#fff;padding:8px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-radius:0 0 8px 8px;margin-bottom:14px;font-weight:600;font-size:12.5px;letter-spacing:.2px}
  .tlock-banner .tlock-dot{width:7px;height:7px;background:#fde68a;border-radius:50%;animation:tlockPulse 1.2s infinite}
  @keyframes tlockPulse{0%,100%{opacity:.55;transform:scale(1)}50%{opacity:1;transform:scale(1.4)}}

  /* Layout */
  .play-wrap{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start;color:#bababa}
  @media (max-width: 980px) { .play-wrap{grid-template-columns:1fr} }
  .play-wrap a{color:#7fa650}

  /* Player rows above and below the board (Lichess pattern) */
  .lp-row{display:flex;align-items:center;gap:12px;padding:6px 4px}
  .lp-pa{width:38px;height:38px;border-radius:6px;background:#2c2c2a;color:#dadada;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;border:1px solid #3a3a37}
  .lp-name{flex:1;min-width:0}
  .lp-name .nm{font-weight:600;color:#dadada;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .lp-name .sub{font-size:11px;color:#7c7b78;margin-top:1px}
  .lp-you{display:inline-block;font-size:9.5px;color:#000;background:#bababa;padding:1px 5px;border-radius:3px;margin-left:6px;font-weight:700;letter-spacing:.4px;vertical-align:middle}

  /* Lichess-style clock */
  .lp-clock{
    font-family:'Roboto Mono','SF Mono',ui-monospace,Menlo,monospace;
    font-size:32px;font-weight:600;letter-spacing:1px;
    padding:6px 16px;border-radius:4px;
    background:#262421;color:#bababa;
    border:1px solid #1f1d1b;
    min-width:128px;text-align:right;line-height:1.1;
    transition:background .15s,color .15s;
  }
  .lp-clock.active{background:#fff;color:#000;border-color:#fff}      /* side to move */
  .lp-clock.idle  {background:#262421;color:#7a7a78}                  /* not their turn */
  .lp-clock.warn.active {background:#cc3636;color:#fff;border-color:#cc3636} /* < 30s */
  .lp-clock.warn.idle   {background:#3a1f1f;color:#cc8b8b}
  .lp-clock.dead {background:#1a1816;color:#5a5a58;border-color:#1a1816}

  /* Board centring + frame */
  .board-shell{display:flex;justify-content:center}
  .board-frame{position:relative}
  #board{width:min(560px, 100%)}

  /* End-of-game overlay (centred) */
  .game-end-overlay{position:absolute;inset:0;background:rgba(20,20,18,.96);color:#dadada;display:flex;align-items:center;justify-content:center;flex-direction:column;border-radius:6px;padding:30px;text-align:center;z-index:5}
  .game-end-overlay h2{font-size:24px;font-weight:600;margin:0 0 8px;color:#fff}
  .game-end-overlay .reason{color:#9a9a98;font-size:14px;margin-bottom:18px}
  .game-end-overlay .btn{background:#629924;border:none;color:#fff;padding:9px 18px;border-radius:4px;font-weight:600;text-decoration:none}
  .game-end-overlay .btn:hover{background:#7eb73c}
  .game-end-overlay.cancelled .btn{background:#cc3636}
  .game-end-overlay.cancelled .btn:hover{background:#dc4848}

  /* Sidebar cards — lichess slate */
  .lp-card{background:#262421;border:1px solid #1f1d1b;border-radius:4px;padding:12px 14px;margin-bottom:10px;color:#bababa}
  .lp-card .ttl{font-size:12px;color:#9a9a98;margin-bottom:8px;font-weight:600}
  .lp-meta{font-size:12px;color:#9a9a98}
  .lp-meta strong{color:#dadada;font-weight:600}
  .lp-pill{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:3px}
  .lp-pill.live{background:#629924;color:#fff}
  .lp-pill.done{background:#3692e7;color:#fff}
  .lp-pill.cancelled{background:#cc3636;color:#fff}

  /* Lichess-style move list — paired columns */
  .lp-moves{
    background:#1f1d1b;border-radius:4px;padding:0;
    max-height:260px;overflow-y:auto;
    font-family:var(--font-ui);
    font-size:13px;
  }
  .lp-moves table{width:100%;border-collapse:collapse}
  .lp-moves td{padding:5px 8px;color:#dadada}
  .lp-moves td.num{width:34px;color:#787876;background:#1a1816;text-align:right;font-size:11.5px;font-weight:600}
  .lp-moves td.san{font-weight:600}
  .lp-moves tr:nth-child(odd) td.san{background:#262421}
  .lp-moves tr:nth-child(even) td.san{background:#22201d}
  .lp-moves .empty{padding:14px;text-align:center;color:#787876;font-size:12.5px}

  /* Action buttons */
  .lp-actions{display:flex;gap:6px;flex-wrap:wrap}
  .lp-btn{flex:1;min-width:100px;padding:8px 10px;border-radius:4px;border:none;font-weight:600;font-size:12.5px;cursor:pointer;letter-spacing:.4px;transition:filter .12s}
  .lp-btn:hover{filter:brightness(1.12)}
  .lp-btn.draw{background:#3692e7;color:#fff}
  .lp-btn.resign{background:#3a3735;color:#dadada}
  .lp-btn.danger{background:#cc3636;color:#fff}
  .lp-btn:disabled{opacity:.45;cursor:not-allowed}

  /* Draw-offer banner */
  .lp-drawoffer{
    background:#3a2f0a;border:1px solid #6b5a16;color:#fde68a;
    padding:10px;border-radius:4px;font-size:12.5px;display:flex;
    align-items:center;gap:8px;margin-top:10px;
  }
  .lp-drawoffer .lp-btn{flex:0 0 auto;padding:5px 10px;font-size:11.5px}

  .lp-msg{font-size:11.5px;color:#cc8b8b;min-height:14px;margin-top:6px}
  .lp-msg.ok{color:#9ad36c}

  /* Status header (replaces the old purple .ph block) */
  .lp-head{
    display:flex;justify-content:space-between;align-items:center;
    padding:6px 4px 14px;border-bottom:1px solid #2c2a28;margin-bottom:14px;
    color:#bababa
  }
  .lp-head h1{font-size:15px;font-weight:600;color:#dadada;margin:0}
  .lp-head .tname{font-size:12px;color:#787876;margin-top:2px}
  .lp-head .tc{font-family:'Roboto Mono',monospace;font-size:13px;color:#9a9a98;background:#262421;padding:4px 10px;border-radius:3px;border:1px solid #1f1d1b}

</style>
@endpush

@php
  $u         = $student;
  $myColor   = $pairing->colorOf($u->id);
  $opponent  = $pairing->opponentOf($u->id);
  $orient    = $myColor === 'b' ? 'black' : 'white';
  $live      = $pairing->liveClocks();         // ← seed clocks with elapsed-aware values
  $sideTurn  = $pairing->sideToMove() ?: 'w';
@endphp

@section('content')
@include('partials._chess-engine')

{{-- Tells the layout poller to NOT yank us off this page. --}}
<script>window.__INSIDE_TOURNAMENT_GAME = true;</script>

<div class="tlock-banner" id="tlock-banner">
  <div style="display:flex;align-items:center;gap:10px">
    <span class="tlock-dot"></span>
    <span>Live tournament game — navigation is locked. Resign, accept a draw, or finish the game to continue.</span>
  </div>
  <div style="font-size:11px;opacity:.85">Round {{ $pairing->round }} · Board {{ $pairing->board }}</div>
</div>

<div class="lp-head">
  <div>
    <h1>Round {{ $pairing->round }} · Board {{ $pairing->board }}</h1>
    <div class="tname">{{ $tournament->name }} · {{ ucfirst($tournament->format) }}</div>
  </div>
  <div class="tc">{{ $tournament->time_control }}</div>
</div>

<div class="play-wrap">
  <div>
    {{-- Opponent row (top of board, Lichess pattern) --}}
    <div class="lp-row">
      <div class="lp-pa">{{ strtoupper(substr($opponent->name ?? '?', 0, 1)) }}</div>
      <div class="lp-name">
        <div class="nm">{{ $opponent->name ?? '—' }}</div>
        <div class="sub">{{ $myColor === 'w' ? 'Black' : 'White' }}</div>
      </div>
      <div id="opp-clock" class="lp-clock idle">—:—</div>
    </div>

    <div class="board-shell">
      <div class="board-frame">
        <div id="board" style="margin:0 auto"></div>
        <div id="game-end" class="game-end-overlay" style="display:none">
          <h2 id="end-headline">Game over</h2>
          <div class="reason" id="end-reason"></div>
          <a class="btn" id="end-back" href="{{ route('student.tournaments.show', $tournament) }}">Back to standings</a>
        </div>
      </div>
    </div>

    {{-- My row (bottom of board) --}}
    <div class="lp-row">
      <div class="lp-pa" style="background:#3a4f1f;border-color:#4a6228;color:#dadada">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
      <div class="lp-name">
        <div class="nm">{{ $u->name }}<span class="lp-you">YOU</span></div>
        <div class="sub">{{ $myColor === 'w' ? 'White' : 'Black' }}</div>
      </div>
      <div id="my-clock" class="lp-clock idle">—:—</div>
    </div>
  </div>

  <aside>
    <div class="lp-card">
      <div class="ttl">Game</div>
      <div style="margin-bottom:10px">
        <span id="status-pill" class="lp-pill live">Live</span>
        <span class="lp-meta" id="turn-text" style="margin-left:8px">Loading…</span>
      </div>
      <div class="lp-meta" id="move-help" style="margin-bottom:10px">Click a piece, then click a destination square.</div>
      <div class="lp-meta" style="margin-bottom:10px">
        <strong>{{ $tournament->time_control }}</strong> · {{ ucfirst($tournament->format) }} · move <strong id="move-count">{{ $pairing->move_count }}</strong>
      </div>

      <div class="lp-actions">
        <button class="lp-btn draw"   id="btn-draw">½ Offer draw</button>
        <button class="lp-btn resign" id="btn-resign" onclick="if(confirm('Resign this game?')) resignGame()">Resign</button>
      </div>

      <div id="draw-offered" class="lp-drawoffer" style="display:none">
        <span style="flex:1">Opponent offered a draw.</span>
        <button class="lp-btn draw" onclick="offerDraw()">Accept ½–½</button>
      </div>

      <div id="msg" class="lp-msg"></div>
    </div>

    <div class="lp-card">
      <div class="ttl">Moves</div>
      <div class="lp-moves" id="move-log-wrap">
        <div class="empty" id="move-log-empty">No moves yet</div>
        <table id="move-log-tbl" style="display:none"><tbody id="move-log-body"></tbody></table>
      </div>
    </div>
  </aside>
</div>

<script>
  const POLL_URL   = "{{ route('student.tournaments.poll',   [$tournament, $pairing]) }}";
  const MOVE_URL   = "{{ route('student.tournaments.move',   [$tournament, $pairing]) }}";
  const RESIGN_URL = "{{ route('student.tournaments.resign', [$tournament, $pairing]) }}";
  const DRAW_URL   = "{{ route('student.tournaments.draw',   [$tournament, $pairing]) }}";
  const MY_COLOR   = "{{ $myColor }}";          // 'w' or 'b'
  const MY_ID      = {{ (int)$u->id }};
  const STARTING_FEN = "{{ $pairing->current_fen }}";
  const STANDINGS_URL = "{{ route('student.tournaments.show', $tournament) }}";

  const MC = window.MiniChess;
  let board  = null;
  let lastFen = STARTING_FEN;
  let lastMoveCount = {{ (int) $pairing->move_count }};
  let gameOver = false;

  // ── Tournament lock ──────────────────────────────────────────────────────
  document.body.classList.add('tournament-locked');

  function lockBeforeUnload(e) {
    if (gameOver) return;
    e.preventDefault();
    e.returnValue = 'You are in a live tournament game. Leaving will count as a forfeit.';
    return e.returnValue;
  }
  window.addEventListener('beforeunload', lockBeforeUnload);

  history.pushState({ tlock: 1 }, '');
  window.addEventListener('popstate', function () {
    if (gameOver) return;
    history.pushState({ tlock: 1 }, '');
    const b = document.getElementById('tlock-banner');
    if (b) {
      b.style.background = '#dc2626';
      setTimeout(() => { b.style.background = '#2563eb'; }, 1200);
    }
  });

  function releaseTournamentLock() {
    gameOver = true;
    document.body.classList.remove('tournament-locked');
    const b = document.getElementById('tlock-banner');
    if (b) b.style.display = 'none';
    window.removeEventListener('beforeunload', lockBeforeUnload);
  }

  let pendingMoveLock = false;
  let selectedLegalMoves = [];
  let redirectTimer = null;

  // Seed clocks with the SERVER's elapsed-adjusted snapshot.
  // Without this, on a stale pairing the raw stored clocks would already
  // be at zero before the player has rendered their first frame.
  let clockState = {
    white: {{ (int) $live['white'] }},
    black: {{ (int) $live['black'] }},
    sideToMove: '{{ $sideTurn }}',
    lastServerSyncAt: Date.now(),
    status: '{{ $pairing->status }}',
  };

  function cloneState(state) {
    return {
      board: state.board.map(row => row.slice()),
      turn: state.turn,
      castling: state.castling,
      ep: state.ep,
      halfMove: state.halfMove,
      fullMove: state.fullMove,
    };
  }

  function findKing(state, side) {
    const king = side === 'w' ? 'K' : 'k';
    for (let rank = 0; rank < 8; rank++) {
      for (let file = 0; file < 8; file++) {
        if (MC.getPiece(state.board, rank, file) === king) {
          return [rank, file];
        }
      }
    }
    return null;
  }

  function isSquareAttacked(state, rank, file, bySide) {
    for (let r = 0; r < 8; r++) {
      for (let f = 0; f < 8; f++) {
        const piece = MC.getPiece(state.board, r, f);
        if (!piece) continue;
        const isWhite = piece === piece.toUpperCase();
        if ((bySide === 'w') !== isWhite) continue;
        if (piece.toLowerCase() === 'p') {
          const pawnDir = isWhite ? 1 : -1;
          for (const df of [-1, 1]) {
            if (r + pawnDir === rank && f + df === file) return true;
          }
          continue;
        }
        const targets = MC.pieceMoves(state, r, f);
        if (targets.some(([tr, tf]) => tr === rank && tf === file)) return true;
        if (piece.toLowerCase() === 'k' && Math.abs(r - rank) <= 1 && Math.abs(f - file) <= 1) {
          return true;
        }
      }
    }
    return false;
  }

  function isKingInCheck(state, side) {
    const kingPos = findKing(state, side);
    if (!kingPos) return false;
    return isSquareAttacked(state, kingPos[0], kingPos[1], side === 'w' ? 'b' : 'w');
  }

  function canCastle(state, side, castleSide) {
    const rights = state.castling || '-';
    const rank = side === 'w' ? 0 : 7;
    const king = side === 'w' ? 'K' : 'k';
    const rook = side === 'w' ? 'R' : 'r';
    const kingside = castleSide === 'kingside';
    const rightChar = side === 'w'
      ? (kingside ? 'K' : 'Q')
      : (kingside ? 'k' : 'q');

    if (!rights.includes(rightChar)) return false;
    if (MC.getPiece(state.board, rank, 4) !== king) return false;
    if (MC.getPiece(state.board, rank, kingside ? 7 : 0) !== rook) return false;
    if (isKingInCheck(state, side)) return false;

    const betweenFiles = kingside ? [5, 6] : [1, 2, 3];
    if (betweenFiles.some(file => MC.getPiece(state.board, rank, file))) return false;

    const travelFiles = kingside ? [5, 6] : [3, 2];
    for (const file of travelFiles) {
      const nextState = cloneState(state);
      MC.setPiece(nextState.board, rank, 4, null);
      MC.setPiece(nextState.board, rank, file, king);
      if (isKingInCheck(nextState, side)) return false;
      MC.setPiece(nextState.board, rank, file, null);
      MC.setPiece(nextState.board, rank, 4, king);
    }
    return true;
  }

  function applyMoveToState(state, fromSq, toSq) {
    const nextState = cloneState(state);
    const [fr, ff] = MC.sqToIdx(fromSq);
    const [tr, tf] = MC.sqToIdx(toSq);
    const moving = MC.getPiece(nextState.board, fr, ff);
    if (!moving) return null;

    const isWhite = moving === moving.toUpperCase();
    const side = isWhite ? 'w' : 'b';
    const target = MC.getPiece(nextState.board, tr, tf);
    const wasCapture = !!target;

    if (moving.toLowerCase() === 'k' && Math.abs(tf - ff) === 2) {
      const kingside = tf > ff;
      MC.setPiece(nextState.board, fr, ff, null);
      MC.setPiece(nextState.board, tr, tf, moving);
      MC.setPiece(nextState.board, fr, kingside ? 7 : 0, null);
      MC.setPiece(nextState.board, fr, kingside ? 5 : 3, isWhite ? 'R' : 'r');
      nextState.castling = (nextState.castling || '-').replace(isWhite ? /[KQ]/g : /[kq]/g, '') || '-';
      nextState.ep = '-';
    } else {
      if (moving.toLowerCase() === 'p' && state.ep !== '-' && toSq === state.ep && !target) {
        MC.setPiece(nextState.board, tr + (isWhite ? -1 : 1), tf, null);
      }
      MC.setPiece(nextState.board, fr, ff, null);
      let finalPiece = moving;
      if (moving.toLowerCase() === 'p' && (tr === 7 || tr === 0)) {
        finalPiece = isWhite ? 'Q' : 'q';
      }
      MC.setPiece(nextState.board, tr, tf, finalPiece);
      if (moving.toLowerCase() === 'p' && Math.abs(tr - fr) === 2) {
        nextState.ep = MC.idxToSq((fr + tr) / 2, ff);
      } else {
        nextState.ep = '-';
      }
      let castling = nextState.castling || '-';
      if (moving === 'K') castling = castling.replace(/[KQ]/g, '');
      if (moving === 'k') castling = castling.replace(/[kq]/g, '');
      if (moving === 'R') {
        if (fr === 0 && ff === 0) castling = castling.replace('Q', '');
        if (fr === 0 && ff === 7) castling = castling.replace('K', '');
      }
      if (moving === 'r') {
        if (fr === 7 && ff === 0) castling = castling.replace('q', '');
        if (fr === 7 && ff === 7) castling = castling.replace('k', '');
      }
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

  function legalMovesForSquare(state, rank, file) {
    const piece = MC.getPiece(state.board, rank, file);
    if (!piece) return [];
    const isWhite = piece === piece.toUpperCase();
    const side = isWhite ? 'w' : 'b';
    if (state.turn !== side) return [];

    const moves = MC.pieceMoves(state, rank, file).filter(([tr, tf]) => {
      const nextState = applyMoveToState(state, MC.idxToSq(rank, file), MC.idxToSq(tr, tf));
      return nextState && !isKingInCheck(nextState, side);
    });

    if (piece.toLowerCase() === 'k') {
      if (canCastle(state, side, 'kingside')) moves.push([rank, 6]);
      if (canCastle(state, side, 'queenside')) moves.push([rank, 2]);
    }
    return moves;
  }

  function hasAnyLegalMove(state, side) {
    for (let rank = 0; rank < 8; rank++) {
      for (let file = 0; file < 8; file++) {
        const piece = MC.getPiece(state.board, rank, file);
        if (!piece) continue;
        const isWhite = piece === piece.toUpperCase();
        if ((side === 'w') !== isWhite) continue;
        if (legalMovesForSquare(state, rank, file).length > 0) return true;
      }
    }
    return false;
  }

  function fmtClock(ms) {
    if (ms == null || ms < 0) ms = 0;
    const totalSec = Math.floor(ms / 1000);
    const mm = Math.floor(totalSec / 60);
    const ss = totalSec % 60;
    // Lichess uses h:mm:ss for >= 1h games — we top out at 2 hours so this is fine.
    if (mm >= 60) {
      const hh = Math.floor(mm / 60);
      const remM = mm % 60;
      return hh + ':' + (remM<10?'0':'') + remM + ':' + (ss<10?'0':'') + ss;
    }
    return mm + ':' + (ss < 10 ? '0' : '') + ss;
  }

  function setMsg(text, isErr = true) {
    const el = document.getElementById('msg');
    el.classList.toggle('ok', !isErr);
    el.textContent = text || '';
  }

  function renderClocks() {
    const now = Date.now();
    let whiteMs = clockState.white;
    let blackMs = clockState.black;

    if (clockState.status === 'active') {
      const elapsed = Math.max(0, now - clockState.lastServerSyncAt);
      if (clockState.sideToMove === 'w') whiteMs = Math.max(0, whiteMs - elapsed);
      if (clockState.sideToMove === 'b') blackMs = Math.max(0, blackMs - elapsed);
    }

    const myMs  = MY_COLOR === 'w' ? whiteMs : blackMs;
    const oppMs = MY_COLOR === 'w' ? blackMs : whiteMs;

    const myEl  = document.getElementById('my-clock');
    const oppEl = document.getElementById('opp-clock');

    // Reset state classes
    myEl.classList.remove('active','idle','warn','dead');
    oppEl.classList.remove('active','idle','warn','dead');

    if (clockState.status !== 'active') {
      myEl.classList.add('dead');
      oppEl.classList.add('dead');
    } else {
      const myActive = clockState.sideToMove === MY_COLOR;
      myEl.classList.add(myActive ? 'active' : 'idle');
      oppEl.classList.add(myActive ? 'idle' : 'active');
      if (myMs  < 30000) myEl.classList.add('warn');
      if (oppMs < 30000) oppEl.classList.add('warn');
    }

    // ensureClockSpans() runs once on init and guarantees the spans exist.
    document.getElementById('my-time').textContent  = fmtClock(myMs);
    document.getElementById('opp-time').textContent = fmtClock(oppMs);
  }

  // Wrap the time numbers in spans so renderClocks can update without
  // wiping the .lp-clock styling.
  function ensureClockSpans() {
    const m = document.getElementById('my-clock');
    const o = document.getElementById('opp-clock');
    if (m && !document.getElementById('my-time'))  m.innerHTML = '<span id="my-time">—:—</span>';
    if (o && !document.getElementById('opp-time')) o.innerHTML = '<span id="opp-time">—:—</span>';
  }

  function updateMoveLog(pgn) {
    const wrap   = document.getElementById('move-log-wrap');
    const empty  = document.getElementById('move-log-empty');
    const tbl    = document.getElementById('move-log-tbl');
    const tbody  = document.getElementById('move-log-body');
    pgn = (pgn || '').trim();

    if (!pgn) {
      empty.style.display = 'block';
      tbl.style.display   = 'none';
      tbody.innerHTML = '';
      return;
    }
    // Split on whitespace, ignore "1.", "2.", etc.
    const tokens = pgn.split(/\s+/).filter(t => t && !/^\d+\.+$/.test(t));
    let html = '';
    for (let i = 0; i < tokens.length; i += 2) {
      const moveNum = (i / 2) + 1;
      const w = tokens[i] || '';
      const b = tokens[i + 1] || '';
      html += '<tr>'
            + '<td class="num">' + moveNum + '</td>'
            + '<td class="san">' + escapeHtml(w) + '</td>'
            + '<td class="san">' + escapeHtml(b) + '</td>'
            + '</tr>';
    }
    tbody.innerHTML = html;
    empty.style.display = 'none';
    tbl.style.display   = 'table';
    // Auto-scroll to latest move
    wrap.scrollTop = wrap.scrollHeight;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => (
      { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]
    ));
  }

  function scheduleStandingsRedirect(url) {
    if (redirectTimer) return;
    redirectTimer = window.setTimeout(() => {
      window.location.href = url || STANDINGS_URL;
    }, 2200);
  }

  function submitMove(fromSq, toSq, resultingFen) {
    if (pendingMoveLock) return;
    pendingMoveLock = true;
    const preState = MC.parseFen(lastFen);
    const san = MC.moveToSan(preState, fromSq, toSq, resultingFen);
    const postState = MC.parseFen(resultingFen);
    const opponentSide = postState.turn;
    const inCheck = isKingInCheck(postState, opponentSide);
    const opponentHasMove = hasAnyLegalMove(postState, opponentSide);
    const resolvedSan = !opponentHasMove
      ? (inCheck ? san + '#' : 'stalemate')
      : san;

    fetch(MOVE_URL, {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': window.CSRF,
        'Accept':'application/json'
      },
      credentials:'same-origin',
      body: JSON.stringify({
        from:           fromSq,
        to:             toSq,
        san:            resolvedSan,
        resulting_fen:  resultingFen,
      })
    })
    .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
    .then(({ ok, body }) => {
      pendingMoveLock = false;
      if (!ok || !body.ok) {
        setMsg(body.error || 'Server rejected the move — reverting.');
        board.setPosition(lastFen);
        pollOnce();
        return;
      }
      setMsg('', false);
      applyServerState(body);
    })
    .catch(err => {
      pendingMoveLock = false;
      setMsg('Network error — retrying via poll…');
    });
  }

  function applyServerState(s) {
    if (!s) return;
    document.getElementById('move-count').textContent = s.move_count;

    if (s.fen && s.fen !== lastFen) {
      lastFen = s.fen;
      board.setPosition(s.fen);
    }
    if (typeof s.moves_pgn !== 'undefined') {
      updateMoveLog(s.moves_pgn);
    }

    clockState = {
      white: s.white_clock_ms ?? 0,
      black: s.black_clock_ms ?? 0,
      sideToMove: s.side_to_move ?? 'w',
      lastServerSyncAt: Date.now(),
      status: s.status ?? 'active',
    };
    renderClocks();

    const turnLbl = document.getElementById('turn-text');
    const moveHelp = document.getElementById('move-help');
    const pill = document.getElementById('status-pill');

    // ── Cancellation by admin ─────────────────────────────────────────────
    // Either the pairing itself was marked cancelled or the parent tournament
    // is cancelled while pairing is stale. Show a clear overlay and bounce
    // the student back to standings.
    const isCancelled = s.status === 'cancelled' || s.tournament_status === 'cancelled';
    if (isCancelled && !gameOver) {
      releaseTournamentLock();
      turnLbl.textContent = 'Tournament cancelled';
      moveHelp.textContent = 'Returning to tournament page…';
      pill.className = 'lp-pill cancelled';
      pill.textContent = 'Cancelled';

      const overlay = document.getElementById('game-end');
      overlay.classList.add('cancelled');
      document.getElementById('end-headline').textContent = 'Tournament cancelled';
      document.getElementById('end-reason').textContent =
        'The administrator has cancelled this tournament. No result will be recorded.';
      const back = document.getElementById('end-back');
      back.textContent = 'Back to tournaments';
      back.href = STANDINGS_URL;
      overlay.style.display = 'flex';

      // Disable action buttons.
      document.getElementById('btn-draw').disabled = true;
      document.getElementById('btn-resign').disabled = true;

      scheduleStandingsRedirect(s.redirect_url);
      return;
    }

    // Coerce side_to_move — the server returns null when status !== 'active'
    // OR when the FEN is corrupt. For a live game we default to 'w' (white
    // moves first per FIDE) so we never display the wrong "Opponent's move"
    // message on a fresh board.
    const sideToMove = s.side_to_move || (s.status === 'active' ? 'w' : null);

    if (s.status === 'completed') {
      turnLbl.textContent = 'Game over';
      moveHelp.textContent = 'Returning to standings…';
      pill.className = 'lp-pill done';
      pill.textContent = 'Completed';
    } else if (sideToMove === MY_COLOR) {
      turnLbl.textContent = 'Your move';
      moveHelp.textContent = 'Click a piece, then click a destination square.';
      pill.className = 'lp-pill live';
      pill.textContent = 'Live';
    } else {
      turnLbl.textContent = "Opponent's move";
      moveHelp.textContent = 'Please wait for your opponent to move.';
      pill.className = 'lp-pill live';
      pill.textContent = 'Live';
    }

    const drawEl = document.getElementById('draw-offered');
    if (s.draw_offer_by && s.draw_offer_by !== MY_ID && s.status === 'active') {
      drawEl.style.display = 'flex';
    } else {
      drawEl.style.display = 'none';
    }

    if (s.status === 'completed' && !gameOver) {
      releaseTournamentLock();
      const overlay = document.getElementById('game-end');
      overlay.classList.remove('cancelled');
      const head    = document.getElementById('end-headline');
      const reason  = document.getElementById('end-reason');
      let h = 'Game over';
      let r = '';
      if (s.result === '1-0')      { h = MY_COLOR === 'w' ? 'You won!' : 'You lost'; r = 'White wins · 1–0'; }
      else if (s.result === '0-1') { h = MY_COLOR === 'b' ? 'You won!' : 'You lost'; r = 'Black wins · 0–1'; }
      else if (s.result === '0.5-0.5') { h = 'Draw'; r = '½–½'; }
      head.textContent = h;
      reason.textContent = r;
      document.getElementById('end-back').textContent = 'Back to standings';
      overlay.style.display = 'flex';
      document.getElementById('btn-draw').disabled = true;
      document.getElementById('btn-resign').disabled = true;
      scheduleStandingsRedirect(s.redirect_url);
    }
  }

  function pollOnce() {
    if (gameOver) return;
    fetch(POLL_URL + '?_=' + Date.now(), {
      credentials:'same-origin',
      headers:{ 'Accept':'application/json' }
    })
    .then(r => r.json())
    .then(s => {
      lastMoveCount = s.move_count;
      applyServerState(s);
    })
    .catch(()=>{});
  }

  function resignGame() {
    fetch(RESIGN_URL, {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': window.CSRF, 'Accept':'application/json' },
      credentials:'same-origin'
    })
      .then(r => r.json().then(j => ({ ok: r.ok, body: j })).catch(() => ({ ok: r.ok, body: {} })))
      .then(({ ok, body }) => {
        if (!ok || body.ok === false) {
          setMsg(body.error || 'Could not resign the game.');
          return;
        }
        setMsg('You resigned the game.', false);
        pollOnce();
      })
      .catch(() => setMsg('Could not resign the game.'));
  }

  function offerDraw() {
    fetch(DRAW_URL, {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': window.CSRF, 'Accept':'application/json' },
      credentials:'same-origin'
    })
      .then(r => r.json().then(j => ({ ok: r.ok, body: j })).catch(() => ({ ok: r.ok, body: {} })))
      .then(({ ok, body }) => {
        if (!ok || body.ok === false) {
          setMsg(body.error || 'Could not send draw request.');
          return;
        }
        if (body.accepted) {
          setMsg('Draw agreed.', false);
        } else {
          setMsg('Draw offer sent.', false);
        }
        pollOnce();
      })
      .catch(() => setMsg('Could not send draw request.'));
  }

  document.getElementById('btn-draw').addEventListener('click', offerDraw);

  function handleMove(fromSq, toSq, fenAfterMove) {
    if (gameOver) {
      board.setPosition(lastFen);
      return;
    }

    const state = MC.parseFen(lastFen);
    if (state.turn !== MY_COLOR) {
      setMsg(MY_COLOR === 'w' ? 'Please wait for White to move.' : 'Please wait for Black to move.');
      board.setPosition(lastFen);
      return;
    }

    const [fr, ff] = MC.sqToIdx(fromSq);
    const legalSquares = legalMovesForSquare(state, fr, ff).map(([rank, file]) => MC.idxToSq(rank, file));
    if (!legalSquares.includes(toSq)) {
      setMsg('Illegal move.');
      board.setPosition(lastFen);
      return;
    }

    selectedLegalMoves = [];
    submitMove(fromSq, toSq, fenAfterMove);
  }

  document.addEventListener('DOMContentLoaded', () => {
    ensureClockSpans();
    board = new MC.Board('board', {
      size: Math.min(560, Math.max(320, window.innerWidth < 640 ? window.innerWidth - 40 : 560)),
      draggable: true,
      position: STARTING_FEN,
      flipped: '{{ $orient }}' === 'black',
      coords: true,
      getLegalMoves(state, rank, file) {
        return legalMovesForSquare(state, rank, file);
      },
      onMove: handleMove,
    });
    updateMoveLog("{{ addslashes((string) $pairing->moves_pgn) }}");
    renderClocks();
    setInterval(renderClocks, 200);
    pollOnce();
    setInterval(pollOnce, 1000);
  });
</script>
@endsection

@extends('layouts.app')
@section('page-title', 'Watching — '.$tournament->name)

@section('sidebar-nav')
@include('tournaments._sidebar')
@endsection

@push('styles')
<style>
  .sp-wrap{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start}
  @media (max-width: 980px) { .sp-wrap{grid-template-columns:1fr} }
  .sp-clock{font-family:'SF Mono',ui-monospace,Menlo,monospace;font-size:22px;font-weight:800;padding:8px 12px;border-radius:8px;background:#1e293b;color:#fff;display:flex;align-items:center;justify-content:space-between;min-width:140px}
  .sp-clock.warn{background:#9f1239}
  .sp-clock.idle{background:#334155;opacity:.7}
  .sp-clock.live{box-shadow:0 0 0 2px rgba(129,140,248,.45)}
  .sp-clock-label{font-size:12px;font-weight:600;opacity:.7}
  .sp-player{display:flex;align-items:center;gap:10px;margin-bottom:6px}
  .sp-pa{width:32px;height:32px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px}
  .sp-pa.b{background:#0f172a}
  .sp-end{position:absolute;inset:0;background:rgba(15,23,42,.85);color:#fff;display:flex;align-items:center;justify-content:center;flex-direction:column;border-radius:8px;padding:30px;text-align:center;z-index:5}
  .sp-end h2{font-size:24px;font-weight:800;margin-bottom:8px}
  .sp-log{max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px 10px;font-family:monospace;font-size:12.5px;line-height:1.7;background:var(--surface2)}
  .sp-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:700;letter-spacing:.02em}
  .sp-banner{background:var(--color-primary);color:#fff;padding:8px 14px;border-radius:10px;display:flex;align-items:center;gap:10px;margin-bottom:14px;font-weight:600;font-size:13px}
  .sp-banner .dot{width:8px;height:8px;background:#fde68a;border-radius:50%;animation:spDot 1.2s infinite}
  @keyframes spDot{0%,100%{opacity:.5;transform:scale(1)}50%{opacity:1;transform:scale(1.4)}}
  #sp-board{width:min(560px, 100%); margin:0 auto}
</style>
@endpush

@php
  $white = $pairing->white;
  $black = $pairing->black;
@endphp

@section('content')
@include('partials._chess-engine')

<div class="sp-banner">
  <span class="dot"></span>
  <span>Watching live — Round {{ $pairing->round }} · Board {{ $pairing->board }} · {{ $tournament->name }}</span>
  <span style="margin-left:auto;font-size:11px;opacity:.85">{{ strtoupper($tournament->time_control) }} {{ ucfirst($tournament->format) }}</span>
</div>

<x-ui.page-header :title="($white->name ?? '—').' vs '.($black->name ?? '—')">
  <x-slot:actions>
    <x-ui.button :href="$backUrl" variant="secondary">Back to Tournament</x-ui.button>
  </x-slot:actions>
</x-ui.page-header>

<div class="sp-wrap">
  <div>
    {{-- Black header (top) --}}
    <div class="sp-player">
      <div class="sp-pa b">{{ strtoupper(substr($black->name ?? '?', 0, 1)) }}</div>
      <div style="flex:1">
        <div style="font-weight:700">{{ $black->name ?? '—' }}</div>
        <div class="text-xs text-muted">Plays Black</div>
      </div>
      <div id="sp-clk-b" class="sp-clock idle">
        <span class="sp-clock-label">Black</span>
        <span id="sp-time-b">—:—</span>
      </div>
    </div>

    <div style="position:relative">
      <div id="sp-board"></div>
      <div id="sp-end" class="sp-end" style="display:none">
        <h2 id="sp-end-headline">Game over</h2>
        <div id="sp-end-reason" class="mb-3"></div>
        <a class="btn btn-primary" href="{{ $backUrl }}">Back to tournament</a>
      </div>
    </div>

    {{-- White footer (bottom) --}}
    <div class="sp-player mt-3">
      <div class="sp-pa">{{ strtoupper(substr($white->name ?? '?', 0, 1)) }}</div>
      <div style="flex:1">
        <div style="font-weight:700">{{ $white->name ?? '—' }}</div>
        <div class="text-xs text-muted">Plays White</div>
      </div>
      <div id="sp-clk-w" class="sp-clock idle">
        <span class="sp-clock-label">White</span>
        <span id="sp-time-w">—:—</span>
      </div>
    </div>
  </div>

  <aside>
    <div class="card mb-3">
      <div class="card-title">Game</div>
      <div class="text-sm mb-2">
        <span id="sp-status" class="badge badge-live">● Live</span>
        <span class="text-muted ms-2" id="sp-turn">Loading…</span>
      </div>
      <div class="text-xs text-muted mb-2">
        Time control <strong>{{ $tournament->time_control }}</strong> · {{ ucfirst($tournament->format) }}
      </div>
      <div class="text-xs text-muted mb-3">Move <span id="sp-move-count">{{ $pairing->move_count }}</span></div>
      @if($pairing->isComplete())
        <a class="btn btn-sm btn-outline w-full" href="{{ route(auth()->user()->role === 'student' ? 'student.tournaments.download-pgn' : auth()->user()->role.'.tournaments.download-pgn', [$tournament, $pairing]) }}">Download PGN</a>
      @endif
    </div>

    <div class="card">
      <div class="card-title">Moves</div>
      <div class="sp-log" id="sp-log">{{ $pairing->moves_pgn ?: '—' }}</div>
    </div>
  </aside>
</div>

<script>
(function () {
  const POLL_URL = "{{ route(auth()->user()->role === 'student' ? 'student.tournaments.spectate-poll' : auth()->user()->role.'.tournaments.spectate-poll', [$tournament, $pairing]) }}";
  const STARTING_FEN = "{{ $pairing->current_fen }}";
  const POLL_MS = 1500;

  const board = new MiniChess.Board('sp-board', {
    size: 520,
    position: STARTING_FEN,
    draggable: false,   // read-only, no click handlers
    coords: true,
  });

  let ended = false;
  let lastFen = STARTING_FEN;

  function fmtClock(ms) {
    if (ms == null) return '—:—';
    const total = Math.max(0, Math.floor(ms / 1000));
    const m = Math.floor(total / 60);
    const s = total % 60;
    return m + ':' + String(s).padStart(2, '0');
  }

  function applyClocks(s) {
    const wEl = document.getElementById('sp-clk-w');
    const bEl = document.getElementById('sp-clk-b');
    document.getElementById('sp-time-w').textContent = fmtClock(s.white_clock_ms);
    document.getElementById('sp-time-b').textContent = fmtClock(s.black_clock_ms);
    wEl.classList.remove('warn','idle','live');
    bEl.classList.remove('warn','idle','live');
    if (s.status === 'completed') {
      wEl.classList.add('idle'); bEl.classList.add('idle');
      return;
    }
    if (s.side_to_move === 'w') { wEl.classList.add('live'); bEl.classList.add('idle'); }
    else                          { bEl.classList.add('live'); wEl.classList.add('idle'); }
    if (s.white_clock_ms <= 30000) wEl.classList.add('warn');
    if (s.black_clock_ms <= 30000) bEl.classList.add('warn');
  }

  function showEnd(s) {
    if (ended) return;
    ended = true;
    const head = document.getElementById('sp-end-headline');
    const reas = document.getElementById('sp-end-reason');
    let h = 'Game over', r = '';
    if (s.result === '1-0') { h = 'White wins'; r = '1–0'; }
    else if (s.result === '0-1') { h = 'Black wins'; r = '0–1'; }
    else if (s.result === '0.5-0.5') { h = 'Draw'; r = '½–½'; }
    head.textContent = h;
    reas.textContent = r;
    document.getElementById('sp-end').style.display = 'flex';
    document.getElementById('sp-status').className = 'badge badge-done';
    document.getElementById('sp-status').textContent = 'Completed';
    document.getElementById('sp-turn').textContent = 'Game finished';
  }

  function applyState(s) {
    if (s.fen && s.fen !== lastFen) {
      lastFen = s.fen;
      board.setPosition(s.fen);
    }
    document.getElementById('sp-move-count').textContent = s.move_count ?? 0;
    document.getElementById('sp-log').textContent = (s.moves_pgn && s.moves_pgn.trim()) ? s.moves_pgn : '—';
    applyClocks(s);

    if (s.status === 'completed') {
      showEnd(s);
      return;
    }
    if (s.side_to_move === 'w') {
      document.getElementById('sp-turn').textContent = "White to move";
    } else {
      document.getElementById('sp-turn').textContent = "Black to move";
    }
  }

  async function tick() {
    if (ended) return;
    try {
      const r = await fetch(POLL_URL + '?_=' + Date.now(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      });
      if (!r.ok) return;
      const s = await r.json();
      applyState(s);
    } catch (e) { /* network blip — retry next tick */ }
  }

  tick();
  const interval = setInterval(() => { if (!ended) tick(); }, POLL_MS);
})();
</script>
@endsection

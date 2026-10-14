{{--
    Stockfish engine panel.
    The host page provides a global function window[$opts['getFenName']]() that returns the FEN to analyse.
    Default is window.engineGetFen.
--}}
@php
  $engHostId    = $engHostId    ?? 'eng';
  $getFenName   = $getFenName   ?? 'engineGetFen';
@endphp

@once
@push('styles')
<style>
.eng-card    { padding:0; overflow:hidden }
.eng-head    { padding:9px 12px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600 }
.eng-body    { padding:10px 12px; display:flex; flex-direction:column; gap:8px }
.eng-status  { font-size:11px; color:#64748b }
.eng-bar     { position:relative; height:14px; background:#0f172a; border-radius:99px; overflow:hidden; border:1px solid #1e293b }
.eng-bar-fill{ position:absolute; left:0; top:0; bottom:0; background:#e2e8f0; transition:width .25s }
.eng-bar-num { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; mix-blend-mode:difference; color:#fff }
.eng-best    { display:flex; align-items:center; gap:8px; font-size:13px }
.eng-best-mv { font-family:ui-monospace, monospace; font-weight:700; color:#1e40af; background:#dbeafe; padding:2px 8px; border-radius:6px }
.eng-pv      { font-family:ui-monospace, monospace; font-size:12px; color:#475569; line-height:1.5; max-height:60px; overflow:auto }
.eng-pv .pvm { display:inline-block; padding:1px 5px; margin-right:3px; border-radius:4px; background:#f1f5f9 }
.eng-warn    { font-size:11px; color:#92400e; background:#fef3c7; border:1px solid #fde68a; padding:6px 8px; border-radius:6px; line-height:1.4 }
</style>
@endpush
@endonce

<div class="card card-sm eng-card">
  <div class="eng-head">
    <span>Engine</span>
    <select id="{{ $engHostId }}-depth" class="btn btn-xs btn-ghost" style="padding:2px 6px;font-size:11px">
      <option value="10">d 10</option>
      <option value="14" selected>d 14</option>
      <option value="18">d 18</option>
      <option value="22">d 22</option>
    </select>
    <button id="{{ $engHostId }}-toggle" class="btn btn-xs btn-primary" style="margin-left:auto">Turn ON</button>
  </div>
  <div class="eng-body">
    <div id="{{ $engHostId }}-status" class="eng-status">Engine off — turn on to analyse the current position.</div>
    <div class="eng-bar"><div id="{{ $engHostId }}-bar-fill" class="eng-bar-fill" style="width:50%"></div><span id="{{ $engHostId }}-bar-num" class="eng-bar-num">0.00</span></div>
    <div class="eng-best">Best: <span id="{{ $engHostId }}-best" class="eng-best-mv">—</span><span id="{{ $engHostId }}-depth-lbl" class="text-xs text-muted">depth 0</span></div>
    <div id="{{ $engHostId }}-pv" class="eng-pv"></div>
  </div>
</div>

@once
@push('scripts')
<script>
// MiniChessEngine — load Stockfish.js once and analyse via UCI.
// API: window.MiniChessEngine.attach({ hostId, getFen, depth })
window.MiniChessEngine = (function(){
  const SF_URL = 'https://cdn.jsdelivr.net/npm/stockfish.js@10.0.2/stockfish.js';
  let workerPromise = null;
  let engine = null;
  let listeners = [];   // [{hostId, on:(line)=>void}]

  function getEngine() {
    if (workerPromise) return workerPromise;
    workerPromise = (async () => {
      const code = await fetch(SF_URL).then(r => {
        if (!r.ok) throw new Error('CDN load failed (' + r.status + ')');
        return r.text();
      });
      const blob = new Blob([code], { type: 'application/javascript' });
      const w = new Worker(URL.createObjectURL(blob));
      w.onmessage = (e) => {
        const line = (typeof e.data === 'string') ? e.data : '';
        listeners.forEach(l => l.on(line));
      };
      w.postMessage('uci');
      // Wait for uciok
      await new Promise((res, rej) => {
        const t = setTimeout(() => { listeners = listeners.filter(x => x !== L); rej(new Error('engine timeout')); }, 15000);
        const L = { on: (line) => { if (line === 'uciok') { clearTimeout(t); listeners = listeners.filter(x => x !== L); res(); } } };
        listeners.push(L);
      });
      w.postMessage('isready');
      // Wait for readyok
      await new Promise((res) => {
        const L = { on: (line) => { if (line === 'readyok') { listeners = listeners.filter(x => x !== L); res(); } } };
        listeners.push(L);
      });
      engine = w;
      return w;
    })();
    return workerPromise;
  }

  // Apply a coord move (e2e4 / e7e8q) to a state and return new FEN — for SAN of PV display.
  function applyCoord(state, mv) {
    if (!mv || mv.length < 4) return null;
    const from = mv.slice(0, 2), to = mv.slice(2, 4), promo = mv.slice(4);
    try {
      const before = MiniChess.toFen(state);
      const san = MiniChess.moveToSan(MiniChess.parseFen(before), from, to, null);
      const ok = MiniChess.applySAN(state, san);
      if (ok) return { fen: MiniChess.toFen(state), san };
    } catch (e) {}
    // Fallback: brute-force the move on the board.
    const [sr, sf] = MiniChess.sqToIdx(from);
    const [tr, tf] = MiniChess.sqToIdx(to);
    const piece = state.board[7 - sr][sf];
    if (!piece) return null;
    state.board[7 - sr][sf] = null;
    state.board[7 - tr][tf] = promo
      ? (state.turn === 'w' ? promo.toUpperCase() : promo.toLowerCase())
      : piece;
    state.turn = state.turn === 'w' ? 'b' : 'w';
    return { fen: MiniChess.toFen(state), san: from + to + promo };
  }

  function pvToSan(fen, pvCoord) {
    if (!pvCoord) return [];
    const moves = pvCoord.trim().split(/\s+/).slice(0, 6);
    const state = MiniChess.parseFen(fen);
    const out = [];
    for (const mv of moves) {
      const r = applyCoord(state, mv);
      if (!r) break;
      out.push(r.san);
    }
    return out;
  }

  function attach(opts) {
    const hostId = opts.hostId;
    const getFen = opts.getFen;
    let depth    = opts.depth || 14;
    let lastFen  = '';
    let on       = false;
    let lastBest = '';
    let lastDepth = 0;

    const $   = (suf) => document.getElementById(hostId + '-' + suf);
    const setStatus = (s) => { const el = $('status'); if (el) el.textContent = s; };

    function parse(line) {
      if (line.startsWith('info ') && line.includes('pv ')) {
        const m = {};
        const tokens = line.split(' ');
        for (let i = 0; i < tokens.length; i++) {
          const t = tokens[i];
          if (t === 'depth')      m.depth = parseInt(tokens[++i], 10);
          else if (t === 'score') {
            const kind = tokens[++i];
            const val  = parseInt(tokens[++i], 10);
            m.score = { kind, val };
          } else if (t === 'pv')  { m.pv = tokens.slice(i + 1).join(' '); break; }
        }
        if (!m.pv || !m.score) return;
        lastDepth = m.depth || lastDepth;
        // Score: white's perspective. UCI returns from side-to-move POV; convert.
        const turn = MiniChess.parseFen(lastFen).turn;
        let cp = 0, label = '';
        if (m.score.kind === 'cp') { cp = m.score.val * (turn === 'w' ? 1 : -1); label = (cp / 100).toFixed(2); if (cp > 0) label = '+' + label; }
        else if (m.score.kind === 'mate') { const v = m.score.val * (turn === 'w' ? 1 : -1); label = '#' + v; cp = v > 0 ? 9999 : -9999; }
        // Bar: 50% = 0, capped ±5
        const pct = Math.max(0, Math.min(100, 50 + (cp / 10)));
        const fillEl = $('bar-fill'); if (fillEl) fillEl.style.width = pct + '%';
        const numEl  = $('bar-num');  if (numEl)  numEl.textContent  = label;
        $('depth-lbl').textContent = 'depth ' + (m.depth || 0);
        // PV → SAN
        const sans = pvToSan(lastFen, m.pv);
        $('pv').innerHTML = sans.map(s => '<span class="pvm">' + s.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</span>').join('');
        if (sans[0]) { $('best').textContent = sans[0]; lastBest = sans[0]; }
      } else if (line.startsWith('bestmove')) {
        const mv = line.split(' ')[1];
        if (mv && mv !== '(none)') {
          // Convert coord to SAN for clarity
          const state = MiniChess.parseFen(lastFen);
          const r = applyCoord(state, mv);
          if (r) { $('best').textContent = r.san; lastBest = r.san; }
          else   { $('best').textContent = mv;     lastBest = mv; }
        }
        setStatus('Idle — depth ' + lastDepth + ' reached.');
      }
    }

    async function start() {
      try {
        setStatus('Loading Stockfish (~3MB)…');
        const w = await getEngine();
        listeners.push({ hostId, on: parse });
        on = true;
        setStatus('Engine ready.');
        $('toggle').textContent = 'Turn OFF';
        $('toggle').classList.remove('btn-primary');
        $('toggle').classList.add('btn-danger');
        analyse();
      } catch (e) {
        setStatus('Engine unavailable: ' + (e.message || 'unknown'));
        const wEl = document.createElement('div');
        wEl.className = 'eng-warn';
        wEl.textContent = 'The browser could not load Stockfish from the public CDN. Engine analysis is offline.';
        document.querySelector('#' + hostId + '-status').parentElement.appendChild(wEl);
      }
    }

    function stop() {
      if (!on) return;
      on = false;
      try { engine && engine.postMessage('stop'); } catch (e) {}
      listeners = listeners.filter(l => l.hostId !== hostId);
      $('toggle').textContent = 'Turn ON';
      $('toggle').classList.add('btn-primary');
      $('toggle').classList.remove('btn-danger');
      setStatus('Engine off.');
    }

    function analyse() {
      if (!on || !engine) return;
      const fen = getFen();
      if (!fen || fen === lastFen) return;
      lastFen = fen;
      lastDepth = 0;
      $('best').textContent = '…';
      $('pv').innerHTML = '';
      $('depth-lbl').textContent = 'thinking…';
      try {
        engine.postMessage('stop');
        engine.postMessage('position fen ' + fen);
        engine.postMessage('go depth ' + depth);
        setStatus('Analysing depth ' + depth + '…');
      } catch (e) { setStatus('Engine error: ' + e.message); }
    }

    $('toggle').addEventListener('click', () => { on ? stop() : start(); });
    $('depth').addEventListener('change', (e) => { depth = parseInt(e.target.value, 10); if (on) { lastFen = ''; analyse(); } });

    // Poll the host's FEN every 700ms while engine is on.
    setInterval(() => { if (on) analyse(); }, 700);

    return { start, stop, analyse: () => { lastFen = ''; analyse(); } };
  }

  return { attach: attach, getEngine: getEngine, pvToSan: pvToSan, applyCoord: applyCoord };
})();
</script>
@endpush
@endonce

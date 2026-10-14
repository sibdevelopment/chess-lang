{{--
    Reusable multi-position PGN viewer.
    Required vars:
      $pgn          — raw PGN string
      $chapterName  — string for the title above the move strip
    Optional vars:
      $boardSize    — int, default 480
      $hostId       — string, default 'cv' (use unique id if multiple on a page)
      $readOnly     — bool, default true (no broadcast / no sub-variation persistence)
      $positionsSide — 'left' or 'right', default 'left'
      $leftSlot      — optional Blade-rendered HTML shown in the left column
      $movePanelBelow — bool, default false; renders move/annotation panel below the main grid
--}}
@php
  $hostId         = $hostId         ?? 'cv';
  $boardSize      = $boardSize      ?? 480;
  $positionsSide  = $positionsSide  ?? 'left';
  $movePanelBelow = $movePanelBelow ?? false;
@endphp

{{-- Shared chess-UI design tokens (loaded once even if viewer is rendered multiple times). --}}
@include('partials._chess-ui')

@once
@push('styles')
<style>
.cv-wrap        { display:grid; grid-template-columns: 280px minmax(420px, 1fr) 360px; gap:14px; align-items:flex-start; }
@media (max-width: 980px) { .cv-wrap { grid-template-columns: 1fr; } }
.cv-stack       { display:flex; flex-direction:column; gap:10px; }
.cv-pn-list     { max-height:520px; overflow:auto }
.cv-pn-row      { padding:9px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:13px; color:#334155 }
.cv-pn-row:hover{ background:#f1f5f9 }
.cv-pn-row.active{ background:#dbeafe; color:#1e40af; font-weight:700; border-left:3px solid #2563eb; padding-left:9px }
.cv-pn-num      { font-family:ui-monospace, monospace; font-size:11px; color:#64748b; margin-right:6px }
.cv-mv-strip    { font-size:13px; line-height:1.7; color:#0f172a; max-height:200px; overflow:auto; padding:4px 2px }
.cv-mv-strip .mn{ color:#64748b; margin-right:3px; font-family:ui-monospace, monospace; font-size:11px }
.cv-mv-strip .mv{ display:inline-block; padding:1px 5px; border-radius:4px; cursor:pointer; margin-right:3px }
.cv-mv-strip .mv:hover{ background:#f1f5f9 }
.cv-mv-strip .mv.active{ background:#dbeafe; color:#1e40af; font-weight:700 }
.cv-mv-empty    { color:#94a3b8; font-style:italic; font-size:12px }
.cv-annot       { font-size:13px; color:#713f12; line-height:1.5; white-space:pre-wrap; word-wrap:break-word }
.cv-annot-empty { color:#94a3b8; font-style:italic; font-size:12px }
.cv-rec-var     { display:inline-block; padding:0 4px; margin:0 4px; font-style:italic; color:#475569; border-left:2px solid #cbd5e1 }
.cv-rec-var .mv { font-style:normal }
</style>
@endpush
@endonce

@php
  $positionsPanel
    = '<div class="card card-sm" style="padding:0">'
    .   '<div style="padding:10px 12px;border-bottom:1px solid var(--border);font-size:13px;font-weight:600">'
    .     'Positions <span id="'.$hostId.'-pn-count" class="text-xs text-muted">0</span>'
    .   '</div>'
    .   '<div id="'.$hostId.'-pn-list" class="cv-pn-list">'
    .     '<div class="cv-mv-empty" style="padding:14px 12px">Loading…</div>'
    .   '</div>'
    . '</div>';

  $movePanel
    = '<div style="display:flex;flex-direction:column;gap:10px">'
    .   '<div class="card card-sm">'
    .     '<div class="card-title" style="font-size:13px">'.$chapterName.'</div>'
    .     '<div id="'.$hostId.'-pos-name" style="font-size:13px;font-weight:700;color:#1e293b">No position selected</div>'
    .     '<div id="'.$hostId.'-pos-sub" style="font-size:11px;color:#64748b;margin-top:1px;display:none"></div>'
    .     '<hr style="border:none;border-top:1px solid #e2e8f0;margin:10px 0 8px">'
    .     '<div id="'.$hostId.'-mv-strip" class="cv-mv-strip">'
    .       '<span class="cv-mv-empty">Pick a position from the left.</span>'
    .     '</div>'
    .   '</div>'
    .   '<div class="card card-sm" style="background:#fef9c3;border-color:#fde047">'
    .     '<div class="card-title" style="font-size:13px;color:#854d0e">Annotation</div>'
    .     '<div id="'.$hostId.'-annot" class="cv-annot">'
    .       '<span class="cv-annot-empty">No annotation for this position.</span>'
    .     '</div>'
    .   '</div>'
    . '</div>';
@endphp

<div class="cv-wrap" id="{{ $hostId }}-root">
  @if($positionsSide === 'left')
    {!! $positionsPanel !!}
  @else
    <div class="cv-stack">
      @isset($leftSlot){!! $leftSlot !!}@endisset
    </div>
  @endif

  {{-- Board --}}
  <div style="display:flex;flex-direction:column;align-items:center;gap:10px">
    <div id="{{ $hostId }}-board"></div>
    <div class="board-controls">
      <button class="btn btn-sm btn-ghost" data-cv-act="start">⏮</button>
      <button class="btn btn-sm btn-ghost" data-cv-act="prev">◀</button>
      <span id="{{ $hostId }}-counter" style="font-size:12px;color:var(--text3);padding:0 10px">0/0</span>
      <button class="btn btn-sm btn-ghost" data-cv-act="next">▶</button>
      <button class="btn btn-sm btn-ghost" data-cv-act="end">⏭</button>
      <button class="btn btn-sm btn-ghost" data-cv-act="flip">⇅ Flip</button>
    </div>
    <div class="flex items-center gap-2" style="width:{{ $boardSize }}px;max-width:100%">
      <div id="{{ $hostId }}-turn-dot" style="width:10px;height:10px;border-radius:50%;background:#fff;border:1.5px solid #888"></div>
      <span id="{{ $hostId }}-turn-label" style="font-size:12px;color:var(--text2)">White to move</span>
    </div>
  </div>

  @if($positionsSide === 'left')
    <div class="cv-stack">
      {!! $movePanel !!}
      @isset($extraSlot){{ $extraSlot }}@endisset
    </div>
  @else
    {!! $positionsPanel !!}
  @endif
</div>

@if($movePanelBelow)
  <div class="mt-3">
    {!! $movePanel !!}
    @isset($extraSlot){{ $extraSlot }}@endisset
  </div>
@endif

@once
@push('scripts')
@include('partials._chess-engine')
@endpush
@endonce

@push('scripts')
<script>
(function(){
  const HOST = '{{ $hostId }}';
  const PGN  = @json($pgn);
  const SIZE = {{ (int) $boardSize }};
  const $    = (suffix) => document.getElementById(HOST + '-' + suffix);
  const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

  let positions = [];
  let posIdx    = -1;
  let fens      = [STARTING_FEN];
  let idx       = 0;

  function parseAnnot(raw) {
    const text = String(raw || '');
    const arrows = [];
    const highlights = [];
    let note = text;
    note = note.replace(/\[%cal\s+([^\]]+)\]/gi, (_, payload) => {
      payload.split(',').map(s => s.trim()).filter(Boolean).forEach(item => {
        const m = item.match(/^([GRBY])([a-h][1-8])([a-h][1-8])$/i);
        if (m) {
          const color = ({G:'green',R:'red',B:'blue',Y:'yellow'})[m[1].toUpperCase()] || 'green';
          arrows.push({ from: m[2], to: m[3], color });
        }
      });
      return '';
    });
    note = note.replace(/\[%csl\s+([^\]]+)\]/gi, (_, payload) => {
      payload.split(',').map(s => s.trim()).filter(Boolean).forEach(item => {
        const m = item.match(/^([GRBY])([a-h][1-8])$/i);
        if (m) {
          const color = ({G:'green',R:'red',B:'blue',Y:'yellow'})[m[1].toUpperCase()] || 'green';
          highlights.push({ sq: m[2], color });
        }
      });
      return '';
    });
    return { note: note.trim(), arrows, highlights };
  }

  const board = new MiniChess.Board(HOST + '-board', {
    size: SIZE,
    draggable: false,
    position: STARTING_FEN,
  });

  function render() {
    const fen = fens[idx];
    board.setPosition(fen);
    const state = MiniChess.parseFen(fen);
    $('counter').textContent  = idx + '/' + (fens.length - 1);
    $('turn-dot').style.background = state.turn === 'w' ? '#fff' : '#222';
    $('turn-label').textContent = state.turn === 'w' ? 'White to move' : 'Black to move';
    renderStrip();
    renderAnnot();
    renderListActive();
  }

  function renderListActive() {
    document.querySelectorAll('#' + HOST + '-pn-list .cv-pn-row').forEach(r => {
      r.classList.toggle('active', parseInt(r.dataset.idx, 10) === posIdx);
    });
  }

  function loadPosition(i) {
    if (i < 0 || i >= positions.length) return;
    posIdx = i;
    const p = positions[i];
    fens = [p.startFen].concat((p.mainLine || []).map(m => m.fen));
    idx = 0;
    $('pos-name').textContent = p.name || ('Position ' + (i + 1));
    const sub = $('pos-sub');
    sub.textContent = (p.mainLine && p.mainLine.length)
      ? (p.mainLine.length + ' recorded move' + (p.mainLine.length === 1 ? '' : 's'))
      : 'No recorded moves';
    sub.style.display = 'block';
    render();
  }

  function jump(n) { idx = Math.max(0, Math.min(n, fens.length - 1)); render(); }

  function renderStrip() {
    const el = $('mv-strip');
    const p = positions[posIdx];
    if (!p || !p.mainLine || !p.mainLine.length) {
      el.innerHTML = '<span class="cv-mv-empty">No recorded moves for this position.</span>';
      return;
    }
    el.innerHTML = '';
    let lastMn = -1;
    p.mainLine.forEach((node, i) => {
      if (node.moveNumber !== lastMn) {
        const mn = document.createElement('span');
        mn.className = 'mn';
        mn.textContent = node.moveNumber + (node.side === 'w' ? '.' : '...');
        el.appendChild(mn);
        lastMn = node.moveNumber;
      }
      const mv = document.createElement('span');
      mv.className = 'mv' + ((i + 1) === idx ? ' active' : '');
      mv.textContent = node.san + (node.nag || '');
      mv.addEventListener('click', () => jump(i + 1));
      el.appendChild(mv);
      if (node.variations && node.variations.length) {
        node.variations.forEach(varLine => {
          const w = document.createElement('span');
          w.className = 'cv-rec-var';
          let lvm = -1;
          varLine.forEach(v => {
            if (v.moveNumber !== lvm) {
              const m = document.createElement('span');
              m.className = 'mn';
              m.textContent = v.moveNumber + (v.side === 'w' ? '.' : '...');
              w.appendChild(m);
              lvm = v.moveNumber;
            }
            const mv2 = document.createElement('span');
            mv2.className = 'mv';
            mv2.textContent = v.san;
            mv2.title = 'Recorded variation (preview only)';
            mv2.addEventListener('click', () => { board.setPosition(v.fen); });
            w.appendChild(mv2);
          });
          el.appendChild(w);
        });
      }
    });
  }

  function renderAnnot() {
    const el = $('annot');
    const p = positions[posIdx];
    if (!p) { el.innerHTML = '<span class="cv-annot-empty">No position selected.</span>'; return; }
    let note = '';
    if (idx > 0 && p.mainLine[idx - 1]) note = (p.mainLine[idx - 1].comment || '').trim();
    if (!note) note = (p.annotation || '').trim();
    const parsed = parseAnnot(note);
    board.setOverlay(parsed.arrows, parsed.highlights);
    el.textContent = parsed.note || '';
    if (!parsed.note) el.innerHTML = '<span class="cv-annot-empty">No annotation for this position.</span>';
  }

  function buildList() {
    const el = $('pn-list');
    $('pn-count').textContent = positions.length;
    if (!positions.length) {
      el.innerHTML = '<div class="cv-mv-empty" style="padding:14px 12px">No positions found in this chapter.</div>';
      return;
    }
    el.innerHTML = '';
    positions.forEach((p, i) => {
      const r = document.createElement('div');
      r.className = 'cv-pn-row';
      r.dataset.idx = i;
      r.innerHTML = '<span class="cv-pn-num">' + (i + 1) + '.</span>'
                  + (p.name || ('Position ' + (i + 1))).replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
      r.addEventListener('click', () => loadPosition(i));
      el.appendChild(r);
    });
  }

  // Wire control buttons by data attribute (no global function leakage)
  $('root').querySelectorAll('[data-cv-act]').forEach(btn => {
    btn.addEventListener('click', () => {
      const a = btn.dataset.cvAct;
      if (a === 'start') jump(0);
      else if (a === 'prev')  jump(idx - 1);
      else if (a === 'next')  jump(idx + 1);
      else if (a === 'end')   jump(fens.length - 1);
      else if (a === 'flip')  board.flip();
    });
  });

  // Boot
  positions = MiniChess.parsePGNFile(PGN) || [];
  buildList();
  if (positions.length) loadPosition(0);
  else render();
})();
</script>
@endpush

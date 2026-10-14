@extends('layouts.app')
@section('page-title', $analysis->name)

@section('sidebar-nav') @include('student._sidebar') @endsection

{{-- Shared chess-UI design tokens (single source of truth for board / eval / classification colours). --}}
@include('partials._chess-ui')

@push('styles')
<style>
/* ─── Lichess-Study inspired palette & layout ─────────────────────── */
/* Local sizing aliases defer to shared tokens so a change in _chess-ui.blade.php cascades. */
:root { --an-board: var(--cui-board); --an-eval: var(--cui-eval); }
.an-shell      { display:grid; grid-template-columns: 230px minmax(calc(var(--an-board) + var(--an-eval) + 14px), 1fr) 420px; gap:14px; align-items:flex-start }
@media (max-width: 1320px) { .an-shell { grid-template-columns: 220px 1fr; } .an-right { grid-column: 1 / -1 } }
@media (max-width:  860px) { .an-shell { grid-template-columns: 1fr; } }

.an-page-head  { display:flex; align-items:center; gap:14px; padding:12px 16px; background:#fff; border:1px solid var(--border); border-radius:10px; margin-bottom:14px; flex-wrap:wrap }
.an-page-head h1 { font-size:17px; font-weight:700; color:#0f172a; letter-spacing:-.01em; margin:0; line-height:1.3 }
.an-page-head .breadcrumb { font-size:11px; color:#94a3b8 }
.an-page-head .breadcrumb a { color:#64748b }
.an-page-head .breadcrumb a:hover { color:#4f46e5 }

/* Save state pill */
.savepill { font-size:11px; padding:4px 10px; border-radius:99px; font-weight:600; display:inline-flex; align-items:center; gap:6px }
.savepill.saved { background:#ecfdf5; color:#065f46 }
.savepill.dirty { background:#fef3c7; color:#92400e }
.savepill.saving { background:#dbeafe; color:#1e40af }
.savepill .dot { width:6px; height:6px; border-radius:50%; background:currentColor; opacity:.7 }

/* Positions (left) */
.an-pos-card   { padding:0; overflow:hidden; background:#fff; border:1px solid var(--border); border-radius:10px }
.an-pos-head   { padding:10px 14px; border-bottom:1px solid #f1f5f9; font-size:12px; font-weight:600; color:#64748b; display:flex; align-items:center; gap:8px }
.an-pos-list   { max-height:560px; overflow:auto }
.an-pos-row    { padding:10px 14px; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:12px; color:#475569; display:flex; flex-direction:column; gap:3px; transition:background .12s }
.an-pos-row:hover { background:#f8fafc }
.an-pos-row.active { background:#f5f3ff; color:#4338ca; font-weight:600; border-left:3px solid #6366f1; padding-left:11px }
.an-pos-meta   { font-size:10px; color:#94a3b8; display:flex; gap:6px; align-items:center }

/* Centre (board, eval, controls) — sized via --an-board so it never overflows */
.an-center        { display:flex; flex-direction:column; gap:10px; align-items:stretch; min-width:0 }
.an-board-wrap    { width:calc(var(--an-board) + var(--an-eval) + 8px); margin:0 auto; display:flex; flex-direction:column; gap:10px }
.an-board-row     { display:flex; gap:8px; align-items:flex-start }
.an-evalbar       { width:var(--an-eval); height:var(--an-board); background:#262626; border-radius:3px; overflow:hidden; position:relative; flex-shrink:0; border:1px solid #1f2937 }
.an-evalbar-fill  { position:absolute; left:0; right:0; bottom:0; background:#fafafa; transition:height .35s cubic-bezier(.4,0,.2,1) }
.an-evalbar-num   { position:absolute; left:0; right:0; top:50%; transform:translateY(-50%); text-align:center; font-size:9px; font-weight:700; mix-blend-mode:difference; color:#fff; pointer-events:none; font-family:ui-monospace, monospace }
#an-board         { width:var(--an-board); height:var(--an-board); flex-shrink:0 }
.an-tools         { width:100%; display:flex; justify-content:space-between; align-items:center; gap:10px }
.an-tools .left   { display:flex; flex-direction:column; min-width:0; flex:1 }
.an-tools .gtitle { font-size:14px; font-weight:600; color:#0f172a; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
.an-tools .gsub   { font-size:11px; color:#64748b }
.an-tools .right  { display:flex; align-items:center; gap:8px; flex-shrink:0 }
.eval-pill        { font-family:ui-monospace, monospace; font-size:12px; font-weight:600; padding:4px 10px; border-radius:6px; background:#1f2937; color:#fff; min-width:58px; text-align:center; letter-spacing:-.01em }
.eval-pill.up     { background:#fafafa; color:#0f172a; border:1px solid #e2e8f0 }
.eval-pill.down   { background:#1f2937; color:#fff }
.eval-pill.neutral{ background:#f1f5f9; color:#475569; border:1px solid #e2e8f0 }
.an-controls      { width:100%; display:flex; gap:4px; padding:6px; background:#fff; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; justify-content:center; align-items:center }
.an-controls .ctl { background:transparent; border:none; padding:6px 10px; font-size:14px; cursor:pointer; color:#475569; border-radius:5px; min-width:34px; transition:all .1s }
.an-controls .ctl:hover { background:#f1f5f9; color:#0f172a }
.an-controls .ctl:active { transform:scale(.95) }
.an-controls .ctl svg { width:16px; height:16px; display:block }
.an-controls .ctr { font-family:ui-monospace, monospace; font-size:12px; color:#64748b; padding:6px 12px; min-width:60px; text-align:center }

/* Right column */
.an-right         { display:flex; flex-direction:column; gap:12px }

/* Tabs */
.an-tabs          { width:100% }
.an-tab           { flex:1 }
.an-panel         { display:none }
.an-panel.active  { display:block }

/* ─── Lichess-style PGN-flow move tree ──────────────────────────────── */
.an-tree          { background:#fff; border:1px solid var(--border); border-radius:10px; padding:12px 14px; max-height:340px; overflow:auto; line-height:1.7; font-size:13px; color:#334155 }
.an-tree-empty    { text-align:center; color:#94a3b8; font-size:12px; font-style:italic; padding:24px 0 }
.an-tree .mn      { font-family:ui-monospace, monospace; font-size:11px; color:#94a3b8; margin:0 4px 0 2px; font-weight:600 }
.an-tree .mv      { display:inline-block; padding:1px 6px; border-radius:4px; cursor:pointer; font-family:ui-monospace, monospace; font-size:13px; color:#0f172a; transition:background .1s, color .1s }
.an-tree .mv:hover{ background:#eef2ff }
.an-tree .mv.active { background:#4338ca; color:#fff; font-weight:700 }
.an-tree .mv .nag   { font-weight:700; margin-left:1px }
.an-tree .mv.brilliant { color:#065f46 } .an-tree .mv.brilliant.active{ background:#10b981; color:#fff }
.an-tree .mv.best     { color:#15803d } .an-tree .mv.best.active     { background:#16a34a; color:#fff }
.an-tree .mv.good     { color:#0f766e } .an-tree .mv.good.active     { background:#14b8a6; color:#fff }
.an-tree .mv.inaccuracy{ color:#854d0e } .an-tree .mv.inaccuracy.active{ background:#ca8a04; color:#fff }
.an-tree .mv.mistake  { color:#9a3412 } .an-tree .mv.mistake.active  { background:#ea580c; color:#fff }
.an-tree .mv.blunder  { color:#991b1b } .an-tree .mv.blunder.active  { background:#dc2626; color:#fff }
.an-tree .mv.user     { font-style:italic; border-bottom:1px dashed #94a3b8 }
.an-tree .var-paren   { color:#94a3b8; font-family:ui-monospace, monospace; padding:0 2px }
.an-tree .var-line    { color:#64748b; font-style:italic; padding-left:14px; border-left:2px solid #e2e8f0; margin:3px 0 3px 4px; font-size:12px; display:block; line-height:1.7 }
.an-tree .var-line .mv { font-style:normal }
.an-tree .cmt-inline  { color:#475569; font-size:12px; font-style:italic; background:#fefce8; border:1px solid #fef9c3; padding:1px 6px; border-radius:3px; margin:0 3px }

/* Move actions card */
.move-actions     { background:#fff; border:1px solid var(--border); border-radius:10px; padding:12px 14px }
.move-actions .ma-head { display:flex; align-items:center; gap:8px; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid #f1f5f9 }
.move-actions .ma-icon { width:28px; height:28px; border-radius:7px; background:#1f2937; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-family:ui-monospace, monospace; font-size:14px; flex-shrink:0 }
.move-actions .ma-icon.brilliant { background:#10b981 }
.move-actions .ma-icon.best  { background:#16a34a }
.move-actions .ma-icon.good  { background:#14b8a6 }
.move-actions .ma-icon.inaccuracy { background:#ca8a04 }
.move-actions .ma-icon.mistake { background:#ea580c }
.move-actions .ma-icon.blunder { background:#dc2626 }
.move-actions .ma-title { flex:1; min-width:0 }
.move-actions .ma-lbl { font-size:12px; color:#64748b; font-weight:600 }
.move-actions .ma-mv  { font-size:14px; font-weight:700; color:#0f172a; font-family:ui-monospace, monospace }
.move-actions .ma-eval { font-family:ui-monospace, monospace; font-size:11px; color:#64748b; padding:3px 8px; background:#f1f5f9; border-radius:5px }

.nag-row { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:10px }
.nag-btn { padding:5px 10px; border-radius:6px; border:1px solid #e2e8f0; background:#fff; cursor:pointer; font-family:ui-monospace, monospace; font-size:13px; font-weight:700; color:#475569; transition:all .12s; min-width:34px }
.nag-btn:hover { background:#f8fafc; border-color:#cbd5e1 }
.nag-btn.active { background:#4338ca; color:#fff; border-color:#4338ca }
.nag-btn[data-nag="!!"].active { background:#10b981; border-color:#10b981 }
.nag-btn[data-nag="!"].active  { background:#16a34a; border-color:#16a34a }
.nag-btn[data-nag="!?"].active { background:#14b8a6; border-color:#14b8a6 }
.nag-btn[data-nag="?!"].active { background:#ca8a04; border-color:#ca8a04 }
.nag-btn[data-nag="?"].active  { background:#ea580c; border-color:#ea580c }
.nag-btn[data-nag="??"].active { background:#dc2626; border-color:#dc2626 }
.nag-btn.clear { color:#94a3b8 }

.cmt-area { width:100%; min-height:60px; padding:8px 10px; border:1px solid #e2e8f0; border-radius:7px; resize:vertical; font:inherit; font-size:12px; line-height:1.5; color:#0f172a; background:#fff; box-sizing:border-box }
.cmt-area:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12) }

.ai-block { margin-top:10px; padding:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:7px; font-size:12px; line-height:1.55; color:#334155 }
.ai-block .ai-head { display:flex; align-items:center; gap:8px; margin-bottom:6px }
.ai-block .ai-head .ai-tag { font-size:10px; padding:2px 7px; border-radius:99px; font-weight:600 }
.ai-block .ai-head .ai-tag.gpt  { background:#ede9fe; color:#5b21b6 }
.ai-block .ai-head .ai-tag.heur { background:#fef3c7; color:#92400e }
.ai-block .ai-pickup { margin-left:auto; font-size:11px; color:#4f46e5; cursor:pointer; padding:3px 8px; border-radius:5px; border:1px solid #c7d2fe; background:#fff; font-weight:600 }
.ai-block .ai-pickup:hover { background:#eef2ff }
.ai-block .ai-text { white-space:pre-wrap }
.ai-loading { color:#6366f1; display:flex; align-items:center; gap:8px; font-size:12px }
.ai-loading::before { content:''; display:inline-block; width:11px; height:11px; border:2px solid #c7d2fe; border-top-color:#4f46e5; border-radius:50%; animation:an-spin 0.8s linear infinite }
@keyframes an-spin { to { transform:rotate(360deg) } }

.move-actions .ma-empty { font-size:13px; color:#94a3b8; font-style:italic; padding:8px 0 }
.move-actions .ma-row { display:flex; gap:6px; flex-wrap:wrap; margin-top:10px }
.move-actions .ma-row button { font-size:11px; padding:5px 10px }

/* MultiPV engine panel */
.mpv-card { background:#fff; border:1px solid var(--border); border-radius:10px; overflow:hidden }
.mpv-head { padding:10px 14px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:10px; background:#f8fafc; flex-wrap:wrap }
.mpv-head .mpv-title { font-size:12px; font-weight:600; color:#64748b }
.mpv-head .mpv-info  { font-family:ui-monospace, monospace; font-size:11px; color:#64748b; margin-left:auto }
.mpv-head .mpv-toggle { font-size:11px; padding:4px 10px; border-radius:5px; border:1px solid #e2e8f0; background:#fff; cursor:pointer; font-weight:600; color:#475569 }
.mpv-head .mpv-toggle.on { background:#4338ca; color:#fff; border-color:#4338ca }
.mpv-list { padding:6px }
.mpv-row { display:grid; grid-template-columns:32px 60px 1fr; gap:10px; padding:7px 10px; border-radius:6px; align-items:center; cursor:pointer; transition:background .1s; font-size:12px }
.mpv-row:hover { background:#f8fafc }
.mpv-row .mpv-rank { font-family:ui-monospace, monospace; font-size:11px; font-weight:700; color:#94a3b8; text-align:center }
.mpv-row .mpv-eval { font-family:ui-monospace, monospace; font-size:12px; font-weight:700; padding:3px 7px; border-radius:5px; text-align:center; min-width:50px; background:#f1f5f9; color:#475569 }
.mpv-row .mpv-eval.up { background:#fafafa; color:#0f172a; border:1px solid #e2e8f0 }
.mpv-row .mpv-eval.down { background:#1f2937; color:#fff }
.mpv-row .mpv-line { font-family:ui-monospace, monospace; font-size:12px; color:#334155; line-height:1.4; overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
.mpv-row .mpv-line .pv-mv { padding:0 2px; cursor:pointer }
.mpv-row .mpv-line .pv-mv:hover { background:#eef2ff; border-radius:3px; color:#4338ca }
.mpv-empty { padding:20px; text-align:center; color:#94a3b8; font-size:12px; font-style:italic }

/* Action bars */
.action-bar       { background:#fff; border:1px solid var(--border); border-radius:10px; padding:12px 14px }
.action-bar h4    { font-size:13px; font-weight:600; color:#64748b; margin-bottom:8px; display:flex; align-items:center; gap:6px }
.action-bar .row  { display:flex; gap:8px; align-items:center }
.action-bar .help { font-size:11px; color:#94a3b8; line-height:1.5; margin-top:8px }
.an-prog          { display:flex; align-items:center; gap:8px; font-size:11px; color:#64748b; margin-top:8px }
.an-prog .bar     { flex:1; height:5px; background:#e2e8f0; border-radius:99px; overflow:hidden }
.an-prog .bar > div { height:100%; background:var(--color-primary); transition:width .25s }

/* Game summary card */
.an-summary       { background:#fff; border:1px solid var(--border); border-radius:10px; padding:10px 14px }
.an-summary-head  { font-size:12px; font-weight:600; color:#64748b; margin-bottom:8px; display:flex; align-items:center; gap:8px }
.an-summary-grid  { display:grid; grid-template-columns:repeat(5, 1fr); gap:6px }
.an-summary-stat  { padding:6px 4px; border-radius:7px; text-align:center; font-size:11px; font-weight:600; color:#475569; background:#f8fafc; border:1px solid #f1f5f9 }
.an-summary-stat .n  { font-size:16px; font-weight:800; line-height:1; display:block; margin-bottom:2px }
.an-summary-stat .l  { font-size:10px; opacity:.85; font-family:ui-monospace, monospace; font-weight:700 }
.an-summary-stat.brilliant { background:#ecfdf5; border-color:#a7f3d0; color:#065f46 }
.an-summary-stat.good      { background:#f0fdf4; border-color:#bbf7d0; color:#15803d }
.an-summary-stat.inacc     { background:#fefce8; border-color:#fde047; color:#854d0e }
.an-summary-stat.mistake   { background:#fff7ed; border-color:#fed7aa; color:#9a3412 }
.an-summary-stat.blunder   { background:#fef2f2; border-color:#fca5a5; color:#991b1b }

/* Promote/delete branch buttons (only visible on non-root nodes) */
.branch-ctl { font-size:11px; padding:4px 8px; border-radius:5px; border:1px solid #e2e8f0; background:#fff; cursor:pointer; color:#64748b; font-weight:600 }
.branch-ctl:hover { background:#f1f5f9; color:#0f172a }
.branch-ctl.danger:hover { background:#fef2f2; color:#dc2626; border-color:#fca5a5 }
</style>
@endpush

@section('content')

<div class="an-page-head">
  <div style="flex:1;min-width:0">
    <div class="breadcrumb"><a href="{{ route('student.analyse') }}">All studies</a> / Study</div>
    <h1>{{ $analysis->name }}</h1>
  </div>
  <span id="an-savepill" class="savepill saved"><span class="dot"></span><span id="an-savetext">Saved</span></span>
  <button id="an-save-btn" class="btn btn-primary btn-sm">Save Study</button>
  <form method="POST" action="{{ route('student.analyse.destroy', $analysis) }}"
        onsubmit="return confirm('Delete this study? Variations, comments and AI notes will be lost.');"
        style="margin:0">
    @csrf @method('DELETE')
    <button class="btn btn-ghost btn-sm" style="color:#dc2626">Delete</button>
  </form>
</div>

<div class="an-shell" id="an-root">

  {{-- ─── LEFT: positions list ─── --}}
  <div class="an-pos-card">
    <div class="an-pos-head">
      <span>Positions</span>
      <span id="an-pos-count" style="margin-left:auto;font-weight:700;color:#0f172a">0</span>
    </div>
    <div id="an-pos-list" class="an-pos-list">
      <div style="padding:14px;color:#94a3b8;font-style:italic;font-size:12px">Loading…</div>
    </div>
  </div>

  {{-- ─── CENTRE: board + controls ─── --}}
  <div class="an-center">
    <div class="an-board-wrap">
      <div class="an-tools">
        <div class="left">
          <div class="gtitle" id="an-game-title">No position selected</div>
          <div class="gsub"   id="an-game-sub">Pick a position from the left</div>
        </div>
        <div class="right">
          <span id="an-eval-pill" class="eval-pill neutral">—</span>
        </div>
      </div>

      <div class="an-board-row">
        <div class="an-evalbar" id="an-evalbar" title="Engine evaluation (white's perspective)">
          <div class="an-evalbar-fill" id="an-evalbar-fill" style="height:50%"></div>
          <div class="an-evalbar-num" id="an-evalbar-num">0.00</div>
        </div>
        <div id="an-board"></div>
      </div>

      <div class="an-controls">
        <button class="ctl" id="an-btn-start" title="First move (Home)" aria-label="First move"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 4V16M15 5L8 10L15 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
        <button class="ctl" id="an-btn-prev" title="Previous move" aria-label="Previous move"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M13 5L7 10L13 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
        <span class="ctr" id="an-counter">0/0</span>
        <button class="ctl" id="an-btn-next" title="Next move" aria-label="Next move"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M7 5L13 10L7 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
        <button class="ctl" id="an-btn-end" title="Last move (End)" aria-label="Last move"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M15 4V16M5 5L12 10L5 15" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
        <span style="width:1px;background:#e2e8f0;margin:4px 4px;align-self:stretch"></span>
        <button class="ctl" id="an-btn-flip" title="Flip board (F)" aria-label="Flip board"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6 4V16M6 4L3.5 6.5M6 4L8.5 6.5M14 16V4M14 16L11.5 13.5M14 16L16.5 13.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg></button>
      </div>

      <div style="font-size:11px;color:#94a3b8;text-align:center;line-height:1.5">
        <b>Drag pieces</b> on the board to add moves — they become variations. Standard SAN notation (Nf3, Bxc4, O-O) is shown everywhere.
      </div>
    </div>
  </div>

  {{-- Hidden host that boots window.MiniChessEngine (Stockfish loader). --}}
  <div style="display:none;position:absolute;left:-9999px" aria-hidden="true">
    @include('partials._engine', ['engHostId' => 'an_eng_loader', 'getFenName' => 'analyseLoaderFen'])
  </div>

  {{-- ─── RIGHT: tree + actions + engine ─── --}}
  <div class="an-right">

    <div class="ui-tabs an-tabs">
      <button type="button" class="ui-tab an-tab is-active active" data-an-tab="moves">Moves &amp; tree</button>
      <button type="button" class="ui-tab an-tab" data-an-tab="engine">Engine (top 5)</button>
    </div>

    {{-- ─── MOVES PANEL ─── --}}
    <div class="an-panel active" data-an-panel="moves">

      <div class="an-summary" id="an-summary" style="display:none">
        <div class="an-summary-head">
          <span>Game review</span>
          <span id="an-rev-progress" style="margin-left:auto;color:#94a3b8;font-weight:600;text-transform:none;letter-spacing:0">— / —</span>
        </div>
        <div class="an-summary-grid">
          <div class="an-summary-stat brilliant"><span class="n" id="cnt-brilliant">0</span><span class="l">!!</span></div>
          <div class="an-summary-stat good"     ><span class="n" id="cnt-good"     >0</span><span class="l">!</span></div>
          <div class="an-summary-stat inacc"    ><span class="n" id="cnt-inacc"    >0</span><span class="l">?!</span></div>
          <div class="an-summary-stat mistake"  ><span class="n" id="cnt-mist"     >0</span><span class="l">?</span></div>
          <div class="an-summary-stat blunder"  ><span class="n" id="cnt-blund"    >0</span><span class="l">??</span></div>
        </div>
      </div>

      <div class="an-tree" id="an-tree">
        <div class="an-tree-empty">No moves yet — drag a piece on the board to start a line.</div>
      </div>

      <div class="move-actions" id="an-move-actions" style="display:none">
        <div class="ma-head">
          <div class="ma-icon" id="an-ma-icon">·</div>
          <div class="ma-title">
            <div class="ma-lbl" id="an-ma-lbl">Move</div>
            <div class="ma-mv"  id="an-ma-mv">—</div>
          </div>
          <span class="ma-eval" id="an-ma-eval" style="display:none">—</span>
        </div>

        <div class="nag-row">
          <button class="nag-btn" data-nag="!!" title="Brilliant">!!</button>
          <button class="nag-btn" data-nag="!"  title="Best/Good">!</button>
          <button class="nag-btn" data-nag="!?" title="Interesting">!?</button>
          <button class="nag-btn" data-nag="?!" title="Inaccuracy">?!</button>
          <button class="nag-btn" data-nag="?"  title="Mistake">?</button>
          <button class="nag-btn" data-nag="??" title="Blunder">??</button>
          <button class="nag-btn clear" data-nag="" title="Clear annotation">Clear</button>
        </div>

        <textarea class="cmt-area" id="an-cmt" placeholder="Add your own comment about this move…"></textarea>

        <div class="ma-row">
          <button class="btn btn-primary btn-sm" id="an-btn-ai" style="flex:1">Ask AI Tutor</button>
          <button class="btn btn-ghost btn-sm" id="an-btn-ai-force" title="Re-generate even if cached">Regenerate</button>
          <button class="branch-ctl" id="an-btn-promote" title="Promote variation to main line">Set Main</button>
          <button class="branch-ctl danger" id="an-btn-del" title="Delete this branch (Del)">Delete Branch</button>
        </div>

        <div class="ai-block" id="an-ai-block" style="display:none">
          <div class="ai-head">
            <span class="ai-tag" id="an-ai-tag">AI</span>
            <span style="font-size:11px;color:#64748b;font-weight:600">Coach explanation</span>
            <button class="ai-pickup" id="an-ai-pickup" title="Use this AI text as my comment">Use as My Note</button>
          </div>
          <div class="ai-text" id="an-ai-text">—</div>
        </div>
      </div>

      <div class="action-bar">
        <h4>Auto-review main line</h4>
        <div class="row">
          <button class="btn btn-primary btn-sm" id="an-btn-review" style="flex:1">Review main line</button>
          <button class="btn btn-ghost btn-sm"   id="an-btn-stop">Stop</button>
        </div>
        <div class="an-prog">
          <span id="an-prog-text">0 / 0</span>
          <div class="bar"><div id="an-prog-fill" style="width:0%"></div></div>
        </div>
        <div class="help">Stockfish analyses every main-line move at depth 12 and tags mistakes with chess symbols.</div>
      </div>

    </div>

    {{-- ─── ENGINE PANEL (Stockfish multipv 5) ─── --}}
    <div class="an-panel" data-an-panel="engine">
      <div class="mpv-card">
        <div class="mpv-head">
          <span class="mpv-title">Stockfish — top 5</span>
          <span class="mpv-info" id="mpv-info">depth — / nps —</span>
          <button class="mpv-toggle" id="mpv-toggle">Start</button>
        </div>
        <div class="mpv-list" id="mpv-list">
          <div class="mpv-empty">Engine off. Press <b>Start</b> to see Stockfish's top 5 moves for this position.</div>
        </div>
      </div>

      <div class="action-bar" style="margin-top:10px">
        <div class="help" style="margin-top:0">
          The engine analyses the position currently on the board. Step through moves with ← / → or click any move in the tree.
          Click any move in a PV line to play it on the board (creates a variation).
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
@include('partials._chess-engine')
<script>
(function(){
const STARTING_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
const $   = (id) => document.getElementById(id);

const PGN              = @json($analysis->pgn);
const URL_REVIEWS      = @json(route('student.analyse.reviews', $analysis));
const URL_COMMENTARY   = @json(route('student.analyse.commentary', $analysis));
const URL_TREE         = @json(route('student.analyse.tree', $analysis));
const initialReviews   = @json($analysis->reviews    ?: (object)[]);
const initialCommentary= @json($analysis->commentary ?: (object)[]);
const initialTree      = @json($analysis->tree       ?: null);

const NAG_LABEL = { '!!':'Brilliant', '!':'Good move', '!?':'Interesting', '?!':'Inaccuracy', '?':'Mistake', '??':'Blunder' };
const NAG_CLASS = { '!!':'brilliant', '!':'best', '!?':'good', '?!':'inaccuracy', '?':'mistake', '??':'blunder' };

// ─── State ────────────────────────────────────────────────────────────
let games   = [];           // [{ name, startFen, root: <node> }]
let gameIdx = -1;
let curPath = [];           // array of child indices into root
let reviews    = {};
let commentary = {};
let reviewing  = false;
let dirty      = false;
let saveBusy   = false;
let nodeIdSeq  = 1;

Object.keys(initialReviews    || {}).forEach(k => reviews[k]    = initialReviews[k]);
Object.keys(initialCommentary || {}).forEach(k => commentary[k] = initialCommentary[k]);

// ─── Tree helpers ─────────────────────────────────────────────────────
function newNode(fields) {
  return Object.assign({
    id: 'n' + (nodeIdSeq++),
    san: '', uci: '', fen: '',
    moveNumber: 0, side: 'w',
    nag: '', comment: '', ai: '', aiSource: '',
    review: null, isUser: false,
    children: []
  }, fields || {});
}
function rootOf(g) { return g.root; }
function getNodeAtPath(g, path) {
  let n = g.root;
  for (const c of path) { if (!n.children[c]) return null; n = n.children[c]; }
  return n;
}
function getFenAt(g, path) {
  if (path.length === 0) return g.startFen || STARTING_FEN;
  const n = getNodeAtPath(g, path);
  return n ? n.fen : g.startFen || STARTING_FEN;
}
function findChildIdx(parent, san) {
  const norm = (s) => String(s || '').replace(/[+#!?]/g, '');
  return parent.children.findIndex(c => norm(c.san) === norm(san));
}

// Convert parsed PGN line into a tree (mainLine + nested variations).
function pgnLineToNodes(line, parent) {
  if (!Array.isArray(line) || line.length === 0) return;
  for (let i = 0; i < line.length; i++) {
    const m = line[i];
    const node = newNode({
      san: m.san, fen: m.fen, moveNumber: m.moveNumber, side: m.side,
      comment: m.comment || '', children: [],
    });
    parent.children.push(node);
    if (Array.isArray(m.variations)) {
      m.variations.forEach(varLine => pgnLineToNodes(varLine, parent)); // sibling variations
    }
    parent = node;
  }
}

function buildGamesFromTree(t) {
  if (!t || !Array.isArray(t.games)) return null;
  return t.games.map(g => {
    const root = newNode({ children: [] });
    function rehydrate(srcChildren, dst) {
      if (!Array.isArray(srcChildren)) return;
      for (const c of srcChildren) {
        const n = newNode({
          san: c.san || '', uci: c.uci || '', fen: c.fen || '',
          moveNumber: c.moveNumber || 0, side: c.side || 'w',
          nag: c.nag || '', comment: c.comment || '',
          ai: c.ai || '', aiSource: c.aiSource || '',
          review: c.review || null, isUser: !!c.isUser,
          children: [],
        });
        dst.push(n);
        rehydrate(c.children, n.children);
      }
    }
    rehydrate(g.root && g.root.children, root.children);
    return { name: g.name || 'Game', startFen: g.startFen || STARTING_FEN, root };
  });
}

function buildGamesFromPGN() {
  const parsed = MiniChess.parsePGNFile(PGN) || [];
  return parsed.map(g => {
    const root = newNode({ children: [] });
    pgnLineToNodes(g.mainLine || [], root);
    return { name: g.name || 'Game', startFen: g.startFen || STARTING_FEN, root };
  });
}

// Boot tree: prefer saved tree, else parse PGN.
const fromSaved = buildGamesFromTree(initialTree);
games = (fromSaved && fromSaved.length) ? fromSaved : buildGamesFromPGN();

// ─── Board (draggable!) ───────────────────────────────────────────────
const BOARD_PX = 460;
const board = new MiniChess.Board('an-board', {
  size: BOARD_PX, draggable: true, draw: true, position: STARTING_FEN,
  onMove: (from, to, newFen) => onUserMove(from, to, newFen),
});

// Stub for hidden engine-partial host getter (it polls but stays hidden).
window.analyseLoaderFen = () => STARTING_FEN;

// ─── Tabs ─────────────────────────────────────────────────────────────
document.querySelectorAll('[data-an-tab]').forEach(t => {
  t.addEventListener('click', () => {
    document.querySelectorAll('.an-tab').forEach(x => x.classList.remove('active', 'is-active'));
    document.querySelectorAll('.an-panel').forEach(x => x.classList.remove('active'));
    t.classList.add('active', 'is-active');
    document.querySelector('[data-an-panel="' + t.dataset.anTab + '"]').classList.add('active');
  });
});

// ─── Position list ────────────────────────────────────────────────────
buildPositionsList();
if (games.length) loadGame(0);

function buildPositionsList() {
  const list = $('an-pos-list');
  $('an-pos-count').textContent = games.length;
  if (!games.length) {
    list.innerHTML = '<div style="padding:14px;color:#94a3b8;font-style:italic;font-size:12px">No games found in this PGN.</div>';
    return;
  }
  list.innerHTML = '';
  games.forEach((g, i) => {
    const r = document.createElement('div');
    r.className = 'an-pos-row';
    r.dataset.idx = i;
    const moves = countMainMoves(g);
    r.innerHTML =
      '<div style="font-size:13px">' + esc(g.name || ('Game ' + (i+1))) + '</div>'
      + '<div class="an-pos-meta"><span>' + moves + ' main move' + (moves===1?'':'s') + '</span></div>';
    r.addEventListener('click', () => loadGame(i));
    list.appendChild(r);
  });
}
function countMainMoves(g) {
  let n = g.root, c = 0;
  while (n.children[0]) { n = n.children[0]; c++; }
  return c;
}

function loadGame(i) {
  if (i < 0 || i >= games.length) return;
  gameIdx = i;
  curPath = [];
  $('an-game-title').textContent = games[i].name || ('Game ' + (i+1));
  $('an-game-sub').textContent   = countMainMoves(games[i]) + ' main-line move(s)';
  document.querySelectorAll('#an-pos-list .an-pos-row').forEach(r => {
    r.classList.toggle('active', parseInt(r.dataset.idx, 10) === i);
  });
  syncBoard();
  renderTree();
  renderMoveActions();
  renderSummary();
  setProg(0, mainLineLen(), 'rev');
}

// ─── Navigation by path ───────────────────────────────────────────────
function syncBoard() {
  if (gameIdx < 0) return;
  const g   = games[gameIdx];
  const fen = getFenAt(g, curPath);
  board.setPosition(fen);
  const total = mainLineLen();
  $('an-counter').textContent = curPath.length + '/' + total;
  renderEvalForCurrent();
}
function mainLineLen() {
  if (gameIdx < 0) return 0;
  let n = games[gameIdx].root, c = 0;
  while (n.children[0]) { n = n.children[0]; c++; }
  return c;
}

$('an-btn-start').onclick = () => { curPath = []; afterNav(); };
$('an-btn-prev').onclick  = () => { if (curPath.length) { curPath = curPath.slice(0, -1); afterNav(); } };
$('an-btn-next').onclick  = () => {
  const cur = curPath.length === 0 ? games[gameIdx].root : getNodeAtPath(games[gameIdx], curPath);
  if (cur && cur.children[0]) { curPath = curPath.concat([0]); afterNav(); }
};
$('an-btn-end').onclick   = () => {
  let path = [];
  let node = games[gameIdx].root;
  while (node.children[0]) { path.push(0); node = node.children[0]; }
  curPath = path; afterNav();
};
$('an-btn-flip').onclick  = () => board.flip();

document.addEventListener('keydown', (e) => {
  if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)) return;
  if (e.key === 'ArrowLeft')  { e.preventDefault(); $('an-btn-prev').click(); }
  if (e.key === 'ArrowRight') { e.preventDefault(); $('an-btn-next').click(); }
  if (e.key === 'Home')       { e.preventDefault(); $('an-btn-start').click(); }
  if (e.key === 'End')        { e.preventDefault(); $('an-btn-end').click(); }
  if (e.key === 'f' || e.key === 'F') { e.preventDefault(); board.flip(); }
  if (e.key === 'Delete')     { e.preventDefault(); deleteCurrentBranch(); }
});

function afterNav() {
  syncBoard();
  renderTree();
  renderMoveActions();
}

// ─── Drag-to-move: builds variations ──────────────────────────────────
async function onUserMove(from, to, newFen) {
  if (gameIdx < 0) { board.setPosition(STARTING_FEN); return; }
  const g = games[gameIdx];
  const preFen = getFenAt(g, curPath);
  // moveToSan needs a parsed state, not a FEN string — this is what gives us
  // proper SAN like "Nf3" / "Bxc4" / "O-O" instead of raw coords.
  let san = null;
  try {
    const preState = MiniChess.parseFen(preFen);
    const r = MiniChess.moveToSan(preState, from, to, newFen);
    if (typeof r === 'string') san = r;
    else if (r && typeof r === 'object') san = r.san || null;
  } catch (e) {}
  if (!san || san === (from + to)) {
    // Last-ditch: if the helper returned coords, at least try once more.
    try {
      const preState2 = MiniChess.parseFen(preFen);
      const r2 = MiniChess.moveToSan(preState2, from, to, null);
      if (typeof r2 === 'string' && r2 !== (from + to)) san = r2;
    } catch (e) {}
  }
  if (!san) san = from + to;
  const uci = from + to;

  // Determine moveNumber & side from FEN.
  const parts = preFen.split(' ');
  const turnBefore = parts[1];
  const fullMove   = parseInt(parts[5], 10) || 1;
  const side       = turnBefore;
  const moveNumber = turnBefore === 'w' ? fullMove : fullMove;

  const parent = curPath.length === 0 ? g.root : getNodeAtPath(g, curPath);
  if (!parent) return;

  const existing = findChildIdx(parent, san);
  if (existing >= 0) {
    curPath = curPath.concat([existing]);
    afterNav();
    return;
  }

  // Append as new child (variation if parent already has a main line at this point).
  const node = newNode({
    san, uci, fen: newFen, moveNumber, side,
    isUser: true, children: [],
  });
  parent.children.push(node);
  curPath = curPath.concat([parent.children.length - 1]);
  markDirty();
  afterNav();
}

// ─── Tree rendering (PGN-flow with inline variations) ────────────────
function renderTree() {
  const host = $('an-tree');
  host.innerHTML = '';
  if (gameIdx < 0 || !games[gameIdx].root.children.length) {
    host.innerHTML = '<div class="an-tree-empty">No moves yet — drag a piece on the board to start a line.</div>';
    return;
  }
  const wrap = document.createElement('div');
  renderLine(wrap, games[gameIdx].root, [], /*isVariation=*/false, /*startSide=*/null);
  host.appendChild(wrap);

  // Auto-scroll active move into view.
  const active = host.querySelector('.mv.active');
  if (active) active.scrollIntoView({ block:'nearest', behavior:'smooth' });
}

function renderLine(host, parent, parentPath, isVariation, _startSide) {
  if (!parent.children.length) return;

  let lastMoveNumber = -1;
  let lastSide = null;
  // First move number/side context
  for (let i = 0; i < parent.children.length; i++) {
    const child = parent.children[i];
    if (i === 0) {
      // main child of this branch
      const path = parentPath.concat([0]);
      // Print move number marker
      lastMoveNumber = renderMoveNumber(host, child, lastMoveNumber, lastSide);
      lastSide = child.side;
      host.appendChild(renderMv(child, path));
      maybeRenderInlineCommentary(host, child);
    } else {
      // sibling variation: render in its own block
      const varHost = document.createElement('span');
      varHost.className = 'var-line';
      varHost.appendChild(document.createTextNode('('));
      let lmn = -1, lsd = null;
      const varPath = parentPath.concat([i]);
      lmn = renderMoveNumber(varHost, child, lmn, lsd);
      lsd = child.side;
      varHost.appendChild(renderMv(child, varPath));
      maybeRenderInlineCommentary(varHost, child);
      // recurse into variation child sub-tree
      renderLine(varHost, child, varPath, true, child.side);
      varHost.appendChild(document.createTextNode(')'));
      host.appendChild(varHost);
    }
  }

  // Recurse into the main line (children[0]) of this parent.
  if (parent.children[0]) {
    const main = parent.children[0];
    renderLine(host, main, parentPath.concat([0]), isVariation, lastSide);
  }
}

function renderMoveNumber(host, node, lastMn, lastSide) {
  // White moves print "n.", black moves print "n..." only if they're starting a line out of order.
  const span = document.createElement('span');
  span.className = 'mn';
  if (node.side === 'w') {
    if (node.moveNumber !== lastMn) {
      span.textContent = node.moveNumber + '.';
      host.appendChild(span);
    }
  } else {
    // Print full "n..." marker if this isn't immediately after a white move of the same number.
    if (node.moveNumber !== lastMn || lastSide !== 'w') {
      span.textContent = node.moveNumber + '...';
      host.appendChild(span);
    }
  }
  return node.moveNumber;
}

function renderMv(node, path) {
  const el = document.createElement('span');
  el.className = 'mv';
  if (node.isUser) el.classList.add('user');
  // Review-class colouring (only if we have engine review for this exact move).
  const r = node.review;
  if (r && r.class && NAG_CLASS) {
    const cls = (r.class === 'best') ? 'best'
              : (r.class === 'brilliant') ? 'brilliant'
              : (r.class === 'good') ? 'good'
              : (r.class === 'inaccuracy') ? 'inaccuracy'
              : (r.class === 'mistake') ? 'mistake'
              : (r.class === 'blunder') ? 'blunder' : '';
    if (cls) el.classList.add(cls);
  } else if (node.nag) {
    const k = NAG_CLASS[node.nag];
    if (k) el.classList.add(k);
  }
  if (samePath(path, curPath)) el.classList.add('active');
  el.dataset.path = path.join(',');
  el.title = (r ? (NAG_LABEL[node.nag] || (r.class || '')).toString().trim() : '') + (node.comment ? ' · Comment' : '');
  el.innerHTML = esc(node.san) + (node.nag ? '<span class="nag">' + esc(node.nag) + '</span>' : '');
  el.addEventListener('click', () => { curPath = path.slice(); afterNav(); });
  return el;
}

function maybeRenderInlineCommentary(host, node) {
  if (node.comment && node.comment.trim()) {
    const c = document.createElement('span');
    c.className = 'cmt-inline';
    const t = node.comment.trim();
    c.textContent = t.length > 70 ? (t.slice(0, 70) + '…') : t;
    c.title = t;
    host.appendChild(c);
  }
}

function samePath(a, b) {
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
  return true;
}

// ─── Move actions card (NAG, comment, AI) ────────────────────────────
function currentNode() {
  if (gameIdx < 0 || curPath.length === 0) return null;
  return getNodeAtPath(games[gameIdx], curPath);
}

function renderMoveActions() {
  const card = $('an-move-actions');
  const node = currentNode();
  if (!node) {
    card.style.display = 'none';
    return;
  }
  card.style.display = '';
  $('an-ma-mv').textContent = node.moveNumber + (node.side === 'w' ? '. ' : '... ') + node.san + (node.nag || '');
  const r = node.review;
  if (r) {
    $('an-ma-lbl').textContent = (NAG_LABEL[node.nag] || (r.class ? (r.class.charAt(0).toUpperCase() + r.class.slice(1)) : 'Engine')) + ' move';
    $('an-ma-eval').style.display = '';
    $('an-ma-eval').textContent = fmtEval(r.evalBefore) + ' → ' + fmtEval(r.evalAfter);
    $('an-ma-icon').className = 'ma-icon ' + (NAG_CLASS[node.nag] || r.class || '');
    $('an-ma-icon').textContent = node.nag || (r.class === 'blunder' ? '??' : r.class === 'mistake' ? '?' : r.class === 'inaccuracy' ? '?!' : r.class === 'best' ? '!' : '·');
  } else {
    $('an-ma-lbl').textContent = node.isUser ? 'Your move' : 'Move';
    $('an-ma-eval').style.display = 'none';
    $('an-ma-icon').className = 'ma-icon';
    $('an-ma-icon').textContent = node.nag || '·';
  }
  // NAG buttons
  document.querySelectorAll('.nag-btn').forEach(b => {
    b.classList.toggle('active', (b.dataset.nag || '') === (node.nag || ''));
  });
  // Comment area
  $('an-cmt').value = node.comment || '';
  // AI block
  if (node.ai && node.ai.trim()) {
    $('an-ai-block').style.display = '';
    $('an-ai-text').textContent = node.ai;
    const tag = $('an-ai-tag');
    tag.className = 'ai-tag ' + (node.aiSource === 'openai' ? 'gpt' : 'heur');
    tag.textContent = node.aiSource === 'openai' ? 'GPT' : 'Built-in';
  } else {
    $('an-ai-block').style.display = 'none';
  }
}

// NAG click
document.querySelectorAll('.nag-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const node = currentNode();
    if (!node) return;
    const v = btn.dataset.nag || '';
    node.nag = (node.nag === v) ? '' : v;
    markDirty();
    renderMoveActions();
    renderTree();
  });
});

// Comment input
$('an-cmt').addEventListener('input', (e) => {
  const node = currentNode();
  if (!node) return;
  node.comment = e.target.value;
  markDirty();
  // Don't full re-render the tree on every keystroke — but refresh inline cmt sometimes.
  scheduleTreeRefresh();
});

let treeRefreshTimer = null;
function scheduleTreeRefresh() {
  clearTimeout(treeRefreshTimer);
  treeRefreshTimer = setTimeout(() => renderTree(), 600);
}

// AI tutor
$('an-btn-ai').onclick       = () => askAI(false);
$('an-btn-ai-force').onclick = () => askAI(true);
$('an-ai-pickup').onclick = () => {
  const node = currentNode();
  if (!node || !node.ai) return;
  node.comment = node.ai;
  $('an-cmt').value = node.ai;
  markDirty();
  renderTree();
};

async function askAI(force) {
  const node = currentNode();
  if (!node) return alert('Step into a move first.');
  const g = games[gameIdx];
  const preFen  = curPath.length === 1 ? (g.startFen || STARTING_FEN) : getFenAt(g, curPath.slice(0, -1));
  const postFen = node.fen;
  const key = 'tree-' + node.id;

  $('an-ai-block').style.display = '';
  $('an-ai-text').innerHTML = '<div class="ai-loading">Asking AI tutor…</div>';
  $('an-ai-tag').className = 'ai-tag heur';
  $('an-ai-tag').textContent = '…';

  // Make sure we have a quick engine review for context.
  let r = node.review;
  if (!r) {
    try {
      const eng = await window.MiniChessEngine.getEngine();
      const before = await analyseFen(eng, preFen, 12);
      const after  = await analyseFen(eng, postFen, 12);
      const evalB = scoreToWhitePawns(before.score, preFen);
      const evalA = scoreToWhitePawns(after.score,  postFen);
      const drop  = node.side === 'w' ? (evalB - evalA) : (evalA - evalB);
      const isBest = before.bestSan && before.bestSan === node.san;
      let cls;
      if (isBest && Math.abs(drop) <= 0.05) cls = 'best';
      else if (drop <= 0.30)                cls = 'good';
      else if (drop <= 0.99)                cls = 'inaccuracy';
      else if (drop <= 1.99)                cls = 'mistake';
      else                                  cls = 'blunder';
      r = { class: cls, drop, evalBefore: evalB, evalAfter: evalA, bestSan: before.bestSan || '', pvSan: before.pvSan || [] };
      node.review = r;
      markDirty();
    } catch (e) {
      $('an-ai-text').innerHTML = '<span style="color:#dc2626">Engine unavailable — ' + esc(e.message || 'error') + '</span>';
      return;
    }
  }

  try {
    const resp = await fetch(URL_COMMENTARY, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF, 'Accept':'application/json' },
      body: JSON.stringify({
        key, san: node.san,
        fenBefore: preFen, fenAfter: postFen,
        evalBefore: r.evalBefore, evalAfter: r.evalAfter,
        bestSan: r.bestSan, pv: r.pvSan,
        classification: r.class, force,
      }),
    });
    const data = await resp.json();
    if (!resp.ok) throw new Error(data.error || ('HTTP ' + resp.status));
    node.ai = data.text || '';
    node.aiSource = data.source || '';
    markDirty();
    renderMoveActions();
    renderTree();
  } catch (e) {
    $('an-ai-text').innerHTML = '<span style="color:#dc2626">AI request failed — ' + esc(e.message || 'error') + '</span>';
  }
}

// Promote variation / delete branch
$('an-btn-promote').onclick = () => {
  if (curPath.length === 0) return;
  const g = games[gameIdx];
  // Walk up: at each level, swap the index in curPath to 0 in its parent's children.
  const newPath = curPath.slice();
  for (let depth = 0; depth < curPath.length; depth++) {
    const parent = depth === 0 ? g.root : getNodeAtPath(g, newPath.slice(0, depth));
    const idxAtDepth = newPath[depth];
    if (idxAtDepth !== 0 && parent && parent.children[idxAtDepth]) {
      const node = parent.children[idxAtDepth];
      parent.children.splice(idxAtDepth, 1);
      parent.children.unshift(node);
      newPath[depth] = 0;
    }
  }
  curPath = newPath;
  markDirty();
  afterNav();
};

$('an-btn-del').onclick = deleteCurrentBranch;
function deleteCurrentBranch() {
  if (curPath.length === 0) return;
  if (!confirm('Delete this branch (and all its sub-variations)?')) return;
  const g = games[gameIdx];
  const parentPath = curPath.slice(0, -1);
  const parent = parentPath.length === 0 ? g.root : getNodeAtPath(g, parentPath);
  const removeAt = curPath[curPath.length - 1];
  if (!parent || !parent.children[removeAt]) return;
  parent.children.splice(removeAt, 1);
  curPath = parentPath;
  markDirty();
  afterNav();
}

// ─── Eval bar ────────────────────────────────────────────────────────
function renderEvalForCurrent() {
  const node = currentNode();
  const r = node && node.review;
  if (r && r.evalAfter != null) setEvalBar(Number(r.evalAfter) * 100);
  else setEvalBar(null);
}
function setEvalBar(cp) {
  const fill = $('an-evalbar-fill');
  const num  = $('an-evalbar-num');
  const lbl  = $('an-eval-pill');
  if (cp == null) {
    fill.style.height = '50%'; num.textContent = ''; lbl.textContent = '—';
    lbl.className = 'eval-pill neutral'; return;
  }
  const pct = Math.max(2, Math.min(98, 50 + (cp / 10)));
  fill.style.height = pct + '%';
  const pawns = cp / 100;
  const label = (Math.abs(pawns) >= 99) ? (pawns > 0 ? 'M+' : 'M-') : ((pawns >= 0 ? '+' : '') + pawns.toFixed(2));
  num.textContent = label; lbl.textContent = label;
  lbl.className = 'eval-pill ' + (Math.abs(pawns) < 0.2 ? 'neutral' : (pawns >= 0 ? 'up' : 'down'));
}
function fmtEval(e) {
  if (e == null) return '—';
  const n = Number(e);
  if (Math.abs(n) >= 99) return n > 0 ? '#+' : '#-';
  return (n >= 0 ? '+' : '') + n.toFixed(2);
}

// ─── Auto-review main line (engine only) ─────────────────────────────
$('an-btn-review').onclick = startReview;
$('an-btn-stop').onclick   = () => { reviewing = false; };

async function startReview() {
  if (gameIdx < 0) return;
  const g = games[gameIdx];
  const main = collectMainLine(g);
  if (!main.length) return alert('No main-line moves to review.');
  reviewing = true;
  setProg(0, main.length, 'rev');
  try {
    const eng = await window.MiniChessEngine.getEngine();
    for (let i = 0; i < main.length && reviewing; i++) {
      const { node, fenBefore, fenAfter } = main[i];
      const before = await analyseFen(eng, fenBefore, 12);
      const after  = await analyseFen(eng, fenAfter,  12);
      const evalB = scoreToWhitePawns(before.score, fenBefore);
      const evalA = scoreToWhitePawns(after.score,  fenAfter);
      const drop  = node.side === 'w' ? (evalB - evalA) : (evalA - evalB);
      const bestSan = before.bestSan || '';
      const isBest  = bestSan && bestSan === node.san;
      let cls;
      if (isBest && Math.abs(drop) <= 0.05) cls = 'best';
      else if (drop <= 0.30)                cls = 'good';
      else if (drop <= 0.99)                cls = 'inaccuracy';
      else if (drop <= 1.99)                cls = 'mistake';
      else                                  cls = 'blunder';
      node.review = { class: cls, drop, evalBefore: evalB, evalAfter: evalA, bestSan, pvSan: before.pvSan || [] };
      // Auto-tag NAG by class if user hasn't set one already.
      if (!node.nag) {
        if      (cls === 'blunder')    node.nag = '??';
        else if (cls === 'mistake')    node.nag = '?';
        else if (cls === 'inaccuracy') node.nag = '?!';
        else if (cls === 'best')       node.nag = '!';
      }
      markDirty();
      setProg(i + 1, main.length, 'rev');
      renderTree();
      renderSummary();
      renderEvalForCurrent();
      renderMoveActions();
    }
  } catch (e) {
    alert('Engine failed: ' + (e.message || e));
  } finally {
    reviewing = false;
  }
}

function collectMainLine(g) {
  const out = [];
  let prev = g.startFen || STARTING_FEN;
  let n = g.root;
  while (n.children[0]) {
    const child = n.children[0];
    out.push({ node: child, fenBefore: prev, fenAfter: child.fen });
    prev = child.fen;
    n = child;
  }
  return out;
}

// ─── Summary card ────────────────────────────────────────────────────
function renderSummary() {
  if (gameIdx < 0) return;
  const counts = { brilliant: 0, best: 0, good: 0, inaccuracy: 0, mistake: 0, blunder: 0 };
  let reviewed = 0, total = 0;
  let n = games[gameIdx].root;
  while (n.children[0]) {
    n = n.children[0]; total++;
    const r = n.review;
    if (r && r.class && counts[r.class] != null) { counts[r.class]++; reviewed++; }
  }
  const sum = $('an-summary');
  if (reviewed === 0) { sum.style.display = 'none'; return; }
  sum.style.display = '';
  $('cnt-brilliant').textContent = counts.brilliant + counts.best;
  $('cnt-good').textContent      = counts.good;
  $('cnt-inacc').textContent     = counts.inaccuracy;
  $('cnt-mist').textContent      = counts.mistake;
  $('cnt-blund').textContent     = counts.blunder;
  $('an-rev-progress').textContent = reviewed + ' / ' + total + ' reviewed';
}

// ─── Stockfish multipv 5 panel ───────────────────────────────────────
let mpvOn = false;
let mpvSlots = [null, null, null, null, null];
let mpvListener = null;
let mpvDepth = 0, mpvNps = 0;

$('mpv-toggle').onclick = async () => {
  mpvOn = !mpvOn;
  $('mpv-toggle').classList.toggle('on', mpvOn);
  $('mpv-toggle').textContent = mpvOn ? 'Stop' : 'Start';
  if (mpvOn) startMpv();
  else stopMpv();
};

async function startMpv() {
  try {
    const eng = await window.MiniChessEngine.getEngine();
    const fen = curFen();
    eng.postMessage('stop');
    eng.postMessage('setoption name MultiPV value 5');
    if (mpvListener) eng.removeEventListener('message', mpvListener);
    mpvSlots = [null, null, null, null, null];
    mpvDepth = 0; mpvNps = 0;

    mpvListener = (e) => {
      const line = (typeof e.data === 'string') ? e.data : '';
      if (!line) return;
      if (line.startsWith('info ') && line.includes(' multipv ')) {
        const m = parseInfoMpv(line);
        if (m && m.multipv >= 1 && m.multipv <= 5) {
          mpvSlots[m.multipv - 1] = {
            score: m.score, depth: m.depth, pvUci: m.pv,
            pvSan: window.MiniChessEngine.pvToSan(fen, m.pv) || [],
          };
          if (m.depth) mpvDepth = m.depth;
          if (m.nps)   mpvNps   = m.nps;
          renderMpv(fen);
        }
      }
    };
    eng.addEventListener('message', mpvListener);
    eng.postMessage('position fen ' + fen);
    eng.postMessage('go depth 22');
  } catch (e) {
    $('mpv-list').innerHTML = '<div class="mpv-empty" style="color:#dc2626">Engine failed to start: ' + esc(e.message || e) + '</div>';
  }
}

async function stopMpv() {
  try {
    const eng = await window.MiniChessEngine.getEngine();
    eng.postMessage('stop');
    eng.postMessage('setoption name MultiPV value 1');
    if (mpvListener) { eng.removeEventListener('message', mpvListener); mpvListener = null; }
  } catch (e) {}
}

function curFen() {
  if (gameIdx < 0) return STARTING_FEN;
  return getFenAt(games[gameIdx], curPath);
}

// Restart MultiPV when the current position changes.
const _afterNav = afterNav;
afterNav = function() {
  _afterNav();
  if (mpvOn) {
    // Bounce engine onto new fen.
    (async () => {
      try {
        const eng = await window.MiniChessEngine.getEngine();
        eng.postMessage('stop');
        mpvSlots = [null, null, null, null, null];
        eng.postMessage('position fen ' + curFen());
        eng.postMessage('go depth 22');
        renderMpv(curFen());
      } catch (e) {}
    })();
  }
};

function parseInfoMpv(line) {
  const t = line.split(' ');
  const out = { pv: '' };
  for (let i = 0; i < t.length; i++) {
    const tok = t[i];
    if      (tok === 'depth')   out.depth   = parseInt(t[++i], 10);
    else if (tok === 'multipv') out.multipv = parseInt(t[++i], 10);
    else if (tok === 'nps')     out.nps     = parseInt(t[++i], 10);
    else if (tok === 'score')   out.score   = { kind: t[++i], val: parseInt(t[++i], 10) };
    else if (tok === 'pv')      { out.pv = t.slice(i + 1).join(' '); break; }
  }
  return out;
}

function renderMpv(fen) {
  $('mpv-info').textContent = 'depth ' + (mpvDepth || '—') + ' / nps ' + (mpvNps ? Math.round(mpvNps/1000) + 'k' : '—');
  const list = $('mpv-list');
  if (mpvSlots.every(s => !s)) {
    list.innerHTML = '<div class="mpv-empty">Engine warming up…</div>';
    return;
  }
  list.innerHTML = '';
  for (let i = 0; i < 5; i++) {
    const s = mpvSlots[i];
    if (!s) continue;
    const pawns = scoreToWhitePawns(s.score, fen);
    const evalLabel = (Math.abs(pawns) >= 99) ? (pawns > 0 ? '#+' : '#-') : ((pawns >= 0 ? '+' : '') + pawns.toFixed(2));
    const evalCls = Math.abs(pawns) < 0.2 ? '' : (pawns >= 0 ? 'up' : 'down');
    const row = document.createElement('div');
    row.className = 'mpv-row';
    const sanSpan = (s.pvSan || []).slice(0, 12).map((mv, idx) => {
      return '<span class="pv-mv" data-pv-idx="' + idx + '">' + esc(mv) + '</span>';
    }).join(' ');
    row.innerHTML =
        '<span class="mpv-rank">#' + (i+1) + '</span>'
      + '<span class="mpv-eval ' + evalCls + '">' + evalLabel + '</span>'
      + '<span class="mpv-line">' + sanSpan + '</span>';
    // Click first move to play it.
    row.querySelectorAll('.pv-mv').forEach(el => {
      el.addEventListener('click', (e) => {
        e.stopPropagation();
        const k = parseInt(el.dataset.pvIdx, 10);
        playPvMove(s.pvSan, k);
      });
    });
    list.appendChild(row);
  }
}

async function playPvMove(pvSan, count) {
  // Walk through count+1 moves on the board, applying SANs to current position.
  if (gameIdx < 0) return;
  const sans = (pvSan || []).slice(0, count + 1);
  for (const san of sans) {
    const g = games[gameIdx];
    const parent = curPath.length === 0 ? g.root : getNodeAtPath(g, curPath);
    if (!parent) break;
    const existing = findChildIdx(parent, san);
    if (existing >= 0) {
      curPath = curPath.concat([existing]);
      continue;
    }
    // Compute resulting FEN by replaying SAN on a parsed state.
    const preFen = getFenAt(g, curPath);
    let newFen = null;
    try {
      const state = MiniChess.parseFen(preFen);
      const ok    = MiniChess.applySAN(state, san);
      if (ok) newFen = MiniChess.toFen(state);
    } catch (e) {}
    if (!newFen) break;
    const parts = preFen.split(' ');
    const turnBefore = parts[1];
    const fullMove   = parseInt(parts[5], 10) || 1;
    const moveNumber = fullMove;
    const node = newNode({
      san, uci: '', fen: newFen,
      moveNumber, side: turnBefore, isUser: true, children: [],
    });
    parent.children.push(node);
    curPath = curPath.concat([parent.children.length - 1]);
    markDirty();
  }
  afterNav();
}

// ─── One-shot engine helper (for review) ─────────────────────────────
function analyseFen(engine, fen, depth) {
  return new Promise((res, rej) => {
    let lastInfo  = null;
    let bestCoord = null;
    const onMessage = (e) => {
      const line = (typeof e.data === 'string') ? e.data : '';
      if (line.startsWith('info ') && line.includes(' multipv 1') && line.includes('pv ')) {
        const m = parseInfoMpv(line);
        if (m && m.score) lastInfo = m;
      } else if (line.startsWith('info ') && line.includes('pv ') && !line.includes(' multipv ')) {
        const m = parseInfoMpv(line);
        if (m && m.score) lastInfo = m;
      } else if (line.startsWith('bestmove')) {
        const mv = line.split(' ')[1];
        if (mv && mv !== '(none)') bestCoord = mv;
        engine.removeEventListener('message', onMessage);
        const score  = lastInfo?.score || { kind: 'cp', val: 0 };
        const pvSan  = lastInfo ? (window.MiniChessEngine.pvToSan(fen, lastInfo.pv) || []) : [];
        let bestSan  = pvSan[0] || null;
        if (!bestSan && bestCoord) {
          try {
            const r = window.MiniChessEngine.applyCoord(MiniChess.parseFen(fen), bestCoord);
            bestSan = r ? r.san : bestCoord;
          } catch (e) { bestSan = bestCoord; }
        }
        res({ score, bestSan, pvSan });
      }
    };
    engine.addEventListener('message', onMessage);
    engine.postMessage('stop');
    engine.postMessage('setoption name MultiPV value 1');
    engine.postMessage('position fen ' + fen);
    engine.postMessage('go depth ' + depth);
    setTimeout(() => { engine.removeEventListener('message', onMessage); rej(new Error('engine timeout')); }, 12000);
  });
}
function scoreToWhitePawns(score, fen) {
  if (!score) return 0;
  const turn = MiniChess.parseFen(fen).turn;
  const sign = turn === 'w' ? 1 : -1;
  if (score.kind === 'mate') return score.val * sign > 0 ? 99 : -99;
  return (score.val * sign) / 100;
}

// ─── Save (whole tree) ───────────────────────────────────────────────
function markDirty() {
  dirty = true;
  const p = $('an-savepill');
  p.className = 'savepill dirty';
  $('an-savetext').textContent = 'Unsaved';
}
function markSaving() {
  const p = $('an-savepill');
  p.className = 'savepill saving';
  $('an-savetext').textContent = 'Saving…';
}
function markSaved() {
  dirty = false;
  const p = $('an-savepill');
  p.className = 'savepill saved';
  $('an-savetext').textContent = 'Saved';
}

function stripNode(n) {
  return {
    san: n.san, uci: n.uci, fen: n.fen,
    moveNumber: n.moveNumber, side: n.side,
    nag: n.nag || '', comment: n.comment || '',
    ai: n.ai || '', aiSource: n.aiSource || '',
    review: n.review || null, isUser: !!n.isUser,
    children: (n.children || []).map(stripNode),
  };
}
function serializeTree() {
  return {
    games: games.map(g => ({
      name: g.name,
      startFen: g.startFen,
      root: { children: (g.root.children || []).map(stripNode) },
    })),
  };
}

$('an-save-btn').onclick = saveAll;

async function saveAll() {
  if (saveBusy) return;
  saveBusy = true;
  markSaving();
  try {
    const resp = await fetch(URL_TREE, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF, 'Accept': 'application/json' },
      body: JSON.stringify({ tree: serializeTree() }),
    });
    let data = null;
    try { data = await resp.json(); } catch (e) {}
    if (!resp.ok) {
      // Surface the real backend error so we know if it's migration / route / 500.
      const txt = (data && (data.error || data.message)) ? (data.error || data.message) : ('HTTP ' + resp.status);
      // Detect "tree column missing" → tell the user how to fix.
      if (resp.status === 500 && /tree/i.test(txt) && /column|unknown|field/i.test(txt)) {
        throw new Error('The tree column does not exist yet. Run database/sql/missing_tables_fallback.sql in phpMyAdmin (or `php artisan migrate`) and try again.');
      }
      throw new Error(txt);
    }
    markSaved();
  } catch (e) {
    const p = $('an-savepill');
    p.className = 'savepill dirty';
    $('an-savetext').textContent = 'Save failed';
    alert('Save failed: ' + (e.message || e));
  } finally {
    saveBusy = false;
  }
}

// Ctrl/Cmd+S → save
document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
    e.preventDefault();
    saveAll();
  }
});

// ─── Misc ────────────────────────────────────────────────────────────
function setProg(done, total, kind) {
  if (kind === 'rev') {
    $('an-prog-text').textContent = done + ' / ' + total;
    $('an-prog-fill').style.width = total ? Math.round((done/total)*100) + '%' : '0%';
  }
}
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// ─── Boot ────────────────────────────────────────────────────────────
markSaved();
})();
</script>
@endpush

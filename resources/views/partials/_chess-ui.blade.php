{{--
    Shared chess-UI design tokens.
    Include this partial once on any page that renders any of:
      • position lists (left column)
      • SAN move strips / scoresheets / variation trees
      • chapter / study cards
      • board + eval bar + controls
      • Lichess-style classification colours (best / brilliant / good / inaccuracy / mistake / blunder)

    Used by:
      • student/analyse/show.blade.php
      • student/classes/live.blade.php (live class viewer)
      • student/homework/solve.blade.php (homework trainer)
      • coach/classes/live.blade.php (live class)
      • coach/homework/show.blade.php
      • coach/database/chapter.blade.php
      • admin/library + admin/database

    Change the tokens here once — the UI updates everywhere.
--}}
@once
@push('styles')
<style>
/* ─── Tokens ────────────────────────────────────────────────────────── */
:root {
  --cui-board:    460px;
  --cui-eval:     16px;
  --cui-radius:   10px;
  --cui-radius-l: 14px;
  --cui-bd:       #e2e8f0;
  --cui-bd-soft:  #f1f5f9;
  --cui-text-1:   #0f172a;
  --cui-text-2:   #334155;
  --cui-text-3:   #64748b;
  --cui-text-mut: #94a3b8;
  --cui-accent:   #4f46e5;
  --cui-accent-2: #6366f1;
  --cui-bg-soft:  #f8fafc;

  /* Lichess-style classification colours */
  --cui-cls-brilliant-bg:#ecfdf5; --cui-cls-brilliant-fg:#065f46; --cui-cls-brilliant-on:#10b981;
  --cui-cls-best-bg:     #f0fdf4; --cui-cls-best-fg:     #15803d; --cui-cls-best-on:     #16a34a;
  --cui-cls-good-bg:     #f0fdfa; --cui-cls-good-fg:     #0f766e; --cui-cls-good-on:     #14b8a6;
  --cui-cls-inacc-bg:    #fefce8; --cui-cls-inacc-fg:    #854d0e; --cui-cls-inacc-on:    #ca8a04;
  --cui-cls-mistake-bg:  #fff7ed; --cui-cls-mistake-fg:  #9a3412; --cui-cls-mistake-on:  #ea580c;
  --cui-cls-blunder-bg:  #fef2f2; --cui-cls-blunder-fg:  #991b1b; --cui-cls-blunder-on:  #dc2626;
}

/* ─── Shell: standard 3-column page ────────────────────────────────── */
.cui-shell      { display:grid; grid-template-columns: 240px minmax(calc(var(--cui-board) + var(--cui-eval) + 14px), 1fr) 420px; gap:14px; align-items:flex-start }
@media (max-width: 1320px) { .cui-shell { grid-template-columns: 220px 1fr; } .cui-shell > .cui-right { grid-column: 1 / -1 } }
@media (max-width: 860px)  { .cui-shell { grid-template-columns: 1fr; } :root { --cui-board: min(360px, calc(100vw - 64px)); } }

/* ─── Position list (left column) ──────────────────────────────────── */
.cui-pos-card   { background:#fff; border:1px solid var(--cui-bd); border-radius:var(--cui-radius); overflow:hidden }
.cui-pos-head   { padding:11px 14px; border-bottom:1px solid var(--cui-bd-soft); font-size:12px; font-weight:600; color:var(--cui-text-3); display:flex; align-items:center; gap:8px }
.cui-pos-head .cui-count { margin-left:auto; font-weight:700; color:var(--cui-text-1) }
.cui-pos-list   { max-height:560px; overflow:auto }
.cui-pos-row    { padding:10px 14px; border-bottom:1px solid var(--cui-bd-soft); cursor:pointer; font-size:13px; color:var(--cui-text-2); display:flex; flex-direction:column; gap:3px; transition:background .12s }
.cui-pos-row:hover { background:var(--cui-bg-soft) }
.cui-pos-row.active { background:#f5f3ff; color:#4338ca; font-weight:600; border-left:3px solid var(--cui-accent-2); padding-left:11px }
.cui-pos-row .cui-pos-name { font-size:13px; line-height:1.3 }
.cui-pos-row .cui-pos-meta { font-size:10px; color:var(--cui-text-mut); display:flex; gap:6px; align-items:center; margin-top:2px }
.cui-pos-row .cui-pos-meta .dot { width:5px; height:5px; border-radius:50%; background:var(--cui-bd) }
.cui-pos-row .cui-pos-meta .ok { color:#16a34a; font-weight:600 }

/* ─── Centre: board + evalbar + controls ──────────────────────────── */
.cui-center      { display:flex; flex-direction:column; gap:10px; min-width:0 }
.cui-board-wrap  { width:calc(var(--cui-board) + var(--cui-eval) + 8px); margin:0 auto; display:flex; flex-direction:column; gap:10px }
.cui-board-row   { display:flex; gap:8px; align-items:flex-start }
.cui-evalbar     { width:var(--cui-eval); height:var(--cui-board); background:#262626; border-radius:3px; overflow:hidden; position:relative; flex-shrink:0; border:1px solid #1f2937 }
.cui-evalbar-fill{ position:absolute; left:0; right:0; bottom:0; background:#fafafa; transition:height .35s cubic-bezier(.4,0,.2,1) }
.cui-evalbar-num { position:absolute; left:0; right:0; top:50%; transform:translateY(-50%); text-align:center; font-size:9px; font-weight:700; mix-blend-mode:difference; color:#fff; pointer-events:none; font-family:ui-monospace, monospace }
.cui-controls    { width:100%; display:flex; gap:4px; padding:6px; background:#fff; border:1px solid var(--cui-bd); border-radius:8px; box-sizing:border-box; justify-content:center; align-items:center }
.cui-controls .ctl { background:transparent; border:none; padding:6px 10px; font-size:14px; cursor:pointer; color:var(--cui-text-2); border-radius:5px; min-width:34px; transition:all .1s }
.cui-controls .ctl:hover { background:var(--cui-bd-soft); color:var(--cui-text-1) }
.cui-controls .ctl:active { transform:scale(.95) }
.cui-controls .ctr { font-family:ui-monospace, monospace; font-size:12px; color:var(--cui-text-3); padding:6px 12px; min-width:60px; text-align:center }
.cui-controls .sep { width:1px; background:var(--cui-bd); margin:4px 4px; align-self:stretch }

.cui-eval-pill   { font-family:ui-monospace, monospace; font-size:12px; font-weight:600; padding:4px 10px; border-radius:6px; background:#1f2937; color:#fff; min-width:58px; text-align:center; letter-spacing:-.01em }
.cui-eval-pill.up      { background:#fafafa; color:var(--cui-text-1); border:1px solid var(--cui-bd) }
.cui-eval-pill.down    { background:#1f2937; color:#fff }
.cui-eval-pill.neutral { background:var(--cui-bd-soft); color:var(--cui-text-2); border:1px solid var(--cui-bd) }

/* ─── Move strip / scoresheet / tree (right column) ───────────────── */
.cui-tree        { background:#fff; border:1px solid var(--cui-bd); border-radius:var(--cui-radius); padding:12px 14px; line-height:1.7; font-size:13px; color:var(--cui-text-2); max-height:340px; overflow:auto }
.cui-tree-empty  { text-align:center; color:var(--cui-text-mut); font-size:12px; font-style:italic; padding:24px 0 }
.cui-tree .mn    { font-family:ui-monospace, monospace; font-size:11px; color:var(--cui-text-mut); margin:0 4px 0 2px; font-weight:600 }
.cui-tree .mv    { display:inline-block; padding:1px 6px; border-radius:4px; cursor:pointer; font-family:ui-monospace, monospace; font-size:13px; color:var(--cui-text-1); transition:background .1s, color .1s }
.cui-tree .mv:hover{ background:#eef2ff }
.cui-tree .mv.active { background:#4338ca; color:#fff; font-weight:700 }
.cui-tree .mv .nag   { font-weight:700; margin-left:1px }
.cui-tree .mv.brilliant { color:var(--cui-cls-brilliant-fg) } .cui-tree .mv.brilliant.active{ background:var(--cui-cls-brilliant-on); color:#fff }
.cui-tree .mv.best      { color:var(--cui-cls-best-fg) }      .cui-tree .mv.best.active     { background:var(--cui-cls-best-on);      color:#fff }
.cui-tree .mv.good      { color:var(--cui-cls-good-fg) }      .cui-tree .mv.good.active     { background:var(--cui-cls-good-on);      color:#fff }
.cui-tree .mv.inaccuracy{ color:var(--cui-cls-inacc-fg) }     .cui-tree .mv.inaccuracy.active{ background:var(--cui-cls-inacc-on);   color:#fff }
.cui-tree .mv.mistake   { color:var(--cui-cls-mistake-fg) }   .cui-tree .mv.mistake.active  { background:var(--cui-cls-mistake-on);   color:#fff }
.cui-tree .mv.blunder   { color:var(--cui-cls-blunder-fg) }   .cui-tree .mv.blunder.active  { background:var(--cui-cls-blunder-on);   color:#fff }
.cui-tree .mv.user      { font-style:italic; border-bottom:1px dashed var(--cui-text-mut) }
.cui-tree .var-line     { color:var(--cui-text-3); font-style:italic; padding-left:14px; border-left:2px solid var(--cui-bd); margin:3px 0 3px 4px; font-size:12px; display:block; line-height:1.7 }
.cui-tree .var-line .mv { font-style:normal }
.cui-tree .cmt-inline   { color:var(--cui-text-2); font-size:12px; font-style:italic; background:#fefce8; border:1px solid #fef9c3; padding:1px 6px; border-radius:3px; margin:0 3px }

/* ─── Chapter / study summary card ─────────────────────────────────── */
.cui-card        { background:#fff; border:1px solid var(--cui-bd); border-radius:var(--cui-radius); padding:12px 14px }
.cui-card .cui-card-head { font-size:12px; font-weight:600; color:var(--cui-text-3); margin-bottom:8px; display:flex; align-items:center; gap:8px }
.cui-card .cui-card-head .cui-count { margin-left:auto; color:var(--cui-text-mut); font-weight:600; text-transform:none; letter-spacing:0 }

/* ─── Tabs ─────────────────────────────────────────────────────────── */
.cui-tabs        { display:flex; gap:2px; padding:4px; background:var(--cui-bd-soft); border-radius:8px }
.cui-tab         { flex:1; padding:8px 12px; font-size:12px; font-weight:600; border-radius:6px; cursor:pointer; text-align:center; color:var(--cui-text-3); transition:all .15s; user-select:none }
.cui-tab:hover   { color:var(--cui-text-2) }
.cui-tab.active  { background:#fff; color:var(--cui-text-1); box-shadow:0 1px 2px rgba(15,23,42,.06) }

/* ─── NAG buttons (consistent across analyse / coach / homework) ──── */
.cui-nag-row     { display:flex; gap:4px; flex-wrap:wrap }
.cui-nag-btn     { padding:5px 10px; border-radius:6px; border:1px solid var(--cui-bd); background:#fff; cursor:pointer; font-family:ui-monospace, monospace; font-size:13px; font-weight:700; color:var(--cui-text-2); transition:all .12s; min-width:34px }
.cui-nag-btn:hover { background:var(--cui-bg-soft); border-color:#cbd5e1 }
.cui-nag-btn.active                 { background:#4338ca; color:#fff; border-color:#4338ca }
.cui-nag-btn[data-nag="!!"].active  { background:var(--cui-cls-brilliant-on); border-color:var(--cui-cls-brilliant-on) }
.cui-nag-btn[data-nag="!"].active   { background:var(--cui-cls-best-on);      border-color:var(--cui-cls-best-on) }
.cui-nag-btn[data-nag="!?"].active  { background:var(--cui-cls-good-on);      border-color:var(--cui-cls-good-on) }
.cui-nag-btn[data-nag="?!"].active  { background:var(--cui-cls-inacc-on);     border-color:var(--cui-cls-inacc-on) }
.cui-nag-btn[data-nag="?"].active   { background:var(--cui-cls-mistake-on);   border-color:var(--cui-cls-mistake-on) }
.cui-nag-btn[data-nag="??"].active  { background:var(--cui-cls-blunder-on);   border-color:var(--cui-cls-blunder-on) }
.cui-nag-btn.clear { color:var(--cui-text-mut) }

</style>
@endpush
@endonce

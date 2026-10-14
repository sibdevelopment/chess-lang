{{--
    Cumulative live-class leaderboard.
    Required vars:
      $liveClass — LiveClass model
      $role      — 'coach' | 'student'
    Shows EVERY student in the group with their TOTAL points across every
    question the coach has asked in this class so far. Always visible.
--}}
@once
@push('styles')
<style>
.lb-card     { padding:0; overflow:hidden; }
.lb-head     { padding:9px 12px; border-bottom:1px solid var(--border); font-size:13px; font-weight:700; display:flex; align-items:center; gap:8px; background:#fffbeb; color:#7c2d12; }
.lb-head .lb-tag { font-size:10px; font-weight:600; background:#fde68a; color:#78350f; padding:2px 7px; border-radius:99px }
.lb-list     { max-height:340px; overflow:auto; padding:4px 0; }
.lb-row      { display:flex; align-items:center; gap:8px; padding:7px 12px; border-bottom:1px solid #f1f5f9; font-size:13px; transition:background .15s; }
.lb-row:last-child { border-bottom:none }
.lb-row.me   { background:#ecfeff; }
.lb-rank     { width:22px; text-align:center; font-weight:700; color:#94a3b8; font-size:12px; }
.lb-rank.r1  { color:#ca8a04; font-size:14px; }
.lb-rank.r2  { color:#94a3b8; font-size:13px; }
.lb-rank.r3  { color:#a16207; font-size:13px; }
.lb-name     { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:500; color:#0f172a; }
.lb-pts      { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:99px; font-weight:700; font-size:12px; min-width:32px; text-align:center; }
.lb-pts.zero { background:#f1f5f9; color:#94a3b8; font-weight:600; }
.lb-meta     { font-size:10px; color:#64748b; white-space:nowrap; }
.lb-empty    { padding:14px 12px; font-size:12px; color:#94a3b8; font-style:italic; text-align:center; }
.lb-foot     { padding:8px 12px; border-top:1px solid var(--border); background:#f8fafc; font-size:11px; color:#64748b; display:flex; justify-content:space-between; }
</style>
@endpush
@endonce

<div class="card card-sm lb-card" id="lb-root">
  <div class="lb-head">
    <span>Leaderboard</span>
    <span class="lb-tag">Total points</span>
    <span style="flex:1"></span>
    <span id="lb-qcount" class="lb-meta">—</span>
  </div>
  <div id="lb-list" class="lb-list">
    <div class="lb-empty">Loading leaderboard…</div>
  </div>
  <div class="lb-foot">
    <span>Instant updates · 3 s backup</span>
    <span id="lb-status">●</span>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const LB_URL = @json($role === 'coach'
                     ? route('coach.class.leaderboard', $liveClass)
                     : route('student.class.leaderboard', $liveClass));
  const LB_ROOM_ID = @json($liveClass->room_id);
  const ME_ID  = {{ auth()->id() }};
  const list   = document.getElementById('lb-list');
  const qCount = document.getElementById('lb-qcount');
  const stat   = document.getElementById('lb-status');
  let pollTimer = null;
  let pollInFlight = false;

  function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  function render(payload) {
    const students = payload.students || [];
    const qAsked   = payload.questions_asked || 0;
    qCount.textContent = qAsked ? (qAsked + ' question' + (qAsked === 1 ? '' : 's') + ' asked') : 'No questions yet';

    if (students.length === 0) {
      list.innerHTML = '<div class="lb-empty">No students in this group yet.</div>';
      return;
    }

    // Build a single innerHTML string for fewer reflows.
    const rows = students.map((s, i) => {
      const rank   = i + 1;
      const rankCls = rank === 1 ? 'r1' : rank === 2 ? 'r2' : rank === 3 ? 'r3' : '';
      const mine   = s.user_id === ME_ID ? ' me' : '';
      const pts    = s.total_points;
      const ptsCls = pts > 0 ? '' : ' zero';
      const meta   = s.questions_attempted
        ? (s.questions_solved + '/' + s.questions_attempted + ' solved')
        : (qAsked ? 'no attempts' : '');
      return ''
        + '<div class="lb-row' + mine + '">'
        +   '<span class="lb-rank ' + rankCls + '">' + rank + '</span>'
        +   '<div class="av av-sm" style="background:' + escapeHtml(s.avatar_color) + ';font-size:10px">' + escapeHtml(s.initials) + '</div>'
        +   '<span class="lb-name">' + escapeHtml(s.name) + '</span>'
        +   (meta ? '<span class="lb-meta">' + escapeHtml(meta) + '</span>' : '')
        +   '<span class="lb-pts' + ptsCls + '">' + pts + '</span>'
        + '</div>';
    }).join('');
    list.innerHTML = rows;
  }

  async function poll() {
    if (pollTimer) clearTimeout(pollTimer);
    if (pollInFlight) return;
    pollInFlight = true;
    try {
      const url = LB_URL + (LB_URL.includes('?') ? '&' : '?') + '_=' + Date.now();
      const r = await fetch(url, {
        headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
      });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const data = await r.json();
      render(data);
      stat.style.color = '#16a34a';
      stat.textContent = '● live';
    } catch (e) {
      stat.style.color = '#dc2626';
      stat.textContent = '● reconnecting';
    } finally {
      pollInFlight = false;
      pollTimer = setTimeout(poll, 3000);
    }
  }

  function subscribeRealtimeLeaderboard() {
    if (!window.Echo) return;
    window.Echo.channel('class.' + LB_ROOM_ID)
      .listen('.question.changed', () => poll());
  }
  if (window.Echo) subscribeRealtimeLeaderboard();
  else window.addEventListener('archer:realtime-ready', subscribeRealtimeLeaderboard, { once:true });

  poll();
})();
</script>
@endpush

{{--
    Live-class chat panel. Required vars:
      $liveClass — the LiveClass model
      $role      — 'coach' | 'student' (drives endpoint URLs and label colors)
--}}
@once
@push('styles')
<style>
.cc-card    { display:flex; flex-direction:column; max-height:380px; min-height:240px; padding:0; overflow:hidden }
.cc-head    { padding:9px 12px; border-bottom:1px solid var(--border); font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px }
.cc-head .cc-pill { font-size:10px; background:#dbeafe; color:#1e40af; padding:2px 7px; border-radius:99px; font-weight:600 }
.cc-list    { flex:1; overflow:auto; padding:10px 12px; display:flex; flex-direction:column; gap:6px; background:#f8fafc }
.cc-msg     { max-width:85%; font-size:13px; line-height:1.35; padding:7px 10px; border-radius:10px; word-wrap:break-word }
.cc-msg .cc-who { font-size:10px; font-weight:600; opacity:.7; margin-bottom:2px; display:block }
.cc-msg.coach   { background:#fef3c7; color:#78350f; border:1px solid #fde68a; align-self:flex-start; }
.cc-msg.student { background:#e0e7ff; color:#1e3a8a; border:1px solid #c7d2fe; align-self:flex-end; }
.cc-msg.mine    { box-shadow:0 0 0 2px #c7d2fe }
.cc-form    { display:flex; gap:6px; padding:9px 10px; border-top:1px solid var(--border); background:#fff }
.cc-form input { flex:1; padding:7px 10px; border:1px solid var(--border); border-radius:7px; font-size:13px }
.cc-form input:disabled { background:#f1f5f9; color:#94a3b8 }
.cc-empty   { text-align:center; color:#94a3b8; font-size:12px; font-style:italic; padding:18px 0 }
.cc-error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; border-radius:8px; padding:8px 10px; font-size:12px; line-height:1.4; margin:6px 0 }
.cc-error b { font-weight:700 }
</style>
@endpush
@endonce

<div class="card card-sm cc-card" id="cc-root">
  <div class="cc-head">
    <span>Class chat</span>
    <span class="cc-pill" id="cc-online-pill" style="display:none">0 online</span>
    <span class="cc-pill" id="cc-realtime-pill" style="margin-left:auto;background:#f1f5f9;color:#64748b">Connecting…</span>
  </div>
  <div id="cc-list" class="cc-list">
    <div class="cc-empty">Loading messages…</div>
  </div>
  <form class="cc-form" id="cc-form" autocomplete="off">
    <input id="cc-input" type="text" maxlength="500" placeholder="Type a message…" required>
    <button class="btn btn-primary btn-sm" type="submit" id="cc-send-btn">Send</button>
  </form>
</div>

@push('scripts')
<script>
(function(){
  const ROLE        = @json($role);
  const CHAT_ROOM_ID= @json($liveClass->room_id);
  const ME_ID       = {{ auth()->id() }};
  const LIST_URL    = @json($role === 'coach'
                        ? route('coach.class.chat.list', $liveClass)
                        : route('student.class.chat.list', $liveClass));
  const SEND_URL    = @json($role === 'coach'
                        ? route('coach.class.chat.send', $liveClass)
                        : route('student.class.chat.send', $liveClass));
  const CSRF        = '{{ csrf_token() }}';
  const list        = document.getElementById('cc-list');
  const form        = document.getElementById('cc-form');
  const input       = document.getElementById('cc-input');
  const sendBtn     = document.getElementById('cc-send-btn');
  const realtimePill= document.getElementById('cc-realtime-pill');
  let lastSeenId    = 0;
  let consecutiveErrors = 0;
  const seenIds     = new Set();

  function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function fmtTime(t){ try { return new Date(t).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); } catch(e){ return ''; } }

  function clearError() {
    const ex = list.querySelector('.cc-error');
    if (ex) ex.remove();
  }

  function showError(text, hint) {
    clearError();
    const empty = list.querySelector('.cc-empty');
    if (empty) empty.remove();
    const div = document.createElement('div');
    div.className = 'cc-error';
    div.innerHTML = '<b>Chat unavailable</b><br>' + escapeHtml(text || 'Unknown error.') +
                    (hint ? '<br><span style="opacity:.8">' + escapeHtml(hint) + '</span>' : '');
    list.prepend(div);
  }

  function append(m){
    const id = Number(m && m.id);
    if (id && seenIds.has(id)) return;
    if (id) seenIds.add(id);
    const div = document.createElement('div');
    const mine = m.user_id === ME_ID;
    div.className = 'cc-msg ' + m.role + (mine ? ' mine' : '');
    div.innerHTML = '<span class="cc-who">' + escapeHtml(m.user_name) + ' · ' + escapeHtml(fmtTime(m.created_at)) + '</span>'
                  + escapeHtml(m.body);
    list.appendChild(div);
  }

  function subscribeRealtimeChat() {
    if (!window.Echo) return;
    const channel = window.Echo.channel('class.' + CHAT_ROOM_ID)
      .listen('.chat.message', payload => {
        const message = payload && payload.message;
        if (!message) return;
        clearError();
        const empty = list.querySelector('.cc-empty');
        if (empty) empty.remove();
        append(message);
        lastSeenId = Math.max(lastSeenId, Number(message.id) || 0);
        list.scrollTop = list.scrollHeight;
        realtimePill.textContent = 'Instant ✓';
        realtimePill.style.background = '#dcfce7';
        realtimePill.style.color = '#166534';
      });
    channel.subscribed(() => {
      realtimePill.textContent = 'Instant';
      realtimePill.style.background = '#dcfce7';
      realtimePill.style.color = '#166534';
    });
  }
  if (window.Echo) subscribeRealtimeChat();
  else window.addEventListener('archer:realtime-ready', subscribeRealtimeChat, { once:true });

  async function poll(){
    let nextDelay = 2500;
    try {
      const r = await fetch(LIST_URL + '?since=' + lastSeenId, {
        headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
      });
      // Try to parse as JSON regardless — server returns JSON even on 5xx.
      let data = null;
      try { data = await r.json(); } catch (e) {}
      if (!r.ok || (data && data.error)) {
        consecutiveErrors++;
        const msg = (data && data.error) ? data.error : ('HTTP ' + r.status);
        const hint = r.status === 403 ? 'You are not in this class batch.'
                    : r.status === 419 ? 'Your session expired — refresh the page.'
                    : r.status === 500 ? 'A server error occurred. Run `php artisan migrate` if you haven\'t yet.'
                    : '';
        showError(msg, hint);
        // Back off on repeated errors so we don't hammer the server
        nextDelay = consecutiveErrors > 3 ? 8000 : 4000;
      } else {
        consecutiveErrors = 0;
        clearError();
        if (data && data.messages && data.messages.length) {
          const empty = list.querySelector('.cc-empty');
          if (empty) empty.remove();
          data.messages.forEach(m => { append(m); lastSeenId = Math.max(lastSeenId, m.id); });
          list.scrollTop = list.scrollHeight;
        } else if (lastSeenId === 0 && !list.querySelector('.cc-msg')) {
          const empty = list.querySelector('.cc-empty');
          if (empty) empty.textContent = 'No messages yet — say hi!';
        }
        if (data && typeof data.online === 'number') {
          const pill = document.getElementById('cc-online-pill');
          pill.style.display = 'inline-block';
          pill.textContent = data.online + ' online';
        }
      }
    } catch(e) {
      consecutiveErrors++;
      showError('Network error — could not reach the server.', 'Trying again automatically.');
      nextDelay = consecutiveErrors > 3 ? 8000 : 4000;
    }
    setTimeout(poll, nextDelay);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = (input.value || '').trim();
    if (!body) return;
    const original = body;
    input.value = '';
    sendBtn.disabled = true;
    try {
      const r = await fetch(SEND_URL, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN': CSRF, 'Accept':'application/json'},
        body: JSON.stringify({ body: original })
      });
      let data = null;
      try { data = await r.json(); } catch(e) {}
      if (!r.ok || !data || !data.message) {
        const err = (data && data.error) ? data.error : ('HTTP ' + r.status);
        showError('Could not send: ' + err);
        input.value = original;   // restore so the student doesn't lose their text
        return;
      }
      clearError();
      const empty = list.querySelector('.cc-empty');
      if (empty) empty.remove();
      append(data.message);
      lastSeenId = Math.max(lastSeenId, data.message.id);
      list.scrollTop = list.scrollHeight;
    } catch(err) {
      showError('Network error sending message.', 'Your text was kept — try again.');
      input.value = original;
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  });

  poll();
})();
</script>
@endpush

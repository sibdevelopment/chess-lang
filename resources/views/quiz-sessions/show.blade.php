@extends('layouts.app')
@section('page-title', $session->name)

@section('sidebar-nav')
@include('quiz-sessions._sidebar')
@endsection

@php
  $u = auth()->user();
  $role = $u->role;
  $rolePrefix = $role;

  // Common routes per role
  $indexRoute      = "$rolePrefix.quiz-sessions.index";
  $cancelRoute     = in_array($role, ['admin','staff'], true) ? "$rolePrefix.quiz-sessions.cancel"     : null;
  $editRoute       = in_array($role, ['admin','staff'], true) ? "$rolePrefix.quiz-sessions.edit"       : null;

  // Coach actions
  $startRoute      = $role === 'coach' ? "coach.quiz-sessions.start"        : null;
  $broadcastRoute  = $role === 'coach' ? "coach.quiz-sessions.broadcast"    : null;
  $endCurrentRoute = $role === 'coach' ? "coach.quiz-sessions.end-current"  : null;
  $endRoute        = $role === 'coach' ? "coach.quiz-sessions.end"          : null;
  $coachPollRoute  = $role === 'coach' ? "coach.quiz-sessions.poll"         : null;

  // Student actions
  $joinRoute        = $role === 'student' ? "student.quiz-sessions.join"   : null;
  $studentPollRoute = $role === 'student' ? "student.quiz-sessions.poll"   : null;
  $answerRoute      = $role === 'student' ? "student.quiz-sessions.answer" : null;

  $statusLabel = match($session->status) {
      'scheduled' => 'Scheduled',
      'live'      => 'LIVE',
      'ended'     => 'Ended',
      'cancelled' => 'Cancelled',
      default     => $session->status,
  };
  $statusBadge = match($session->status) {
      'scheduled' => 'badge-pending',
      'live'      => 'badge-live',
      'ended'     => 'badge-done',
      'cancelled' => 'badge-inactive',
      default     => 'badge-inactive',
  };
@endphp

@push('styles')
<style>
  .qs-hero{background:var(--color-primary);color:#fff;border-radius:12px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
  .qs-hero h1{margin:0;font-size:24px;font-weight:600;letter-spacing:0}
  .qs-hero .meta{font-size:12px;color:rgba(255,255,255,.78);margin-top:6px;display:flex;gap:14px;flex-wrap:wrap}
  .qs-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media (max-width:900px){.qs-grid{grid-template-columns:1fr}}

  .qs-question-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px}
  .qs-question-text{font-size:18px;font-weight:600;color:var(--text);margin-bottom:14px;line-height:1.35}
  .qs-question-img{max-width:100%;max-height:280px;border-radius:10px;margin-bottom:14px;background:#f3f4f6;object-fit:contain}

  .qs-options{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px}
  @media (max-width:560px){.qs-options{grid-template-columns:1fr}}
  .qs-opt{display:flex;align-items:center;gap:10px;border:2px solid var(--border);border-radius:10px;padding:14px 16px;cursor:pointer;background:#fff;transition:all .15s var(--ease);text-align:left;font-size:14px;font-weight:500;width:100%;font-family:inherit}
  .qs-opt:hover{border-color:var(--accent);background:var(--accent-50)}
  .qs-opt[disabled]{cursor:not-allowed;opacity:.85}
  .qs-opt.is-picked{border-color:var(--accent);background:var(--accent-50)}
  .qs-opt.is-correct{border-color:#10b981;background:#ecfdf5;color:#065f46}
  .qs-opt.is-wrong{border-color:#dc2626;background:#fef2f2;color:#991b1b}
  .qs-opt-letter{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:var(--surface3);color:var(--text);font-weight:800;font-size:14px;flex-shrink:0}
  .qs-opt.is-correct .qs-opt-letter{background:#10b981;color:#fff}
  .qs-opt.is-wrong .qs-opt-letter{background:#dc2626;color:#fff}
  .qs-opt.is-picked .qs-opt-letter{background:var(--accent);color:#fff}
  .qs-opt-img{max-height:80px;max-width:100%;object-fit:contain;border-radius:6px}

  .qs-timer{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.18);padding:6px 12px;border-radius:999px;font-weight:700;font-size:13px}
  .qs-timer.warn{background:#fef3c7;color:#92400e}
  .qs-timer.crit{background:#fee2e2;color:#991b1b;animation:pulseHard 1s infinite}
  @keyframes pulseHard{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}

  .qs-feedback-banner{padding:14px 18px;border-radius:12px;font-weight:700;font-size:15px;margin-top:16px;display:flex;align-items:center;gap:10px;animation:popIn .35s var(--ease)}
  @keyframes popIn{from{opacity:0;transform:scale(.9)}to{opacity:1;transform:none}}
  .qs-feedback-banner.right{background:#dcfce7;color:#065f46;border:2px solid #10b981}
  .qs-feedback-banner.wrong{background:#fee2e2;color:#991b1b;border:2px solid #dc2626}

  .qs-tally{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:14px}
  .qs-tally-cell{padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);text-align:center;font-size:12px;font-weight:600}
  .qs-tally-cell .num{display:block;font-size:20px;font-weight:800;color:var(--text)}
  .qs-tally-cell.is-correct{background:#dcfce7;border-color:#86efac;color:#065f46}

  .qs-grid-screens{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-top:14px}
  .qs-screen{padding:10px;border:1px solid var(--border);border-radius:10px;background:#fff;text-align:center;font-size:12px;transition:all .2s var(--ease)}
  .qs-screen.right{background:#dcfce7;border-color:#10b981}
  .qs-screen.wrong{background:#fee2e2;border-color:#dc2626}
  .qs-screen.waiting{background:var(--surface3);border-color:var(--border);opacity:.7}
  .qs-screen .nm{font-weight:700;color:var(--text);margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .qs-screen .pk{font-size:11px;color:var(--text2)}

  .qs-queue-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;background:#fff;font-size:13px}
  .qs-queue-item.shown{opacity:.55}
  .qs-queue-item.live{border-color:#dc2626;background:#fef2f2;font-weight:600}

  .qs-lobby{text-align:center;padding:48px 24px}
  .qs-lobby h3{font-size:18px;font-weight:600;margin-bottom:8px}
</style>
@endpush

@section('content')

{{-- ────────────────── HERO HEADER ────────────────── --}}
<div class="qs-hero">
  <div>
    <h1>{{ $session->name }}</h1>
    <div class="meta">
      <span>Coach: <strong>{{ optional($session->coach)->name ?? '—' }}</strong></span>
      <span>Level: <strong>{{ ucfirst($session->level) }}</strong></span>
      <span>{{ \Illuminate\Support\Carbon::parse($session->scheduled_at)->timezone('Asia/Kolkata')->format('D, d M Y · h:i A') }} IST</span>
      @if($session->week)<span>Week {{ $session->week }}</span>@endif
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    @if($session->status === 'live')
      <span class="qs-timer"><span class="live-dot"></span> LIVE</span>
    @else
      <span class="badge {{ $statusBadge }}" style="font-size:12px;padding:6px 12px">{{ $statusLabel }}</span>
    @endif
    <a class="btn btn-sm btn-outline" href="{{ route($indexRoute) }}" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff">Back</a>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════
     MANAGE MODE (admin / staff)
════════════════════════════════════════════════════════════════════════ --}}
@if($mode === 'manage')

  @if($session->isEnded() && !empty($leaderboard))
    <div class="card mb-4">
      <div class="card-title">Leaderboard</div>
      @include('quiz-sessions._leaderboard', ['leaderboard' => $leaderboard, 'session' => $session])
    </div>
  @endif

  <div class="qs-grid">
    <div>
      <div class="card">
        <div class="card-title">Session Details</div>
        <div class="text-sm text-muted">Created by</div>
        <div class="font-semibold mb-3">{{ optional($session->creator)->name ?? '—' }}</div>

        <div class="text-sm text-muted">Status</div>
        <div class="mb-3"><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></div>

        @if($session->started_at)
          <div class="text-sm text-muted">Started</div>
          <div class="mb-3 text-sm">{{ \Illuminate\Support\Carbon::parse($session->started_at)->timezone('Asia/Kolkata')->format('d M Y · h:i A') }} IST</div>
        @endif
        @if($session->ended_at)
          <div class="text-sm text-muted">Ended</div>
          <div class="mb-3 text-sm">{{ \Illuminate\Support\Carbon::parse($session->ended_at)->timezone('Asia/Kolkata')->format('d M Y · h:i A') }} IST</div>
        @endif

        @if($editRoute && !$session->isLive() && !$session->isEnded())
          <div class="flex gap-2 mt-3">
            <a class="btn btn-sm btn-outline" href="{{ route($editRoute, $session) }}">Edit</a>
            <form method="POST" action="{{ route($cancelRoute, $session) }}" onsubmit="return confirm('Cancel session?')" style="display:inline">
              @csrf
              <button class="btn btn-sm btn-warning" type="submit">Cancel</button>
            </form>
          </div>
        @endif
      </div>
    </div>

    <div>
      <div class="card">
        <div class="card-title">Joined Students <span class="badge badge-active">{{ $session->attendees->count() }}</span></div>
        @if($session->attendees->isEmpty())
          <div class="empty" style="padding:20px">No students have joined yet.</div>
        @else
          <div style="display:flex;flex-direction:column;gap:6px">
            @foreach($session->attendees as $a)
              <div class="flex items-center" style="gap:10px;padding:6px 0">
                <div class="av av-sm" style="background:{{ optional($a->user)->avatar_color ?? '#94a3b8' }}">{{ optional($a->user)->initials ?? '?' }}</div>
                <div class="flex-1">
                  <div class="font-semibold text-sm">{{ optional($a->user)->name ?? '—' }}</div>
                  <div class="text-xs text-muted">Joined {{ \Illuminate\Support\Carbon::parse($a->joined_at)->timezone('Asia/Kolkata')->format('h:i A') }}</div>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </div>

{{-- ════════════════════════════════════════════════════════════════════════
     COACH MODE
════════════════════════════════════════════════════════════════════════ --}}
@elseif($mode === 'coach')

  {{-- ── COACH: scheduled — start form (week is pre-defined by the system) ── --}}
  @if($session->isScheduled())
    <div class="card" style="max-width:640px">
      <div class="card-title">Start This Session</div>

      {{-- Pre-defined week display (read-only) --}}
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px">
        <div style="flex:1">
          <div style="font-size:12px;color:#4338ca;font-weight:600;margin-bottom:2px">
            Pre-defined by the system
          </div>
          @if($availableWeeks->isEmpty())
            <div style="font-size:16px;font-weight:700;color:#991b1b">
              No quizzes in the bank yet
            </div>
          @else
            <div style="font-size:18px;font-weight:800;color:#1e1b4b">
              Week {{ $suggestedWeek }}
            </div>
          @endif
        </div>
      </div>

      @if($session->attendees->isNotEmpty())
        <div class="alert alert-info">
          <strong>{{ $session->attendees->count() }}</strong> student(s) have already joined the lobby.
        </div>
      @else
        <div class="alert alert-warning">
          No students have joined yet. You can still start; they will auto-join when they open this session.
        </div>
      @endif

      <form method="POST" action="{{ route($startRoute, $session) }}">
        @csrf
        <div class="flex gap-2 mt-3">
          <button class="btn btn-primary btn-lg" type="submit" {{ $availableWeeks->isEmpty() ? 'disabled' : '' }}>
            Start Session
          </button>
          <a class="btn btn-outline" href="{{ route('coach.quiz-sessions.index') }}">Back to lobby</a>
        </div>
      </form>
    </div>

  {{-- ── COACH: live — broadcast control panel ── --}}
  @elseif($session->isLive())
    <div class="qs-grid">
      {{-- LEFT: current question + tally + queue --}}
      <div>
        <div class="qs-question-card" id="coachQuestionCard">
          <div id="coachNoQuiz" style="{{ $session->currentQuiz ? 'display:none' : '' }}">
            <div class="text-center" style="padding:40px 10px">
              <div class="font-bold mb-2">Pick a quiz to broadcast</div>
            </div>
          </div>

          <div id="coachQuizBody" style="{{ $session->currentQuiz ? '' : 'display:none' }}">
            <div class="flex items-center justify-between mb-3" style="gap:10px;flex-wrap:wrap">
              <div class="text-sm text-muted" id="coachQuizMeta">
                <span class="badge badge-pending">Week {{ $session->week ?? '—' }}</span>
                <span class="badge badge-active" id="coachQuizCategory">—</span>
              </div>
              <span class="qs-timer" id="coachTimer"><span id="coachTimerSec">—</span>s</span>
            </div>

            <img id="coachQuizImage" class="qs-question-img" style="display:none" alt="">
            <div class="qs-question-text" id="coachQuizText">—</div>

            <div class="qs-options" id="coachOptions">
              {{-- Filled by JS --}}
            </div>

            <div class="qs-tally" id="coachTally">
              <div class="qs-tally-cell"><span class="num" id="tally-0">0</span>A</div>
              <div class="qs-tally-cell"><span class="num" id="tally-1">0</span>B</div>
              <div class="qs-tally-cell"><span class="num" id="tally-2">0</span>C</div>
              <div class="qs-tally-cell"><span class="num" id="tally-3">0</span>D</div>
            </div>

            <div class="flex gap-2 mt-4" style="flex-wrap:wrap">
              <form method="POST" action="{{ route($endCurrentRoute, $session) }}" style="display:inline">
                @csrf
                <button class="btn btn-warning" type="submit">End Current Quiz</button>
              </form>
              <form method="POST" action="{{ route($broadcastRoute, $session) }}" style="display:inline">
                @csrf
                <button class="btn btn-primary" type="submit">Next Quiz</button>
              </form>
            </div>
          </div>
        </div>

        {{-- Queue list --}}
        <div class="card mt-4">
          <div class="card-title">Quiz Queue (Week {{ $session->week }})</div>
          @if($session->quizQueue->isEmpty())
            <div class="empty" style="padding:20px">Queue is empty.</div>
          @else
            @foreach($session->quizQueue as $q)
              @php
                $isLive = $session->current_quiz_id === $q->quiz_id;
                $shown = $q->shown && !$isLive;
              @endphp
              <div class="qs-queue-item {{ $shown ? 'shown' : '' }} {{ $isLive ? 'live' : '' }}">
                <div style="font-weight:700;width:24px">{{ $loop->iteration }}.</div>
                <div class="flex-1" style="min-width:0">
                  <div class="truncate" style="font-size:13px">
                    {{ optional($q->quiz)->question_text ?: 'Picture question' }}
                  </div>
                  <div class="text-xs text-muted">{{ optional($q->quiz)->category }} · {{ optional($q->quiz)->time_limit_seconds }}s</div>
                </div>
                @if($isLive)
                  <span class="badge badge-live">Live</span>
                @elseif($shown)
                  <span class="badge badge-done">Shown</span>
                @else
                  <form method="POST" action="{{ route($broadcastRoute, $session) }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="queue_id" value="{{ $q->id }}">
                    <button class="btn btn-xs btn-primary" type="submit">Show this</button>
                  </form>
                @endif
              </div>
            @endforeach
          @endif
        </div>

        <form method="POST" action="{{ route($endRoute, $session) }}" onsubmit="return confirm('End this session for everyone?')" class="mt-3">
          @csrf
          <button class="btn btn-danger" type="submit">End Session</button>
        </form>
      </div>

      {{-- RIGHT: live student grid (multi-screen) --}}
      <div>
        <div class="card">
          <div class="card-title">
            Live Student Screens
            <span class="badge badge-active" id="coachJoinedCount">{{ $session->attendees->count() }} joined</span>
          </div>
          <div class="text-xs text-muted mb-2" id="coachAnswerSummary">—</div>
          <div class="qs-grid-screens" id="coachStudentGrid">
            {{-- Filled by JS poller --}}
          </div>
          @if($session->attendees->isEmpty())
            <div class="empty" style="padding:20px">No students yet.</div>
          @endif
        </div>
      </div>
    </div>

  {{-- ── COACH: ended — leaderboard ── --}}
  @elseif($session->isEnded())
    <div class="card mb-4">
      <div class="card-title">Leaderboard</div>
      @include('quiz-sessions._leaderboard', ['leaderboard' => $leaderboard ?? [], 'session' => $session])
    </div>
  @endif

{{-- ════════════════════════════════════════════════════════════════════════
     STUDENT MODE
════════════════════════════════════════════════════════════════════════ --}}
@elseif($mode === 'student')

  {{-- ── STUDENT: not joined + open ── --}}
  @if(!$hasJoined && !$session->isEnded() && !$session->isCancelled())
    <div class="card">
      <div class="qs-lobby">
        <h3>Ready to join this quiz?</h3>
        <p class="text-muted mb-4">
          Coach <strong>{{ optional($session->coach)->name }}</strong> will broadcast questions live.
          Tap an option as soon as you see it — fast and correct wins!
        </p>
        <form method="POST" action="{{ route($joinRoute, $session) }}">
          @csrf
          <button class="btn btn-primary btn-lg" type="submit">Join This Quiz</button>
        </form>
      </div>
    </div>

  {{-- ── STUDENT: joined, scheduled (waiting for coach) ── --}}
  @elseif($hasJoined && $session->isScheduled())
    <div class="card">
      <div class="qs-lobby">
        <h3>You're in! Waiting for the coach…</h3>
        <p class="text-muted">
          Quiz starts at
          <strong>{{ \Illuminate\Support\Carbon::parse($session->scheduled_at)->timezone('Asia/Kolkata')->format('h:i A') }}</strong>.
          This page will switch to live as soon as the coach starts.
        </p>
        <div class="badge badge-active mt-3">Joined</div>
      </div>
    </div>
    {{-- Auto-refresh poller — when status flips to live, reload the page --}}
    @push('scripts')
    <script>
      (function () {
        var URL = "{{ route($studentPollRoute, $session) }}";
        async function tick() {
          try {
            var r = await fetch(URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) return;
            var data = await r.json();
            if (data.status === 'live' || data.status === 'ended') {
              window.location.reload();
            }
          } catch (e) {}
        }
        setInterval(tick, 3000);
      })();
    </script>
    @endpush

  {{-- ── STUDENT: joined + live — answer screen ── --}}
  @elseif($hasJoined && $session->isLive())
    <div class="qs-question-card" id="studentCard">
      <div id="studentNoQuiz" style="{{ $session->currentQuiz ? 'display:none' : '' }}">
        <div class="qs-lobby">
          <h3>Your coach is preparing the next question…</h3>
          <p class="text-muted">Stay sharp — it could appear any second!</p>
        </div>
      </div>

      <div id="studentQuizBody" style="{{ $session->currentQuiz ? '' : 'display:none' }}">
        <div class="flex items-center justify-between mb-3" style="gap:10px;flex-wrap:wrap">
          <div>
            <span class="badge badge-pending">Week {{ $session->week ?? '—' }}</span>
            <span class="badge badge-active" id="studentCategory">—</span>
          </div>
          <span class="qs-timer" id="studentTimer"><span id="studentTimerSec">—</span>s</span>
        </div>

        <img id="studentQuizImage" class="qs-question-img" style="display:none" alt="">
        <div class="qs-question-text" id="studentQuizText">—</div>

        <div class="qs-options" id="studentOptions">
          {{-- Filled by JS poller --}}
        </div>

        <div id="studentFeedback" style="display:none"></div>
      </div>
    </div>

    @push('scripts')
    <script>
      (function () {
        var POLL_URL   = "{{ route($studentPollRoute, $session) }}";
        var ANSWER_URL = "{{ route($answerRoute, $session) }}";
        var CSRF       = "{{ csrf_token() }}";
        var POLL_MS    = 1500;

        var currentQuizId = null;
        var lockedPick    = null;   // index 0..3 once we've picked
        var answeredQuizIds = new Set(); // ids we've already submitted to

        var els = {
          noQuiz:    document.getElementById('studentNoQuiz'),
          body:      document.getElementById('studentQuizBody'),
          category:  document.getElementById('studentCategory'),
          timer:     document.getElementById('studentTimer'),
          timerSec:  document.getElementById('studentTimerSec'),
          image:     document.getElementById('studentQuizImage'),
          text:      document.getElementById('studentQuizText'),
          options:   document.getElementById('studentOptions'),
          feedback:  document.getElementById('studentFeedback'),
        };

        function letter(i){ return ['A','B','C','D'][i] || ''; }

        function renderQuestion(quiz) {
          els.noQuiz.style.display = 'none';
          els.body.style.display = '';

          // New question? reset state.
          if (currentQuizId !== quiz.id) {
            currentQuizId = quiz.id;
            lockedPick = (typeof quiz.my_pick === 'number') ? quiz.my_pick : null;
            els.feedback.style.display = 'none';
            els.feedback.innerHTML = '';
          }

          els.category.textContent = quiz.category || '—';
          els.text.textContent = quiz.question_text || '';

          if (quiz.image_url) {
            els.image.src = quiz.image_url;
            els.image.style.display = '';
          } else {
            els.image.style.display = 'none';
          }

          // Options
          var html = '';
          (quiz.options || []).forEach(function (opt, i) {
            var pickedClass = '';
            if (lockedPick !== null) {
              var isCorrect = quiz.correct_index === i;
              if (i === lockedPick) {
                pickedClass = isCorrect ? 'is-correct' : 'is-wrong';
              } else if (isCorrect) {
                pickedClass = 'is-correct';
              }
            }
            var inner = (opt.kind === 'image' && opt.image)
              ? '<img class="qs-opt-img" src="' + opt.image + '">'
              : '<span>' + escapeHtml(opt.text || '') + '</span>';
            html += '<button type="button" class="qs-opt ' + pickedClass + '" data-idx="' + i + '"'
                  + (lockedPick !== null ? ' disabled' : '') + '>'
                  +   '<span class="qs-opt-letter">' + letter(i) + '</span>'
                  +   inner
                  + '</button>';
          });
          els.options.innerHTML = html;

          // Wire clicks (only if not yet answered)
          if (lockedPick === null) {
            els.options.querySelectorAll('.qs-opt').forEach(function (btn) {
              btn.addEventListener('click', function () { onPick(parseInt(btn.dataset.idx, 10)); });
            });
          } else {
            // Show feedback banner if not yet shown
            renderFeedback(quiz);
          }

          // Timer
          var rem = (typeof quiz.remaining === 'number') ? quiz.remaining : null;
          if (rem === null) {
            els.timerSec.textContent = '—';
            els.timer.classList.remove('warn','crit');
          } else {
            els.timerSec.textContent = rem;
            els.timer.classList.toggle('warn', rem <= 15 && rem > 5);
            els.timer.classList.toggle('crit', rem <= 5);
          }
        }

        function renderFeedback(quiz) {
          if (lockedPick === null) return;
          var isCorrect = (quiz.correct_index === lockedPick);
          els.feedback.style.display = '';
          els.feedback.innerHTML = '<div class="qs-feedback-banner ' + (isCorrect ? 'right' : 'wrong') + '">'
            + (isCorrect ? 'Correct! Well done.' : 'Not quite. The correct answer was ' + letter(quiz.correct_index) + '.')
            + '</div>';
        }

        async function onPick(idx) {
          if (lockedPick !== null) return;
          if (currentQuizId === null) return;
          // Optimistically lock
          lockedPick = idx;
          // Disable all
          els.options.querySelectorAll('.qs-opt').forEach(function (b) { b.disabled = true; });
          els.options.querySelectorAll('.qs-opt').forEach(function (b) {
            if (parseInt(b.dataset.idx,10) === idx) b.classList.add('is-picked');
          });

          try {
            var r = await fetch(ANSWER_URL, {
              method: 'POST',
              headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' },
              credentials: 'same-origin',
              body: JSON.stringify({ quiz_id: currentQuizId, picked_index: idx })
            });
            var data = await r.json();
            if (data && data.ok) {
              // Paint feedback now
              els.options.querySelectorAll('.qs-opt').forEach(function (b) {
                var bi = parseInt(b.dataset.idx,10);
                b.classList.remove('is-picked');
                if (bi === data.correct_index) b.classList.add('is-correct');
                else if (bi === idx)           b.classList.add('is-wrong');
              });
              els.feedback.style.display = '';
              els.feedback.innerHTML = '<div class="qs-feedback-banner ' + (data.is_correct ? 'right' : 'wrong') + '">'
                + (data.is_correct ? 'Correct! Well done.' : 'Not quite. The correct answer was ' + letter(data.correct_index) + '.')
                + '</div>';
              answeredQuizIds.add(currentQuizId);
            } else if (data && data.reason === 'time_up') {
              els.feedback.style.display = '';
              els.feedback.innerHTML = '<div class="qs-feedback-banner wrong">⏰ Time up — coach moved on.</div>';
            }
          } catch (e) {
            // Network blip — let the poller heal state
          }
        }

        function renderNoQuiz() {
          els.noQuiz.style.display = '';
          els.body.style.display = 'none';
          currentQuizId = null;
          lockedPick = null;
        }

        async function tick() {
          try {
            var r = await fetch(POLL_URL, { headers:{'Accept':'application/json'}, credentials:'same-origin' });
            if (!r.ok) return;
            var data = await r.json();

            if (data.status === 'ended' || data.status === 'cancelled') {
              window.location.reload();
              return;
            }

            if (!data.quiz) {
              renderNoQuiz();
              return;
            }
            // Server is authoritative for my_pick (in case of reload mid-session).
            if (data.quiz.id !== currentQuizId && typeof data.quiz.my_pick === 'number') {
              lockedPick = data.quiz.my_pick;
            } else if (data.quiz.id !== currentQuizId) {
              lockedPick = null;
            }
            renderQuestion(data.quiz);
          } catch (e) {}
        }

        function escapeHtml(s) {
          return String(s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
          });
        }

        tick();
        setInterval(tick, POLL_MS);
      })();
    </script>
    @endpush

  {{-- ── STUDENT: ended — show leaderboard if any --}}
  @elseif($session->isEnded())
    <div class="card mb-4">
      <div class="card-title">Results</div>
      @php
        $sb = isset($leaderboard) && !empty($leaderboard)
              ? $leaderboard
              : \App\Http\Controllers\Quiz\AdminQuizSessionController::buildLeaderboard($session);
      @endphp
      @include('quiz-sessions._leaderboard', ['leaderboard' => $sb, 'session' => $session, 'highlightUserId' => $u->id])
    </div>

  {{-- ── STUDENT: cancelled --}}
  @else
    <div class="card empty">This session was cancelled.</div>
  @endif

@endif

{{-- ════════════════════════════════════════════════════════════════════════
     COACH LIVE POLLER (defined here so it's only shipped when needed)
════════════════════════════════════════════════════════════════════════ --}}
@if($mode === 'coach' && $session->isLive())
@push('scripts')
<script>
(function () {
  var POLL_URL = "{{ route($coachPollRoute, $session) }}";
  var POLL_MS  = 1500;
  var lastQuizId = null;

  var els = {
    noQuiz:        document.getElementById('coachNoQuiz'),
    body:          document.getElementById('coachQuizBody'),
    category:      document.getElementById('coachQuizCategory'),
    timerSec:      document.getElementById('coachTimerSec'),
    timer:         document.getElementById('coachTimer'),
    image:         document.getElementById('coachQuizImage'),
    text:          document.getElementById('coachQuizText'),
    options:       document.getElementById('coachOptions'),
    grid:          document.getElementById('coachStudentGrid'),
    joinedCount:   document.getElementById('coachJoinedCount'),
    summary:       document.getElementById('coachAnswerSummary'),
  };

  function letter(i){ return ['A','B','C','D'][i] || '?'; }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }

  function renderQuiz(quiz) {
    els.noQuiz.style.display = 'none';
    els.body.style.display = '';
    els.category.textContent = quiz.category || '—';
    els.text.textContent = quiz.question_text || '';

    if (quiz.image_url) {
      els.image.src = quiz.image_url;
      els.image.style.display = '';
    } else {
      els.image.style.display = 'none';
    }

    // Options panel — always show correct option highlighted for coach
    var html = '';
    (quiz.options || []).forEach(function (opt, i) {
      var cls = (i === quiz.correct_index) ? 'is-correct' : '';
      var inner = (opt.kind === 'image' && opt.image)
        ? '<img class="qs-opt-img" src="' + opt.image + '">'
        : '<span>' + escapeHtml(opt.text || '') + '</span>';
      html += '<div class="qs-opt ' + cls + '" style="cursor:default">'
            +   '<span class="qs-opt-letter">' + letter(i) + '</span>' + inner
            + '</div>';
    });
    els.options.innerHTML = html;

    // Tally
    (quiz.tally || [0,0,0,0]).forEach(function (n, i) {
      var cell = document.getElementById('tally-' + i);
      if (cell) cell.textContent = n;
    });

    // Timer
    var rem = (typeof quiz.remaining === 'number') ? quiz.remaining : null;
    if (rem === null) {
      els.timerSec.textContent = '—';
      els.timer.classList.remove('warn','crit');
    } else {
      els.timerSec.textContent = rem;
      els.timer.classList.toggle('warn', rem <= 15 && rem > 5);
      els.timer.classList.toggle('crit', rem <= 5);
    }
  }

  function renderNoQuiz() {
    els.noQuiz.style.display = '';
    els.body.style.display = 'none';
  }

  function renderGrid(payload) {
    var quiz = payload.quiz;
    var picks = (quiz && quiz.picks) ? quiz.picks : [];
    // If no quiz live, fall back to attendees-only.
    var people = picks.length ? picks : (payload.attendees || []).map(function (a) {
      return { user_id:a.id, name:a.name, picked:null, is_correct:null };
    });

    els.joinedCount.textContent = (payload.attendees || []).length + ' joined';

    var answered = 0, correct = 0;
    var html = people.map(function (p) {
      var cls = 'waiting', label = 'Waiting…';
      if (p.picked !== null && typeof p.picked !== 'undefined') {
        answered++;
        if (p.is_correct) correct++;
        cls = p.is_correct ? 'right' : 'wrong';
        label = (p.is_correct ? 'Correct: ' : 'Wrong: ') + letter(p.picked);
      }
      return '<div class="qs-screen ' + cls + '">'
           +   '<div class="nm">' + escapeHtml(p.name || '—') + '</div>'
           +   '<div class="pk">' + label + '</div>'
           + '</div>';
    }).join('');
    els.grid.innerHTML = html;

    if (quiz) {
      els.summary.textContent = answered + ' answered · ' + correct + ' correct · ' + (people.length - answered) + ' waiting';
    } else {
      els.summary.textContent = '—';
    }
  }

  async function tick() {
    try {
      var r = await fetch(POLL_URL, { headers:{'Accept':'application/json'}, credentials:'same-origin' });
      if (!r.ok) return;
      var data = await r.json();

      if (data.status === 'ended' || data.status === 'cancelled') {
        window.location.reload();
        return;
      }
      if (data.quiz) {
        renderQuiz(data.quiz);
      } else {
        renderNoQuiz();
      }
      renderGrid(data);
      lastQuizId = data.quiz ? data.quiz.id : null;
    } catch (e) {}
  }

  tick();
  setInterval(tick, POLL_MS);
})();
</script>
@endpush
@endif

@endsection

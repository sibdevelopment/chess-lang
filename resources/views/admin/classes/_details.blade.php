@php
  $isCancelled = $class->status === 'cancelled';
  $scheduledAt = $class->scheduledStart()?->timezone($class->displayTimezone());
  $startedAt = $class->actualStart()?->timezone($class->displayTimezone());
  $endedAt = $class->actualEnd()?->timezone($class->displayTimezone());
  $plannedDuration = (int) ($class->duration_minutes ?: \App\Models\Group::DEFAULT_DURATION_MINUTES);
  $cancelledAt = $class->cancelled_at?->timezone($class->displayTimezone());
  $classCount = $class->group_id
    ? \App\Models\LiveClass::query()
        ->where('group_id', $class->group_id)
        ->whereIn('status', ['ended', 'completed', 'cancelled'])
        ->where('id', '<=', $class->id)
        ->count()
    : null;
  $puzzleAudit = $puzzleAudit ?? collect();
@endphp

<div class="classroom-details">
  <h3 class="classroom-detail-heading">{{ $class->name }}</h3>

  <div class="classroom-details-top">
    <div class="classroom-detail-box">
      <div class="classroom-meta-label">Coach</div>
      <div class="classroom-meta-value">{{ $class->coach?->name ?? '—' }}</div>
    </div>
    <div class="classroom-detail-box">
      <div class="classroom-meta-label">Batch</div>
      <div class="classroom-meta-value">{{ $class->group?->name ?? '—' }}</div>
    </div>
    <div class="classroom-detail-box">
      <div class="classroom-meta-label">Date</div>
      <div class="classroom-meta-value">{{ ($startedAt ?? $scheduledAt ?? $endedAt)?->format('D, d M Y') ?? '—' }}</div>
    </div>
    <div class="classroom-detail-box">
      <div class="classroom-meta-label">Time</div>
      <div class="classroom-meta-value">{{ ($startedAt ?? $scheduledAt ?? $endedAt)?->format('g:i A') ?? '—' }}</div>
    </div>
    @if($class->isCompleted())
      <div class="classroom-detail-box">
        <div class="classroom-meta-label">Class Count</div>
        <div class="classroom-meta-value">{{ $classCount ? 'Class '.$classCount : '—' }}</div>
      </div>
      <div class="classroom-detail-box">
        <div class="classroom-meta-label">Duration</div>
        <div class="classroom-meta-value">{{ $plannedDuration }} Minutes</div>
      </div>
    @endif
    <div class="classroom-detail-box">
      <div class="classroom-meta-label">Chapter / Lesson Taught</div>
      <div class="classroom-meta-value">{{ $class->chapter?->name ?? '—' }}</div>
    </div>
    <div class="classroom-detail-box">
      <div class="classroom-meta-label">Total Students</div>
      <div class="classroom-meta-value">{{ $class->group?->students_count ?? $class->group?->students?->count() ?? 0 }}</div>
    </div>
    @unless($isCancelled)
      <div class="classroom-detail-box">
        <div class="classroom-meta-label">Attended Students</div>
        <div class="classroom-meta-value">{{ $attendedStudents->count() }}</div>
      </div>
      <div class="classroom-detail-box">
        <div class="classroom-meta-label">Absent Students</div>
        <div class="classroom-meta-value">{{ $absentStudents->count() }}</div>
      </div>
    @else
      <div class="classroom-detail-box">
        <div class="classroom-meta-label">Cancelled By</div>
        <div class="classroom-meta-value">{{ $class->completion_type === 'cancelled' ? 'Admin / Staff / Coach / System' : 'System' }}</div>
      </div>
      <div class="classroom-detail-box">
        <div class="classroom-meta-label">Cancellation Date</div>
        <div class="classroom-meta-value">{{ $cancelledAt?->format('d M Y, g:i A') ?? '—' }}</div>
      </div>
      <div class="classroom-detail-box">
        <div class="classroom-meta-label">Cancellation Reason</div>
        <div class="classroom-meta-value">Coach did not start the class within the allowed window.</div>
      </div>
    @endunless
  </div>

  <div class="ui-tabs classroom-detail-tabs">
    <button type="button" class="ui-tab classroom-detail-tab is-active" data-detail-tab="students">Students</button>
    @unless($isCancelled)
      <button type="button" class="ui-tab classroom-detail-tab" data-detail-tab="leaderboard">Leaderboard</button>
    @endunless
    @if($class->isCompleted())
      <button type="button" class="ui-tab classroom-detail-tab" data-detail-tab="classroom-materials">
        Classroom Materials <span class="ui-tab__count">{{ $puzzleAudit->count() }}</span>
      </button>
    @endif
    <button type="button" class="ui-tab classroom-detail-tab" data-detail-tab="notes">Notes</button>
  </div>

  <section class="classroom-detail-panel is-active" data-detail-panel="students">
    @if($isCancelled)
      <div class="classroom-detail-section">
        <div class="classroom-detail-heading">Assigned Students</div>
        <x-ui.table compact>
          <x-slot:head>
            <tr><th>Name</th><th>Username</th><th>Status</th></tr>
          </x-slot:head>
          @forelse(($assignedStudents ?? collect()) as $student)
            <tr>
              <td>{{ $student->name }}</td>
              <td>{{ $student->username }}</td>
              <td>Assigned</td>
            </tr>
          @empty
            <tr><td colspan="3">No students assigned.</td></tr>
          @endforelse
        </x-ui.table>
      </div>
    @else
      <div class="classroom-detail-section">
        <div class="classroom-detail-heading">Attended Students</div>
        <x-ui.table compact>
          <x-slot:head>
            <tr><th>Name</th><th>Username</th><th>Status</th></tr>
          </x-slot:head>
          @forelse($attendedStudents as $student)
            <tr>
              <td>{{ $student->name }}</td>
              <td>{{ $student->username }}</td>
              <td>Attended</td>
            </tr>
          @empty
            <tr><td colspan="3">No students attended this class.</td></tr>
          @endforelse
        </x-ui.table>
      </div>

      <div class="classroom-detail-section">
        <div class="classroom-detail-heading">Absent Students</div>
        <x-ui.table compact>
          <x-slot:head>
            <tr><th>Name</th><th>Username</th><th>Status</th></tr>
          </x-slot:head>
          @forelse($absentStudents as $student)
            <tr>
              <td>{{ $student->name }}</td>
              <td>{{ $student->username }}</td>
              <td>Absent</td>
            </tr>
          @empty
            <tr><td colspan="3">No absent students.</td></tr>
          @endforelse
        </x-ui.table>
      </div>
    @endif
  </section>

  @unless($isCancelled)
    <section class="classroom-detail-panel" data-detail-panel="leaderboard">
      <x-ui.table compact>
        <x-slot:head>
          <tr><th>Rank</th><th>Student</th><th>Score</th><th>Accuracy</th><th>Time</th></tr>
        </x-slot:head>
        @forelse($leaderboard as $index => $row)
          <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $row['student']->name }}</td>
            <td>{{ $row['score'] }}</td>
            <td>{{ $row['accuracy'] }}</td>
            <td>{{ $row['time'] }}</td>
          </tr>
        @empty
          <tr><td colspan="5">No leaderboard activity yet.</td></tr>
        @endforelse
      </x-ui.table>
    </section>
  @endunless

  @if($class->isCompleted())
    <section class="classroom-detail-panel" data-detail-panel="classroom-materials">
      @forelse($puzzleAudit as $puzzle)
        <article class="classroom-puzzle-card">
          <header class="classroom-puzzle-card__header">
            <div>
              <div class="classroom-puzzle-kicker">Puzzle {{ $puzzle['number'] }}</div>
              <h4 class="classroom-puzzle-title">{{ $puzzle['prompt'] ?: 'Class puzzle '.$puzzle['number'] }}</h4>
              <div class="classroom-puzzle-meta">
                Shown {{ $puzzle['asked_at']?->timezone($class->displayTimezone())->format('d M Y, g:i A') ?? '—' }}
                @if($puzzle['ended_at'])
                  · Ended {{ $puzzle['ended_at']->timezone($class->displayTimezone())->format('g:i A') }}
                @endif
              </div>
            </div>
            <span class="badge badge-student">{{ $puzzle['max_score'] }} possible {{ Illuminate\Support\Str::plural('point', $puzzle['max_score']) }}</span>
          </header>

          <div class="classroom-puzzle-overview">
            <div class="classroom-puzzle-position">
              <div class="classroom-meta-label">Position shown to students</div>
              <div class="classroom-puzzle-board-host">
                <div
                  data-class-puzzle-board
                  data-fen="{{ $puzzle['position_fen'] }}"
                  data-arrows='@json($puzzle['arrows'])'
                  data-highlights='@json($puzzle['highlights'])'
                  aria-label="Puzzle {{ $puzzle['number'] }} starting position"
                ></div>
              </div>
              <details class="classroom-puzzle-fen">
                <summary>Position FEN</summary>
                <code>{{ $puzzle['position_fen'] }}</code>
              </details>
            </div>

            <div class="classroom-puzzle-answer">
              <div class="classroom-meta-label">Correct answer</div>
              @if($puzzle['answer_moves']->isNotEmpty())
                <ol class="classroom-answer-line">
                  @foreach($puzzle['answer_moves'] as $move)
                    <li><span>{{ $move }}</span></li>
                  @endforeach
                </ol>
              @else
                <div class="ui-meta-text">No answer line was saved for this puzzle.</div>
              @endif

              <div class="classroom-puzzle-rule">
                Each correct move gives 1 point. Wrong attempts do not reduce the score.
              </div>
            </div>
          </div>

          <div class="classroom-puzzle-students">
            <div class="classroom-detail-heading">Student scores</div>
            <x-ui.table compact>
              <x-slot:head>
                <tr>
                  <th>Student</th>
                  <th>Attendance</th>
                  <th>Score</th>
                  <th>Wrong</th>
                  <th>Accuracy</th>
                  <th>Status</th>
                  <th>Moves played</th>
                </tr>
              </x-slot:head>
              @forelse($puzzle['students'] as $result)
                <tr>
                  <td>
                    <div class="font-medium">{{ $result['student']->name }}</div>
                    <div class="ui-meta-text">{{ $result['student']->username }}</div>
                  </td>
                  <td>{{ $result['attended'] ? 'Attended' : 'Absent' }}</td>
                  <td><strong>{{ $result['score'] }}/{{ $result['max_score'] }}</strong></td>
                  <td>{{ $result['wrong_count'] }}</td>
                  <td>{{ $result['accuracy'] }}</td>
                  <td>
                    <span class="badge {{ $result['completed'] ? 'badge-completed' : ($result['status'] === 'Attempted' ? 'badge-in_progress' : 'badge-inactive') }}">
                      {{ $result['status'] }}
                    </span>
                  </td>
                  <td>
                    @if($result['moves_played']->isNotEmpty())
                      <div class="classroom-played-moves">
                        @foreach($result['moves_played'] as $move)
                          <code>{{ $move }}</code>
                        @endforeach
                      </div>
                    @else
                      <span class="ui-meta-text">No moves</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="7">No students were attached to this class.</td></tr>
              @endforelse
            </x-ui.table>
          </div>
        </article>
      @empty
        <x-ui.empty-state
          title="No class puzzles recorded"
          description="This class ended without a puzzle being broadcast to students."
        />
      @endforelse
    </section>
  @endif

  <section class="classroom-detail-panel" data-detail-panel="notes">
    <div class="classroom-detail-grid">
      <div class="classroom-note-box">
        <div class="classroom-meta-label">Coach Notes</div>
        <div class="classroom-meta-value">No coach notes were saved for this class.</div>
      </div>
      <div class="classroom-note-box">
        <div class="classroom-meta-label">Lesson Summary</div>
        <div class="classroom-meta-value">
          @if($isCancelled)
            This class was cancelled before it started.
          @elseif($class->status === 'live')
            This class is currently live and in progress.
          @elseif(in_array($class->status, ['ended', 'completed'], true))
            This class covered {{ $class->chapter?->name ?? 'the scheduled lesson' }}.
          @else
            This class is scheduled and ready for the assigned lesson.
          @endif
        </div>
      </div>
    </div>

    <div class="classroom-note-box">
      <div class="classroom-meta-label">What Was Taught In Today's Class</div>
      <div class="classroom-meta-value">
        {{ $class->chapter?->name ?? 'No chapter attached yet.' }}
      </div>
    </div>
  </section>
</div>

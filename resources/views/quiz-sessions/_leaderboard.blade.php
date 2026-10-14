@php
  $highlightUserId = $highlightUserId ?? null;
  $totalQuizzes = $session->quizQueue->count() ?: $session->responses->pluck('quiz_id')->unique()->count();
@endphp

@if(empty($leaderboard))
  <div class="empty" style="padding:24px">No responses recorded yet.</div>
@else
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th style="width:50px">#</th>
        <th>Student</th>
        <th style="text-align:center">Correct</th>
        <th style="text-align:center">Wrong</th>
        <th style="text-align:center">Answered</th>
        <th style="text-align:center">Score</th>
      </tr>
    </thead>
    <tbody>
      @foreach($leaderboard as $i => $row)
        @php
          $isMe = $highlightUserId && (int)$row['user']->id === (int)$highlightUserId;
        @endphp
        <tr style="{{ $isMe ? 'background:#fef3c7' : '' }}">
          <td><strong>{{ $i + 1 }}</strong></td>
          <td>
            <div class="flex items-center" style="gap:10px">
              <div class="av av-sm" style="background:{{ $row['user']->avatar_color ?? '#94a3b8' }}">
                {{ $row['user']->initials ?? '?' }}
              </div>
              <div>
                <div class="font-semibold">{{ $row['user']->name }}{{ $isMe ? ' (you)' : '' }}</div>
              </div>
            </div>
          </td>
          <td style="text-align:center"><strong style="color:#059669">{{ $row['correct'] }}</strong></td>
          <td style="text-align:center"><span style="color:#dc2626">{{ $row['wrong'] }}</span></td>
          <td style="text-align:center" class="text-muted">{{ $row['answered'] }}{{ $totalQuizzes ? ' / ' . $totalQuizzes : '' }}</td>
          <td style="text-align:center">
            <span class="badge badge-active" style="font-size:13px">{{ $row['correct'] }} pts</span>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Archer Student Performance Report</title>
  <style>
    @page { margin: 20px 32px 34px; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #172554; font-size: 10px; line-height: 1.4; }
    .header { background: #0b1f4f; border-radius: 13px; padding: 9px 14px; color: #fff; margin-bottom: 8px; }
    .header-table, .metric-table, .info-table, .score-table, .feedback-table { width: 100%; border-collapse: collapse; }
    .logo { width: 70px; height: 44px; object-fit: contain; }
    .brand { color: #fbbf24; font-size: 10px; font-weight: 600; }
    .title { color: #fff; font-size: 19px; font-weight: 700; margin: 1px 0; }
    .student-line { color: #bfdbfe; font-size: 10px; }
    .period-box { background: #fff7d6; color: #854d0e; border: 1px solid #fde68a; border-radius: 9px; padding: 5px 8px; text-align: center; }
    .period-label { font-size: 8px; font-weight: 600; }
    .period-value { font-size: 10px; font-weight: 700; margin-top: 1px; }
    .section { margin-bottom: 7px; page-break-inside: avoid; }
    .section-title { color: #fff; font-size: 11px; font-weight: 700; padding: 4px 9px; border-radius: 7px 7px 0 0; }
    .blue { background: #2563eb; }
    .purple { background: #7c3aed; }
    .orange { background: #ea580c; }
    .green { background: #059669; }
    .pink { background: #db2777; }
    .info-table { border: 1px solid #dbeafe; }
    .info-table td { padding: 4px 7px; border: 1px solid #dbeafe; vertical-align: top; }
    .info-label { width: 18%; background: #eff6ff; color: #1d4ed8; font-weight: 700; }
    .metric-table { border-spacing: 4px; border-collapse: separate; margin: 0 -4px; width: calc(100% + 8px); }
    .metric { border-radius: 8px; padding: 5px 5px; text-align: center; vertical-align: middle; border: 1px solid #dbeafe; background: #f8fbff; }
    .metric.yellow { background: #fffbeb; border-color: #fde68a; }
    .metric.mint { background: #ecfdf5; border-color: #a7f3d0; }
    .metric.lilac { background: #f5f3ff; border-color: #ddd6fe; }
    .metric.rose { background: #fff1f2; border-color: #fecdd3; }
    .metric-value { font-size: 14px; font-weight: 700; color: #172554; }
    .metric-name { font-size: 7px; color: #64748b; margin-top: 1px; }
    .score-summary { background: #fff7d6; border: 1px solid #fde68a; border-radius: 8px; padding: 5px 9px; margin-bottom: 5px; color: #854d0e; font-weight: 700; }
    .score-table td { border: 1px solid #e9d5ff; padding: 4px 7px; vertical-align: top; }
    .score-name { width: 29%; background: #faf5ff; color: #6d28d9; font-weight: 700; }
    .score-value { width: 13%; color: #172554; font-weight: 700; text-align: center; }
    .reference { display: block; color: #64748b; font-size: 7px; font-weight: 400; margin-top: 1px; }
    .feedback-table td { border: 1px solid #bbf7d0; padding: 4px 7px; vertical-align: top; }
    .feedback-label { width: 20%; background: #ecfdf5; color: #047857; font-weight: 700; }
    .encouragement { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 9px; padding: 5px 9px; color: #9a3412; font-weight: 700; text-align: center; margin-top: 5px; }
    .footer { position: fixed; left: 0; right: 0; bottom: -27px; border-top: 1px solid #dbeafe; padding-top: 6px; color: #64748b; font-size: 8px; }
    .footer-right { float: right; }
    .page-number:after { content: counter(page); }
  </style>
</head>
<body>
  @php
    $profile = $report['profile'];
    $period = $report['period'] ?? ['label' => $profile['report_period'] ?? 'All Time'];
    $classes = $report['classes'];
    $homework = $report['homework'];
    $puzzles = $report['puzzles'];
    $quizzes = $report['quizzes'];
    $tournaments = $report['tournaments'];
    $latestAssessment = $report['latest_assessment'];
    $assessmentLabels = $report['assessment_labels'];
    $assessmentReferences = $report['assessment_references'];
    $scoreFields = \App\Models\StudentPerformanceAssessment::scoreFields();
    $assessmentValues = [];
    $totalMarks = 0;
    $logoPath = public_path('logo.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;

    foreach ($scoreFields as $field) {
        $assessmentValues[$field] = (int) ($latestAssessment?->{$field} ?? 0);
        $totalMarks += $assessmentValues[$field];
    }

    $percentage = round($totalMarks, 1);
    $assessorRole = $latestAssessment?->assessor_role ?: $latestAssessment?->assessor?->role;
    $assessorRoleLabel = $assessorRole === 'admin' ? 'Super Admin' : ($assessorRole ? ucfirst($assessorRole) : '-');
    $clean = fn ($value) => str_replace('—', '-', (string) ($value ?? '-'));
    $country = trim(preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $clean($profile['country'])));
  @endphp

  <div class="footer">
    Archer Chess Academy - Learn. Think. Win.
    <span class="footer-right">Page <span class="page-number"></span></span>
  </div>

  <div class="header">
    <table class="header-table">
      <tr>
        <td style="width:80px;vertical-align:middle;">
          @if($logoData)<img src="{{ $logoData }}" class="logo" alt="Archer Chess Academy">@endif
        </td>
        <td style="vertical-align:middle;">
          <div class="brand">Archer Chess Academy</div>
          <div class="title">Student Performance Report</div>
          <div class="student-line">{{ $student->name }} ({{ $student->username }})</div>
        </td>
        <td style="width:165px;vertical-align:middle;">
          <div class="period-box">
            <div class="period-label">Report Period</div>
            <div class="period-value">{{ $clean($period['label']) }}</div>
          </div>
        </td>
      </tr>
    </table>
  </div>

  <div class="section">
    <div class="section-title blue">Student Snapshot</div>
    <table class="info-table">
      <tr><td class="info-label">Student</td><td>{{ $clean($profile['name']) }}</td><td class="info-label">Username</td><td>{{ $clean($profile['username']) }}</td></tr>
      <tr><td class="info-label">Batch</td><td>{{ $clean($profile['current_batch']) }}</td><td class="info-label">Coach</td><td>{{ $clean($profile['coach']) }}</td></tr>
      <tr><td class="info-label">Level</td><td>{{ $clean($profile['current_level']) }}</td><td class="info-label">Country</td><td>{{ $country ?: '-' }}</td></tr>
      <tr><td class="info-label">Status</td><td>{{ $clean($profile['status']) }}</td><td class="info-label">Last Activity</td><td>{{ $clean($profile['last_activity']) }}</td></tr>
    </table>
  </div>

  <div class="section">
    <div class="section-title green">Learning Activity</div>
    <table class="metric-table"><tr>
      <td class="metric mint"><div class="metric-value">{{ $classes['total_classes_scheduled'] }}</div><div class="metric-name">Classes</div></td>
      <td class="metric mint"><div class="metric-value">{{ $classes['classes_attended'] }}</div><div class="metric-name">Attended</div></td>
      <td class="metric rose"><div class="metric-value">{{ $classes['classes_missed'] }}</div><div class="metric-name">Missed</div></td>
      <td class="metric yellow"><div class="metric-value">{{ number_format($classes['attendance_percent'], 1) }}%</div><div class="metric-name">Attendance</div></td>
      <td class="metric lilac"><div class="metric-value">{{ number_format($classes['total_learning_hours'], 1) }}</div><div class="metric-name">Learning Hours</div></td>
    </tr></table>
  </div>

  <div class="section">
    <div class="section-title orange">Practice & Challenges</div>
    <table class="metric-table"><tr>
      <td class="metric yellow"><div class="metric-value">{{ $homework['homework_completed'] }}/{{ $homework['homework_assigned'] }}</div><div class="metric-name">Homework Done</div></td>
      <td class="metric mint"><div class="metric-value">{{ number_format($homework['average_accuracy_percent'], 1) }}%</div><div class="metric-name">Homework Accuracy</div></td>
      <td class="metric lilac"><div class="metric-value">{{ $puzzles['correct'] }}/{{ $puzzles['puzzles_attempted'] }}</div><div class="metric-name">Puzzles Correct</div></td>
      <td class="metric rose"><div class="metric-value">{{ number_format($puzzles['accuracy_percent'], 1) }}%</div><div class="metric-name">Puzzle Accuracy</div></td>
      <td class="metric mint"><div class="metric-value">{{ number_format($quizzes['average_score'], 1) }}%</div><div class="metric-name">Quiz Average</div></td>
    </tr></table>
  </div>

  <div class="section">
    <div class="section-title pink">Tournament Journey</div>
    <table class="metric-table"><tr>
      <td class="metric lilac"><div class="metric-value">{{ $tournaments['tournament_played'] }}</div><div class="metric-name">Played</div></td>
      <td class="metric mint"><div class="metric-value">{{ $tournaments['wins'] }}</div><div class="metric-name">Wins</div></td>
      <td class="metric yellow"><div class="metric-value">{{ $tournaments['draws'] }}</div><div class="metric-name">Draws</div></td>
      <td class="metric rose"><div class="metric-value">{{ $tournaments['losses'] }}</div><div class="metric-name">Losses</div></td>
      <td class="metric mint"><div class="metric-value">{{ number_format($tournaments['tournament_points'], 1) }}</div><div class="metric-name">Points</div></td>
      <td class="metric yellow"><div class="metric-value">{{ $clean($tournaments['best_rank']) }}</div><div class="metric-name">Best Rank</div></td>
    </tr></table>
  </div>

  <div class="section">
    <div class="section-title purple">Chess Skills Assessment</div>
    <div class="score-summary">Total Score: {{ $totalMarks }}/100 &nbsp; | &nbsp; Achievement: {{ number_format($percentage, 1) }}%</div>
    <table class="score-table">
      @foreach(array_chunk($scoreFields, 2) as $pair)
        <tr>
          @foreach($pair as $field)
            <td class="score-name">
              {{ $assessmentLabels[$field] }}
              @if(isset($assessmentReferences[$field]))
                <span class="reference">{{ $assessmentReferences[$field]['label'] }}: {{ $clean($assessmentReferences[$field]['value']) }}</span>
              @endif
            </td>
            <td class="score-value">{{ $assessmentValues[$field] }}/10</td>
          @endforeach
          @if(count($pair) === 1)<td class="score-name"></td><td class="score-value"></td>@endif
        </tr>
      @endforeach
    </table>
  </div>

  <div class="section">
    <div class="section-title green">Coach Feedback & Next Steps</div>
    <table class="feedback-table">
      <tr><td class="feedback-label">Assessed By</td><td>{{ $clean($latestAssessment?->assessor?->name) }} ({{ $assessorRoleLabel }})</td><td class="feedback-label">Saved On</td><td>{{ $latestAssessment?->assessed_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '-' }}</td></tr>
      <tr><td class="feedback-label">Strengths</td><td colspan="3">{{ $clean($latestAssessment?->strengths) }}</td></tr>
      <tr><td class="feedback-label">Areas to Improve</td><td colspan="3">{{ $clean($latestAssessment?->areas_to_improve) }}</td></tr>
      <tr><td class="feedback-label">Coach Remarks</td><td colspan="3">{{ $clean($latestAssessment?->coach_remarks) }}</td></tr>
      <tr><td class="feedback-label">Next Focus</td><td colspan="3">{{ $clean($latestAssessment?->next_focus) }}</td></tr>
    </table>
    <div class="encouragement">Every move is a chance to learn. Keep thinking, keep practicing, and keep shining!</div>
  </div>
</body>
</html>

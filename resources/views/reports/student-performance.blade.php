@extends('layouts.app')
@section('page-title', 'Student Performance Report')
@section('sidebar-nav')
  @include(
    auth()->user()?->isCoach()
      ? 'coach._sidebar'
      : (auth()->user()?->isStaff()
          ? 'staff._sidebar'
          : (auth()->user()?->isStudent() ? 'student._sidebar' : 'admin._sidebar'))
  )
@endsection

@php
  $profile = $report['profile'];
  $classes = $report['classes'];
  $homework = $report['homework'];
  $puzzles = $report['puzzles'];
  $quizzes = $report['quizzes'];
  $tournaments = $report['tournaments'];
  $latestAssessment = $report['latest_assessment'];
  $assessmentLabels = $report['assessment_labels'];
  $assessmentReferences = $report['assessment_references'];
  $period = $report['period'] ?? [
    'start' => null,
    'end' => null,
    'label' => $profile['report_period'] ?? 'All Time',
  ];
  $isSavedReport = $isSavedReport ?? false;

  $assessmentValues = [];
  $totalMarks = 0;
  foreach (\App\Models\StudentPerformanceAssessment::scoreFields() as $field) {
      $assessmentValues[$field] = (int) old($field, $latestAssessment?->{$field} ?? 0);
      $totalMarks += $assessmentValues[$field];
  }
  $percentage = round(($totalMarks / 100) * 100, 1);
@endphp

@section('content')
<div class="ui-page-stack">
  <x-ui.page-header
    title="{{ $isSavedReport ? 'Saved Student Report' : 'Create Student Report' }}"
    subtitle="{{ $student->name }} — {{ $student->username }} · Period: {{ $period['label'] }}{{ $isSavedReport && $latestAssessment?->assessed_at ? ' · Published '.$latestAssessment->assessed_at->timezone(config('app.timezone'))->format('d M Y, h:i A') : '' }}"
  >
    <x-slot:actions>
      <div class="flex flex-wrap gap-3">
        <x-ui.button type="button" variant="secondary" onclick="window.history.back()">Back</x-ui.button>
        <x-ui.button href="{{ $pdfRoute }}">Download PDF</x-ui.button>
      </div>
    </x-slot:actions>
  </x-ui.page-header>

  @if($canEditAssessment)
    <form method="GET" action="{{ route($routePrefix.'.reports.students.show', $student) }}" class="ui-card ui-report-period-form">
      <label class="ui-field">
        <span class="ui-label">Report Period From</span>
        <input type="date" name="date_from" value="{{ $dateFrom }}" required class="ui-input">
      </label>
      <label class="ui-field">
        <span class="ui-label">Report Period To</span>
        <input type="date" name="date_to" value="{{ $dateTo }}" required class="ui-input">
      </label>
      <x-ui.button type="submit">Calculate Period</x-ui.button>
      <p class="ui-meta-text ui-report-period-note">Attendance, homework, puzzles, quizzes, tournaments, and activity below are calculated only for this period.</p>
    </form>
  @endif

  <div class="ui-card ui-report-detail grid gap-8">
    <section class="grid gap-4 border-b border-slate-200 pb-8">
      <h2 class="text-lg font-semibold text-slate-900">Student Information</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student Name</div>
          <div class="mt-1 text-sm font-semibold text-slate-900">{{ $profile['name'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Username</div>
          <div class="mt-1 text-sm text-slate-700">{{ $profile['username'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Country</div>
          <div class="mt-1 text-sm text-slate-700">{{ $profile['country'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current Batch</div>
          <div class="mt-1 text-sm text-slate-700">{{ $profile['current_batch'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Coach</div>
          <div class="mt-1 text-sm text-slate-700">{{ $profile['coach'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Current Level</div>
          <div class="mt-1 text-sm text-slate-700">{{ $profile['current_level'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</div>
          <div class="mt-1 text-sm text-slate-700">{{ $profile['status'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Report Date</div>
          <div class="mt-1 text-sm text-slate-700">{{ $profile['report_date'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Report Period</div>
          <div class="mt-1 text-sm font-semibold text-blue-700">{{ $period['label'] }}</div>
        </div>
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Activity</div>
          <div class="mt-1 text-sm text-slate-700">{{ $profile['last_activity'] }}</div>
        </div>
      </div>
    </section>

    <section class="ui-report-score-section border-b border-slate-200 pb-8">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Overall Assessment</h2>
        <p class="ui-meta-text">Combined chess skill assessment for {{ $period['label'] }}.</p>
      </div>
      <div class="ui-report-score" aria-label="Overall assessment {{ number_format($percentage, 1) }} percent">
        <svg viewBox="0 0 42 42" aria-hidden="true">
          <circle class="ui-report-score__track" cx="21" cy="21" r="15.9" pathLength="100" />
          <circle class="ui-report-score__value" cx="21" cy="21" r="15.9" pathLength="100" stroke-dasharray="{{ min(100, max(0, $percentage)) }} 100" />
        </svg>
        <div class="ui-report-score__label"><strong>{{ number_format($percentage, 1) }}%</strong><span>{{ $totalMarks }} / 100</span></div>
      </div>
    </section>

    <section class="grid gap-4 border-b border-slate-200 pb-8">
      <h2 class="text-lg font-semibold text-slate-900">Class Performance</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Classes</div><div class="mt-1 text-2xl font-semibold text-slate-900">{{ $classes['total_classes_scheduled'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Attended</div><div class="mt-1 text-2xl font-semibold text-slate-900">{{ $classes['classes_attended'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Missed</div><div class="mt-1 text-2xl font-semibold text-slate-900">{{ $classes['classes_missed'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Attendance %</div><div class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($classes['attendance_percent'], 1) }}%</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Learning Hours</div><div class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($classes['total_learning_hours'], 1) }}</div></div>
      </div>
    </section>

    <section class="grid gap-4 border-b border-slate-200 pb-8">
      <h2 class="text-lg font-semibold text-slate-900">Homework Performance</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $homework['homework_assigned'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $homework['homework_completed'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $homework['homework_pending'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Missed</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $homework['homework_missed'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completion %</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($homework['completion_percent'], 1) }}%</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Accuracy %</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($homework['average_accuracy_percent'], 1) }}%</div></div>
      </div>
    </section>

    <section class="grid gap-4 border-b border-slate-200 pb-8">
      <h2 class="text-lg font-semibold text-slate-900">Puzzle Performance</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Puzzles Attempted</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $puzzles['puzzles_attempted'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Correct</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $puzzles['correct'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Wrong</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $puzzles['wrong'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Accuracy %</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($puzzles['accuracy_percent'], 1) }}%</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hints Used</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $puzzles['hints_used'] }}</div></div>
      </div>
    </section>

    <section class="grid gap-4 border-b border-slate-200 pb-8">
      <h2 class="text-lg font-semibold text-slate-900">Quiz Performance</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quiz Sessions Played</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $quizzes['quiz_sessions_played'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average Score</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($quizzes['average_score'], 1) }}%</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Highest Score</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($quizzes['highest_score'], 1) }}%</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Accuracy %</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($quizzes['quiz_accuracy_percent'], 1) }}%</div></div>
      </div>
    </section>

    <section class="grid gap-4 border-b border-slate-200 pb-8">
      <h2 class="text-lg font-semibold text-slate-900">Tournament Performance</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Played</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $tournaments['tournament_played'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Missed</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $tournaments['tournament_missed'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Wins</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $tournaments['wins'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Draws</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $tournaments['draws'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Losses</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $tournaments['losses'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Points</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($tournaments['tournament_points'], 1) }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Best Rank</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $tournaments['best_rank'] ?? '—' }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tournament Participation</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ $tournaments['tournament_participation'] }}</div></div>
        <div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tournament Performance %</div><div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($tournaments['tournament_performance_percent'], 1) }}%</div></div>
      </div>
    </section>

    <section class="grid gap-4 border-b border-slate-200 pb-8">
        <div class="flex items-center justify-between gap-4">
          <h2 class="text-lg font-semibold text-slate-900">Chess Assessment</h2>
          <div class="flex flex-wrap gap-4 text-sm font-semibold text-slate-700">
          <span>Total Score: <span data-total-score>{{ $totalMarks }}</span> / 100</span>
          <span>Percentage: <span data-total-percentage>{{ number_format($percentage, 1) }}</span>%</span>
          </div>
        </div>

      @if($canEditAssessment && $assessmentSaveRoute)
        <form method="POST" action="{{ $assessmentSaveRoute }}" class="grid gap-6">
          @csrf
          <input type="hidden" name="period_start" value="{{ $dateFrom }}">
          <input type="hidden" name="period_end" value="{{ $dateTo }}">
          <div class="grid gap-4 md:grid-cols-2">
            @foreach($assessmentLabels as $field => $label)
              @php
                $reference = $assessmentReferences[$field] ?? null;
                $currentValue = $assessmentValues[$field];
              @endphp
              <label class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="text-sm font-semibold text-slate-900">{{ $label }}</div>
                    @if($reference)
                      <div class="mt-1 text-xs font-medium text-slate-500">{{ $reference['label'] }}: <span class="text-slate-700">{{ $reference['value'] }}</span></div>
                    @endif
                  </div>
                  <div class="text-sm font-semibold text-slate-700"><span data-score-label="{{ $field }}">{{ $currentValue }}</span> / 10</div>
                </div>
                <input
                  type="number"
                  min="0"
                  max="10"
                  step="1"
                  name="{{ $field }}"
                  value="{{ $currentValue }}"
                  data-score-input="{{ $field }}"
                  class="ui-input"
                >
                <div class="h-3 overflow-hidden rounded-full bg-slate-200">
                  <div
                    class="h-full rounded-full bg-emerald-500 transition-all duration-150"
                    data-score-bar="{{ $field }}"
                    style="width: {{ $currentValue * 10 }}%;"
                  ></div>
                </div>
                @error($field)
                  <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                @enderror
              </label>
            @endforeach
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="grid gap-2">
              <span class="text-sm font-semibold text-slate-700">Strengths</span>
              <textarea name="strengths" rows="4" class="ui-textarea">{{ old('strengths', $latestAssessment?->strengths) }}</textarea>
            </label>
            <label class="grid gap-2">
              <span class="text-sm font-semibold text-slate-700">Areas to Improve</span>
              <textarea name="areas_to_improve" rows="4" class="ui-textarea">{{ old('areas_to_improve', $latestAssessment?->areas_to_improve) }}</textarea>
            </label>
            <label class="grid gap-2">
              <span class="text-sm font-semibold text-slate-700">Coach Remarks</span>
              <textarea name="coach_remarks" rows="4" class="ui-textarea">{{ old('coach_remarks', $latestAssessment?->coach_remarks) }}</textarea>
            </label>
            <label class="grid gap-2">
              <span class="text-sm font-semibold text-slate-700">Next Focus</span>
              <textarea name="next_focus" rows="4" class="ui-textarea">{{ old('next_focus', $latestAssessment?->next_focus) }}</textarea>
            </label>
          </div>

          <div class="flex justify-end">
            <x-ui.button type="submit">Save Report for {{ $period['label'] }}</x-ui.button>
          </div>
        </form>
      @else
        <div class="grid gap-4 md:grid-cols-2">
          @foreach($assessmentLabels as $field => $label)
            @php $reference = $assessmentReferences[$field] ?? null; @endphp
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="text-sm font-semibold text-slate-900">{{ $label }}</div>
                  @if($reference)
                    <div class="mt-1 text-xs font-medium text-slate-500">{{ $reference['label'] }}: <span class="text-slate-700">{{ $reference['value'] }}</span></div>
                  @endif
                </div>
                <div class="text-sm font-semibold text-slate-700">{{ $assessmentValues[$field] }}/10</div>
              </div>
              <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $assessmentValues[$field] * 10 }}%;"></div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </section>

    <section class="grid gap-4">
      <h2 class="text-lg font-semibold text-slate-900">Coach Feedback</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assessed By</div>
          <div class="mt-2 text-sm text-slate-700">{{ $latestAssessment?->assessor?->name ?? '—' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</div>
          <div class="mt-2 text-sm text-slate-700">{{ $latestAssessment?->assessor_role ? ucfirst($latestAssessment->assessor_role) : ($latestAssessment?->assessor ? ucfirst($latestAssessment->assessor->role) : '—') }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assessment Date</div>
          <div class="mt-2 text-sm text-slate-700">{{ $latestAssessment?->assessed_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '—' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Updated</div>
          <div class="mt-2 text-sm text-slate-700">{{ $latestAssessment?->updated_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '—' }}</div>
        </div>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Strengths</div>
          <div class="mt-2 text-sm text-slate-700">{{ $latestAssessment?->strengths ?: '—' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Areas to Improve</div>
          <div class="mt-2 text-sm text-slate-700">{{ $latestAssessment?->areas_to_improve ?: '—' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Coach Remarks</div>
          <div class="mt-2 text-sm text-slate-700">{{ $latestAssessment?->coach_remarks ?: '—' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Next Focus</div>
          <div class="mt-2 text-sm text-slate-700">{{ $latestAssessment?->next_focus ?: '—' }}</div>
        </div>
      </div>
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-score-input]').forEach(function (input) {
    input.addEventListener('input', function () {
      const field = input.getAttribute('data-score-input');
      const raw = parseInt(input.value || '0', 10);
      const value = Math.min(10, Math.max(0, Number.isNaN(raw) ? 0 : raw));
      const label = document.querySelector('[data-score-label="' + field + '"]');
      const bar = document.querySelector('[data-score-bar="' + field + '"]');

      if (label) {
        label.textContent = value;
      }

      if (bar) {
        bar.style.width = (value * 10) + '%';
      }

      const allInputs = Array.from(document.querySelectorAll('[data-score-input]'));
      const total = allInputs.reduce(function (sum, scoreInput) {
        const numeric = parseInt(scoreInput.value || '0', 10);
        return sum + Math.min(10, Math.max(0, Number.isNaN(numeric) ? 0 : numeric));
      }, 0);
      const totalLabel = document.querySelector('[data-total-score]');
      const percentageLabel = document.querySelector('[data-total-percentage]');

      if (totalLabel) {
        totalLabel.textContent = total;
      }

      if (percentageLabel) {
        percentageLabel.textContent = total.toFixed(1);
      }
    });
  });
});
</script>
@endpush

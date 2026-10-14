@extends('layouts.app')
@section('page-title', 'Reports')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@php
  $baseQuery = array_filter([
    'coach_id' => $filters['coach_id'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
  ], fn ($value) => $value !== null && $value !== '');

  $reportTypes = [
    'attendance' => [
      'label' => 'Attendance',
      'description' => 'Coach class attendance',
      'count' => $summary['attended'],
    ],
    'cancelled' => [
      'label' => 'Cancelled Classes',
      'description' => 'Classes cancelled or missed',
      'count' => $summary['cancelled'],
    ],
    'late' => [
      'label' => 'Joined Late',
      'description' => 'Classes started after schedule',
      'count' => $summary['late'],
    ],
  ];

  $reportTabs = [
    [
      'label' => 'Student',
      'href' => route('admin.reports', ['tab' => 'student']),
      'active' => $activeTab === 'student',
    ],
    [
      'label' => 'Coach',
      'href' => route('admin.reports', array_merge($baseQuery, ['tab' => 'coach', 'type' => $reportType])),
      'active' => $activeTab === 'coach',
    ],
  ];

  $coachReportTypeTabs = collect($reportTypes)->map(fn ($report, $type) => [
    'label' => $report['label'],
    'count' => $report['count'],
    'href' => route('admin.reports', array_merge($baseQuery, ['tab' => 'coach', 'type' => $type])).'#coach-reports',
    'active' => $reportType === $type,
  ])->values()->all();
@endphp

@section('content')
<div class="ui-page-stack">
  <x-ui.page-header
    title="Reports"
    subtitle="A unified view of learning activity and academy operations."
  />

  <x-ui.tabs :items="$reportTabs" aria-label="Report audience" class="ui-section-tabs" />

  @if($activeTab === 'student')
    @include('reports._students-list', [
      'routePrefix' => 'admin',
      'studentListAction' => route('admin.reports'),
      'studentResetUrl' => route('admin.reports', ['tab' => 'student']),
      'embeddedStudentTab' => true,
      'studentSearch' => $studentSearch,
      'savedReports' => $savedReports,
      'canCreateReports' => true,
      'reportStatusTab' => $studentStatus,
      'savedReportCount' => $savedReportCount,
      'needAssessmentCount' => $needAssessmentCount,
      'dateFrom' => $filters['date_from'],
      'dateTo' => $filters['date_to'],
    ])
  @else
  <section class="grid gap-5" id="coach-reports">
    <div>
      <h2 class="text-xl font-semibold text-slate-900">Coach Reports</h2>
      <p class="mt-1 text-sm text-slate-500">Review attendance, cancelled classes, and late class starts.</p>
    </div>

    <form method="GET" action="{{ route('admin.reports') }}" class="ui-card ui-report-filters">
      <input type="hidden" name="tab" value="coach">
      <input type="hidden" name="type" value="{{ $reportType }}">
      <label class="ui-field">
        <span class="ui-label">Coach</span>
        <select name="coach_id" class="ui-select">
          <option value="">All coaches</option>
          @foreach($coachOptions as $coach)
            <option value="{{ $coach->id }}" @selected((int) $filters['coach_id'] === (int) $coach->id)>{{ $coach->name }}</option>
          @endforeach
        </select>
      </label>
      <label class="ui-field">
        <span class="ui-label">From</span>
        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="ui-input">
      </label>
      <label class="ui-field">
        <span class="ui-label">To</span>
        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="ui-input">
      </label>
      <div class="ui-report-filter-actions">
        <x-ui.button type="submit">Apply</x-ui.button>
        @if(array_filter($baseQuery))
          <x-ui.button :href="route('admin.reports', ['tab' => 'coach', 'type' => $reportType])" variant="secondary">Reset</x-ui.button>
        @endif
      </div>
    </form>

    <x-ui.tabs :items="$coachReportTypeTabs" aria-label="Coach report type" class="ui-section-tabs" />

    @if($reportType === 'attendance')
      @if($coachRows->isEmpty())
        <x-ui.empty-state title="No coaches found" description="No coaches match the selected filters." />
      @else
        <x-ui.table>
          <x-slot:head>
            <tr>
              <th>Coach</th>
              <th>Classes</th>
              <th>Attended</th>
              <th>Cancelled</th>
              <th>Joined Late</th>
              <th>Attendance</th>
            </tr>
          </x-slot:head>
          @foreach($coachRows as $row)
            <tr>
              <td>
                <div class="grid gap-1">
                  <div class="font-semibold text-slate-900">{{ $row['coach']->name }}</div>
                  <div class="text-xs text-slate-500">{{ $row['coach']->username }}</div>
                </div>
              </td>
              <td>{{ $row['total'] }}</td>
              <td><x-ui.badge :label="$row['attended'].' / '.$row['total']" variant="success" /></td>
              <td>{{ $row['cancelled'] }}</td>
              <td>{{ $row['late'] }}</td>
              <td class="font-semibold text-slate-900">{{ number_format($row['attendance_percent'], 1) }}%</td>
            </tr>
          @endforeach
        </x-ui.table>
      @endif
    @else
      @if(!$activityPaginator || $activityPaginator->isEmpty())
        <x-ui.empty-state
          :title="$reportType === 'cancelled' ? 'No cancelled classes' : 'No late joins'"
          description="No coach activity matches the selected filters."
        />
      @else
        <x-ui.table>
          <x-slot:head>
            <tr>
              <th>Class</th>
              <th>Coach</th>
              <th>Batch</th>
              <th>Scheduled</th>
              <th>{{ $reportType === 'cancelled' ? 'Cancelled At' : 'Started At' }}</th>
              <th>{{ $reportType === 'cancelled' ? 'Status' : 'Delay' }}</th>
            </tr>
          </x-slot:head>
          @foreach($activityPaginator as $class)
            @php
              $scheduledStart = $class->scheduledStart();
              $actualStart = $class->actualStart();
              $cancelledAt = $class->cancelled_at ?? $class->actualEnd() ?? $class->ended_at;
              $delayMinutes = $scheduledStart && $actualStart ? $scheduledStart->diffInMinutes($actualStart) : 0;
            @endphp
            <tr>
              <td class="font-semibold text-slate-900">{{ $class->name }}</td>
              <td>{{ $class->coach?->name ?? '—' }}</td>
              <td>{{ $class->group?->name ?? '—' }}</td>
              <td>{{ $scheduledStart?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '—' }}</td>
              <td>
                {{ ($reportType === 'cancelled' ? $cancelledAt : $actualStart)?->timezone(config('app.timezone'))->format('d M Y, h:i A') ?? '—' }}
              </td>
              <td>
                @if($reportType === 'cancelled')
                  <x-ui.badge label="Cancelled" variant="danger" />
                @else
                  <x-ui.badge :label="$delayMinutes.' min late'" variant="warning" />
                @endif
              </td>
            </tr>
          @endforeach
        </x-ui.table>

        <div class="pt-1">
          {{ $activityPaginator->links('components.ui.pagination') }}
        </div>
      @endif
    @endif
  </section>
  @endif
</div>
@endsection

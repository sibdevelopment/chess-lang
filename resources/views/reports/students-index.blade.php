@extends('layouts.app')
@section('page-title', 'Reports')
@section('sidebar-nav')
  @include(
    auth()->user()?->isCoach()
      ? 'coach._sidebar'
      : (auth()->user()?->isStaff()
          ? 'staff._sidebar'
          : (auth()->user()?->isStudent() ? 'student._sidebar' : 'admin._sidebar'))
  )
@endsection

@section('content')
<div class="ui-page-stack">
  <x-ui.page-header
    title="Reports"
    subtitle="{{ $canCreateReports ? 'Create reports and review every saved report by date.' : 'View and download reports published by a Super Admin or Coach.' }}"
  />

  @include('reports._students-list', [
    'studentSearch' => $search,
    'savedReports' => $savedReports,
    'canCreateReports' => $canCreateReports,
    'reportStatusTab' => $reportStatusTab,
    'savedReportCount' => $savedReportCount,
    'needAssessmentCount' => $needAssessmentCount,
    'dateFrom' => $dateFrom,
    'dateTo' => $dateTo,
  ])
</div>
@endsection

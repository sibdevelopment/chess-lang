@php
  $dashboardTabs = [
    ['label' => 'Students', 'href' => route($routePrefix.'.dashboard', ['tab' => 'students']), 'active' => $tab === 'students'],
    ['label' => 'Coaches', 'href' => route($routePrefix.'.dashboard', ['tab' => 'coaches']), 'active' => $tab === 'coaches'],
    ['label' => 'Staff', 'href' => route($routePrefix.'.dashboard', ['tab' => 'staff']), 'active' => $tab === 'staff'],
    ['label' => 'Batches', 'href' => route($routePrefix.'.dashboard', ['tab' => 'groups']), 'active' => $tab === 'groups'],
  ];
@endphp

@extends('layouts.app')
@section('page-title', 'Dashboard')

@section('sidebar-nav') @include($isStaffView ? 'staff._sidebar' : 'admin._sidebar') @endsection

@section('content')
<div class="ui-page-stack">
  <x-ui.page-header title="Dashboard" />
  <x-ui.tabs :items="$dashboardTabs" aria-label="People Management" class="ui-section-tabs" />

  <div id="dashboard-hub">
    @if($tab === 'students')
      @include('admin.people._students-list', ['isDashboardHub' => true])
    @elseif($tab === 'coaches')
      @include('admin.people._coaches-list', ['isDashboardHub' => true])
    @elseif($tab === 'staff')
      @include('admin.people._staff-list', ['isDashboardHub' => true])
    @else
      @include('admin.people._groups-list', ['isDashboardHub' => true])
    @endif
  </div>
</div>
@endsection

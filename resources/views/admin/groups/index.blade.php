@extends('layouts.app')
@section('page-title','Batches')
@php
  $isStaffView = auth()->user()?->isStaff();
  $routePrefix = $isStaffView ? 'staff' : 'admin';
@endphp
@section('sidebar-nav') @include($isStaffView ? 'staff._sidebar' : 'admin._sidebar') @endsection

@section('content')
@include('admin.people._groups-list')
@endsection

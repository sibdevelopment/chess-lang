@extends('layouts.app')
@section('page-title','Students')

@php
  $isStaffView = auth()->user()?->isStaff();
  $routePrefix = $isStaffView ? 'staff' : 'admin';
@endphp

@section('sidebar-nav')
@include($isStaffView ? 'staff._sidebar' : 'admin._sidebar')
@endsection

@php
  $countryCodes = \App\Models\User::studentCountryCodes();
@endphp

@section('content')
@include('admin.people._students-list')
@endsection

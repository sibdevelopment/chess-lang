{{-- resources/views/admin/users/coaches.blade.php --}}
@extends('layouts.app')
@section('page-title','Coaches')
@php
  $isStaffView = auth()->user()?->isStaff();
  $routePrefix = $isStaffView ? 'staff' : 'admin';
@endphp
@section('sidebar-nav') @include($isStaffView ? 'staff._sidebar' : 'admin._sidebar') @endsection

@section('content')
@include('admin.people._coaches-list')
@endsection

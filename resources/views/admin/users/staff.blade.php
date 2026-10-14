{{-- resources/views/admin/users/staff.blade.php --}}
@extends('layouts.app')
@section('page-title','Staff')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@section('content')
@include('admin.people._staff-list')
@endsection

@extends('layouts.app')
@section('page-title', 'Edit Staff')

@section('sidebar-nav')
@include('admin._sidebar')
@endsection

@section('content')
<x-ui.page-header title="Edit Staff">
  <x-slot:actions><x-ui.button :href="route('admin.staff')" variant="secondary">Back</x-ui.button></x-slot:actions>
</x-ui.page-header>

<div class="card ui-form-card">
  <form method="POST" action="{{ route('admin.staff.update', $user) }}">
    @csrf
    @method('PUT')

    <div class="fg-row">
      <div class="fg">
        <label>Full name *</label>
        <input name="name" value="{{ old('name', $user->name) }}" required>
      </div>
      <div class="fg">
        <label>Username *</label>
        <input name="username" value="{{ old('username', $user->username) }}" required>
      </div>
    </div>

    {{-- Email field removed: only name, username, password are collected. --}}

    <div class="fg">
      <label>New password</label>
      <input name="password" type="password" placeholder="Leave blank to keep current password">
    </div>

    <div class="fg">
      <label>Status *</label>
      <select name="is_active" required>
        <option value="1" {{ old('is_active', $user->is_active ? '1' : '0') == '1' ? 'selected' : '' }}>Active</option>
        <option value="0" {{ old('is_active', $user->is_active ? '1' : '0') == '0' ? 'selected' : '' }}>Inactive</option>
      </select>
    </div>

    <div class="flex gap-2 mt-3">
      <button class="btn btn-primary" type="submit">Save Changes</button>
      <a href="{{ route('admin.staff') }}" class="btn">Cancel</a>
    </div>
  </form>
</div>
@endsection

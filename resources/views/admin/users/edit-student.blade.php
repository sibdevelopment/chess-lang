@extends('layouts.app')
@section('page-title', 'Edit Student')

@section('sidebar-nav')
@include('admin._sidebar')
@endsection

@php
  $countryCodes = \App\Models\User::studentCountryCodes();
@endphp

@section('content')
<x-ui.page-header title="Edit Student">
  <x-slot:actions><x-ui.button :href="route('admin.students')" variant="secondary">Back</x-ui.button></x-slot:actions>
</x-ui.page-header>

<div class="card ui-form-card">
  <form method="POST" action="{{ route('admin.students.update', $user) }}">
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

    <div class="fg">
      <label>New password</label>
      <input name="password" type="password" placeholder="Leave blank to keep current password">
    </div>

    <div class="fg">
      <label>Country / Region</label>
      <select name="country">
        <option value="">— Select region —</option>
        @foreach($countryCodes as $code => $label)
          <option value="{{ $code }}" {{ old('country', $user->country)===$code?'selected':'' }}>{{ $label }} ({{ $code }})</option>
        @endforeach
        @php
          // If the legacy long-form value isn't in our short list, keep it as a stray option so we don't lose it on save.
          $existing = old('country', $user->country);
        @endphp
        @if($existing && !array_key_exists($existing, $countryCodes))
          <option value="{{ $existing }}" selected>{{ $existing }} (legacy)</option>
        @endif
      </select>
    </div>

    <div class="fg">
      <label>Status *</label>
      <select name="is_active" required>
        <option value="1" {{ old('is_active', $user->is_active ? '1' : '0') == '1' ? 'selected' : '' }}>Active</option>
        <option value="0" {{ old('is_active', $user->is_active ? '1' : '0') == '0' ? 'selected' : '' }}>Inactive</option>
      </select>
    </div>

    <div class="fg">
      <label>Assigned Batches</label>
      <div style="display:flex;flex-wrap:wrap;gap:6px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface2);min-height:46px">
        @forelse($user->groups as $group)
          <span class="badge badge-{{ $group->level }}">{{ $group->name }}</span>
        @empty
          <span class="text-xs text-muted">No groups assigned.</span>
        @endforelse
      </div>
    </div>

    <div class="flex gap-2 mt-3">
      <button class="btn btn-primary" type="submit">Save Changes</button>
      <a href="{{ route('admin.students') }}" class="btn">Cancel</a>
    </div>
  </form>
</div>
@endsection

@extends('layouts.app')
@section('page-title', 'Edit Puzzle Set')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@section('content')
<x-ui.page-header title="Edit Puzzle Set">
  <x-slot:actions><x-ui.button :href="route('admin.puzzle-library.show', $set)" variant="secondary">Back</x-ui.button></x-slot:actions>
</x-ui.page-header>

<div class="card" style="max-width:760px">
  <form method="POST" action="{{ route('admin.puzzle-library.update', $set) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="level" value="{{ old('level', $set->level ?: 'beginner') }}">
    <input type="hidden" name="category_name" value="{{ old('category_name', $set->category_name ?: 'Mixed') }}">

    <div class="fg">
      <label>Name of Puzzle Set *</label>
      <input type="text" name="name" value="{{ old('name', $set->name) }}" required autocomplete="off">
    </div>

    <div class="fg">
      <label>Status *</label>
      <select name="is_active" required>
        <option value="1" {{ old('is_active', $set->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Active</option>
        <option value="0" {{ old('is_active', $set->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Inactive</option>
      </select>
    </div>

    <div class="flex gap-2 mt-4">
      <button type="submit" class="btn btn-primary">Save</button>
      <a href="{{ route('admin.puzzle-library.show', $set) }}" class="btn">Cancel</a>
    </div>
  </form>
</div>
@endsection

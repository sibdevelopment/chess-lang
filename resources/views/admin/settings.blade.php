@extends('layouts.app')
@section('page-title', 'Settings')
@section('sidebar-nav') @include('admin._sidebar') @endsection

@php $portalPrefix = auth()->user()?->isStaff() ? 'staff' : 'admin'; @endphp

@section('content')
<div class="ui-page-stack">
  <x-ui.page-header
    title="Settings"
    subtitle="Manage the people and learning content that shape the academy workspace."
  />

  <div class="grid gap-4 md:grid-cols-2">
    <section class="ui-card grid gap-4">
      <div>
        <div class="text-lg font-semibold">People and access</div>
        <p class="mt-1 text-sm text-slate-500">Manage administrators, staff access, and coaching accounts.</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <x-ui.button :href="route($portalPrefix.'.dashboard', ['tab' => 'staff'])" variant="secondary">Manage staff</x-ui.button>
        <x-ui.button :href="route($portalPrefix.'.dashboard', ['tab' => 'coaches'])" variant="secondary">Manage coaches</x-ui.button>
      </div>
    </section>

    <section class="ui-card grid gap-4">
      <div>
        <div class="text-lg font-semibold">Learning content</div>
        <p class="mt-1 text-sm text-slate-500">Configure the curriculum, puzzle bank, and reusable quizzes.</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <x-ui.button :href="route($portalPrefix.'.library')" variant="secondary">Curriculum</x-ui.button>
        <x-ui.button :href="route($portalPrefix.'.puzzle-library.index')" variant="secondary">Puzzle bank</x-ui.button>
        <x-ui.button :href="route($portalPrefix.'.quizzes.index')" variant="secondary">Quiz bank</x-ui.button>
      </div>
    </section>
  </div>
</div>
@endsection

@extends('layouts.app')
@section('page-title', 'Archer Discovery')
@section('sidebar-nav') @include(in_array($routePrefix, ['admin', 'staff'], true) ? 'admin._sidebar' : 'coach._sidebar') @endsection

@section('content')
<div class="ui-page-stack">
  <x-ui.page-header title="Archer Discovery" subtitle="Coach-led trial snapshot. The system does not automatically diagnose a child's chess ability." />
  @if($errors->any())<div class="ui-form-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <section class="card">
    <div class="card-title">Create Discovery Snapshot</div>
    <form method="POST" action="{{ route($routePrefix.'.archer-discovery.store') }}" class="aj-form-grid">@csrf
      <label><span>Existing student (optional)</span><select name="user_id"><option value="">Trial student without LMS ID</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }} ({{ $student->username }})</option>@endforeach</select></label>
      <label><span>Trial student name</span><input name="student_name" value="{{ old('student_name') }}" placeholder="Required when no LMS student is selected"></label>
      <label><span>Chess experience</span><input name="chess_experience" value="{{ old('chess_experience') }}" placeholder="e.g. Beginner"></label><label><span>Observed strength</span><input name="strength" value="{{ old('strength') }}" placeholder="e.g. Pattern recognition"></label>
      <label><span>Recommended focus</span><input name="focus" value="{{ old('focus') }}" placeholder="e.g. Calculation"></label><label><span>Recommended Archer level</span><select name="recommended_level"><option value="">Choose after assessment</option>@foreach(['beginner','intermediate','advanced','expert'] as $level)<option value="{{ $level }}">{{ ucfirst($level) }}</option>@endforeach</select></label>
      <label class="aj-form-span"><span>Next step</span><textarea name="next_step" rows="3" placeholder="e.g. Join the Beginner Archer Journey">{{ old('next_step') }}</textarea></label><div><button class="btn btn-primary">Save Snapshot</button></div>
    </form>
  </section>
  <section class="card"><div class="card-title">Saved snapshots</div><div class="aj-admin-stack">
    @forelse($snapshots as $snapshot)
      <details class="aj-editor"><summary><span><strong>🏹 {{ $snapshot->student_name }}</strong><small>{{ $snapshot->recommended_level ? ucfirst($snapshot->recommended_level) : 'Level not selected' }} · by {{ $snapshot->assessor->name }} · {{ $snapshot->assessed_at?->diffForHumans() }}</small></span></summary>
        <form method="POST" action="{{ route($routePrefix.'.archer-discovery.update', $snapshot) }}" class="aj-form-grid">@csrf @method('PUT')<input type="hidden" name="user_id" value="{{ $snapshot->user_id }}"><label><span>Student name</span><input name="student_name" value="{{ $snapshot->student_name }}" required></label><label><span>Experience</span><input name="chess_experience" value="{{ $snapshot->chess_experience }}"></label><label><span>Strength</span><input name="strength" value="{{ $snapshot->strength }}"></label><label><span>Focus</span><input name="focus" value="{{ $snapshot->focus }}"></label><label><span>Recommended level</span><select name="recommended_level"><option value="">Not selected</option>@foreach(['beginner','intermediate','advanced','expert'] as $level)<option value="{{ $level }}" @selected($snapshot->recommended_level === $level)>{{ ucfirst($level) }}</option>@endforeach</select></label><label class="aj-form-span"><span>Next step</span><textarea name="next_step" rows="2">{{ $snapshot->next_step }}</textarea></label><div><button class="btn btn-primary">Update Snapshot</button></div></form>
      </details>
    @empty<div class="empty">No Discovery snapshots yet.</div>@endforelse
  </div>{{ $snapshots->links() }}</section>
</div>
@endsection

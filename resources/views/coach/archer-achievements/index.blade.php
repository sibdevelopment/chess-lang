@extends('layouts.app')
@section('page-title', 'Archer Achievements')
@section('sidebar-nav') @include('coach._sidebar') @endsection

@section('content')
<div class="ui-page-stack">
  <x-ui.page-header title="Give Achievement" subtitle="Recognise effort, sportsmanship and progress. Archer Stars are awarded automatically." />

  <section class="card">
    <form method="POST" action="{{ route('coach.achievements.store') }}" class="aj-form-grid">
      @csrf
      <label><span>Student</span><select name="user_id" required><option value="">Select assigned student</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('user_id') == $student->id)>{{ $student->name }} ({{ $student->username }})</option>@endforeach</select></label>
      <label><span>Achievement</span><select name="achievement_type" required>@foreach(['sportsmanship' => 'Sportsmanship', 'effort' => 'Excellent effort', 'focus' => 'Great focus', 'improvement' => 'Strong improvement', 'leadership' => 'Leadership', 'other' => 'Other'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
      <label><span>Title</span><input name="title" value="{{ old('title') }}" maxlength="120" placeholder="e.g. Outstanding sportsmanship" required></label>
      <label class="aj-form-span"><span>Optional note</span><textarea name="note" rows="3" maxlength="1000" placeholder="What did the student do well?">{{ old('note') }}</textarea></label>
      <div class="aj-form-span"><button class="btn btn-primary">Give Achievement</button></div>
    </form>
  </section>

  <section class="card">
    <div class="card-title">Recent achievements</div>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Student</th><th>Type</th><th>Achievement</th><th>Note</th></tr></thead><tbody>
      @forelse($achievements as $achievement)
        <tr><td class="text-sm text-muted">{{ $achievement->awarded_at?->timezone(auth()->user()->preferredTimezone())->format('d M Y · g:i A') }}</td><td class="font-medium">{{ $achievement->student->name }}</td><td><span class="badge badge-active">{{ str($achievement->achievement_type)->title() }}</span></td><td>{{ $achievement->title }}</td><td class="text-sm text-muted">{{ $achievement->note ?: '—' }}</td></tr>
      @empty<tr><td colspan="5"><div class="empty">No achievements given yet.</div></td></tr>@endforelse
    </tbody></table></div>
    {{ $achievements->links() }}
  </section>
</div>
@endsection

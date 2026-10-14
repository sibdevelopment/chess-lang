@extends('layouts.app')
@section('page-title','Curriculum')

@section('sidebar-nav') @include('coach._sidebar') @endsection

@section('content')
<x-ui.page-header title="Curriculum" />

{{-- Totals --}}
<div class="stats mb-4">
  <div class="stat"><div class="stat-label">Beginner</div><div class="stat-val" style="color:#059669">{{ $totals['beginner'] ?? 0 }}</div><div class="stat-sub">PGNs</div></div>
  <div class="stat"><div class="stat-label">Intermediate</div><div class="stat-val" style="color:#d97706">{{ $totals['intermediate'] ?? 0 }}</div><div class="stat-sub">PGNs</div></div>
  <div class="stat"><div class="stat-label">Advanced</div><div class="stat-val" style="color:#dc2626">{{ $totals['advanced'] ?? 0 }}</div><div class="stat-sub">PGNs</div></div>
  <div class="stat"><div class="stat-label">Expert</div><div class="stat-val" style="color:#7c3aed">{{ $totals['expert'] ?? 0 }}</div><div class="stat-sub">PGNs</div></div>
</div>

{{-- Filter --}}
<div class="flex gap-2 mb-4">
  @foreach(array_merge(['all'], \App\Models\Chapter::LEVELS) as $lv)
  <a href="{{ route('coach.database', ['level'=>$lv]) }}"
     class="btn btn-sm {{ $level===$lv ? 'btn-primary' : '' }}">
    {{ ucfirst($lv) }}
  </a>
  @endforeach
</div>

{{-- Flat grouped list --}}
@foreach(\App\Models\Chapter::LEVELS as $lv)
  @if(isset($chapters[$lv]))
    <div class="card mb-3">
      <div class="card-title">
        <span class="badge badge-{{ $lv }}">{{ ucfirst($lv) }}</span>
        <span class="text-muted text-xs" style="margin-left:8px">{{ $chapters[$lv]->count() }} PGN(s)</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:70px">Seq</th>
              <th>Name</th>
              <th>Total Puzzles</th>
              <th>Uploaded</th>
              <th>Added by</th>
              <th style="text-align:right">Study</th>
            </tr>
          </thead>
          <tbody>
          @foreach($chapters[$lv] as $ch)
            <tr>
              <td><strong>#{{ $ch->sequence_number }}</strong></td>
              <td>
                <div style="font-weight:600">{{ $ch->name }}</div>
                @if($ch->description)<div class="text-xs text-muted">{{ \Illuminate\Support\Str::limit($ch->description, 90) }}</div>@endif
              </td>
              <td class="text-sm">{{ $ch->puzzle_count }}</td>
              <td class="text-sm text-muted">{{ $ch->created_at->format('d M Y') }}</td>
              <td class="text-sm text-muted">{{ $ch->uploader->name ?? '—' }}</td>
              <td style="text-align:right">
                <a href="{{ route('coach.database.chapter', $ch) }}" class="btn btn-xs btn-ghost">View board</a>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endforeach

@if($chapters->isEmpty())
  <x-ui.empty-state title="No curriculum available" description="Ask your administrator to add curriculum chapters." />
@endif
@endsection

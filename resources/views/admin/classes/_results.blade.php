@php
  $routePrefix = $routePrefix ?? (auth()->user()?->isStaff() ? 'staff' : 'admin');
  $isPaginator = $classes instanceof \Illuminate\Contracts\Pagination\Paginator || $classes instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
  $chapterLabel = static fn ($chapter, string $fallback = '—') =>
      $chapter
          ? trim(($chapter->sequence_number ? 'Chapter '.$chapter->sequence_number.' · ' : '').$chapter->name)
          : $fallback;
  if ($isPaginator && method_exists($classes, 'appends')) {
      $classes->appends(['section' => $tab]);
  }
@endphp

@if($tab === 'past')
  <div class="classroom-summary">
    <div class="classroom-summary-card">
      <div class="classroom-summary-label">Total Classes</div>
      <div class="classroom-summary-value">{{ $summary['total_classes'] }}</div>
    </div>
    <div class="classroom-summary-card">
      <div class="classroom-summary-label">Total Duration (Hours)</div>
      <div class="classroom-summary-value">{{ $summary['total_duration_hours'] }}</div>
    </div>
  </div>
@endif

@if($classes->count())
  <x-ui.table>
    <x-slot:head>
      <tr>
        @if($tab === 'live')
          <th>Class</th><th>Batch</th><th>Chapter</th><th>Started</th><th>Students</th><th style="text-align:right">Action</th>
        @elseif($tab === 'today')
          <th>When</th><th>Class</th><th>Batch</th><th>Chapter</th><th>Students</th><th style="text-align:right">Action</th>
        @elseif($tab === 'upcoming')
          <th>When</th><th>Class</th><th>Batch</th><th>Chapter</th><th>Students</th><th style="text-align:right">Action</th>
        @elseif($tab === 'cancelled')
          <th>Class</th><th>Batch</th><th>Chapter</th><th>Scheduled Time</th><th>Students</th><th style="text-align:right">Action</th>
        @else
          <th>Class</th><th>Batch</th><th>Chapter</th><th>Date</th><th>Class Count</th><th>Duration</th><th>Students</th><th style="text-align:right">Action</th>
        @endif
      </tr>
    </x-slot:head>
    @foreach($classes as $class)
      @php
        $scheduledAt = $class->scheduledStart()?->timezone($class->displayTimezone());
        $startedAt = $class->actualStart()?->timezone($class->displayTimezone());
        $plannedDuration = (int) ($class->duration_minutes ?: \App\Models\Group::DEFAULT_DURATION_MINUTES);
        $durationLabel = "{$plannedDuration} Minutes";
        $classCount = $tab === 'past' && $class->group_id
          ? \App\Models\LiveClass::query()
              ->where('group_id', $class->group_id)
              ->whereIn('status', ['ended', 'completed', 'cancelled'])
              ->where('id', '<=', $class->id)
              ->count()
          : null;
      @endphp
      <tr>
        @if($tab === 'live')
          <td class="font-medium">{{ $class->name }}</td>
          <td><span class="badge badge-{{ $class->group->level ?? 'ended' }}">{{ $class->group?->name ?? '—' }}</span></td>
          <td class="text-sm">{{ $chapterLabel($class->chapter) }}</td>
          <td class="text-muted text-sm">{{ $startedAt?->format('g:i A') ?? '—' }}</td>
          <td class="text-sm">{{ $class->group?->students_count ?? 0 }}</td>
        @elseif($tab === 'today' || $tab === 'upcoming')
          <td>
            <div style="font-weight:600">{{ $scheduledAt?->format('D, d M Y') ?? '—' }}</div>
            <div class="text-xs text-muted">{{ $scheduledAt?->format('g:i A') ?? '—' }}</div>
          </td>
          <td class="font-medium">{{ $class->name }}</td>
          <td><span class="badge badge-{{ $class->group->level ?? 'ended' }}">{{ $class->group?->name ?? '—' }}</span></td>
          <td class="text-sm">{{ $chapterLabel($class->chapter) }}</td>
          <td class="text-sm">{{ $class->group?->students_count ?? 0 }}</td>
        @elseif($tab === 'cancelled')
          <td class="font-medium">{{ $class->name }}</td>
          <td><span class="badge badge-{{ $class->group->level ?? 'ended' }}">{{ $class->group?->name ?? '—' }}</span></td>
          <td class="text-sm">{{ $chapterLabel($class->chapter) }}</td>
          <td class="text-muted text-sm">{{ $scheduledAt?->format('d M Y · g:i A') ?? '—' }}</td>
          <td class="text-sm">{{ $class->group?->students_count ?? 0 }}</td>
        @else
          <td class="font-medium">{{ $class->name }}</td>
          <td><span class="badge badge-{{ $class->group->level ?? 'ended' }}">{{ $class->group?->name ?? '—' }}</span></td>
          <td class="text-sm">{{ $chapterLabel($class->chapter) }}</td>
          <td class="text-muted text-sm">{{ $startedAt?->format('d M Y · g:i A') ?? '—' }}</td>
          <td class="text-sm">{{ $classCount ? 'Class '.$classCount : '—' }}</td>
          <td class="text-sm">{{ $durationLabel }}</td>
          <td class="text-sm">{{ $class->group?->students_count ?? 0 }}</td>
        @endif
        <td style="text-align:right">
          <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
            @if(in_array($tab, ['today', 'upcoming'], true))
              <button
                type="button"
                class="btn btn-sm btn-ghost"
                data-change-coach-open
                data-action="{{ route($routePrefix.'.classes.change-coach', $class) }}"
                data-class-name="{{ $class->name }}"
                data-current-coach="{{ $class->coach?->name ?? 'Unassigned' }}"
                data-available-coaches='@json(($class->replacementCoachOptions ?? collect())->map(fn($coach) => ["id" => $coach->id, "name" => $coach->name])->values())'
              >Change Coach</button>
            @endif
            @if($tab === 'live')
              <a href="{{ route($routePrefix.'.classes.show', $class) }}" class="btn btn-sm btn-primary" data-classroom-detail>View Live Details</a>
            @elseif($tab === 'past')
              <a href="{{ route($routePrefix.'.classes.show', $class) }}" class="btn btn-sm btn-primary">View Details</a>
            @elseif($tab === 'cancelled')
              <a href="{{ route($routePrefix.'.classes.show', $class) }}" class="btn btn-sm btn-primary" data-classroom-detail>View Details</a>
            @endif
          </div>
        </td>
      </tr>
    @endforeach
  </x-ui.table>

  @if($isPaginator)
    <div class="classroom-pagination">
      {{ $classes->links('components.ui.pagination') }}
    </div>
  @endif
@else
  <div class="classroom-empty">No classes found for this view.</div>
@endif

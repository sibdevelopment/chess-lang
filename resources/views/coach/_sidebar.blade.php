@php
  $coachItems = [
    ['label' => 'Classes', 'route' => 'coach.classes', 'active' => request()->routeIs('coach.classes*') || request()->routeIs('coach.class.*'), 'icon' => 'calendar'],
    ['label' => 'Homework', 'route' => 'coach.homework', 'active' => request()->routeIs('coach.homework*'), 'icon' => 'book'],
    ['label' => 'Reports', 'route' => 'coach.reports', 'active' => request()->routeIs('coach.reports*'), 'icon' => 'report'],
    ['label' => 'Curriculum', 'route' => 'coach.database', 'active' => request()->routeIs('coach.database*'), 'icon' => 'database'],
    ['label' => 'Tournaments', 'route' => 'coach.tournaments.index', 'active' => request()->routeIs('coach.tournaments*'), 'icon' => 'trophy'],
    ['label' => 'Quiz Sessions', 'route' => 'coach.quiz-sessions.index', 'active' => request()->routeIs('coach.quiz-sessions*'), 'icon' => 'quiz'],
  ];
@endphp

@foreach($coachItems as $item)
  <a class="ui-admin-nav__link {{ $item['active'] ? 'is-active' : '' }}" href="{{ route($item['route']) }}">
    <svg class="ui-admin-nav__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      @switch($item['icon'])
        @case('achievement')
          <path d="M10 2.8L12 7.2L16.8 7.7L13.2 10.9L14.2 15.7L10 13.3L5.8 15.7L6.8 10.9L3.2 7.7L8 7.2L10 2.8Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          @break
        @case('discovery')
          <circle cx="10" cy="8" r="4.5" stroke="currentColor" stroke-width="1.5"/><path d="M7.8 12L5 17M12.2 12L15 17M7 15H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          @break
        @case('calendar')
          <rect x="3.2" y="4.3" width="13.6" height="12.2" rx="1.6" stroke="currentColor" stroke-width="1.5"/><path d="M6.7 2.5V5.8M13.3 2.5V5.8M3.2 8.2H16.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          @break
        @case('book')
          <path d="M4 3.5H12.5C13.3 3.5 14 4.2 14 5V16.5H5.5C4.7 16.5 4 15.8 4 15V3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6.8 7H11.5M6.8 10H11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          @break
        @case('report')
          <path d="M4 16.5V11.8H7V16.5H4ZM8.5 16.5V7.8H11.5V16.5H8.5ZM13 16.5V3.5H16V16.5H13Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          @break
        @case('database')
          <ellipse cx="10" cy="4.2" rx="5.8" ry="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M4.2 4.2V10C4.2 11.4 6.8 12.5 10 12.5S15.8 11.4 15.8 10V4.2M4.2 10V15.8C4.2 17.2 6.8 18.3 10 18.3S15.8 17.2 15.8 15.8V10" stroke="currentColor" stroke-width="1.5"/>
          @break
        @case('trophy')
          <path d="M6 3.3H14V7.5C14 9.7 12.2 11.5 10 11.5S6 9.7 6 7.5V3.3Z" stroke="currentColor" stroke-width="1.5"/><path d="M6 5H3.5V6.5C3.5 8.2 4.8 9.5 6.5 9.5M14 5H16.5V6.5C16.5 8.2 15.2 9.5 13.5 9.5M10 11.5V15M7 16.7H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          @break
        @default
          <rect x="3.3" y="3.3" width="13.4" height="13.4" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M6.7 7H13.3M6.7 10H13.3M6.7 13H10.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
      @endswitch
    </svg>
    <span class="ui-admin-nav__label">{{ $item['label'] }}</span>
  </a>
@endforeach

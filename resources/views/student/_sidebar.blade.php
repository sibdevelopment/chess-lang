@php
  $studentItems = [
    ['label' => 'Archer Journey', 'route' => 'student.archer-journey', 'active' => request()->routeIs('student.archer-journey*'), 'icon' => 'journey'],
    ['label' => 'Classes', 'route' => 'student.classes', 'active' => request()->routeIs('student.classes*') || request()->routeIs('student.class.*'), 'icon' => 'calendar'],
    ['label' => 'Reports', 'route' => 'student.reports', 'active' => request()->routeIs('student.reports*'), 'icon' => 'report'],
    ['label' => 'Homework', 'route' => 'student.homework', 'active' => request()->routeIs('student.homework*'), 'icon' => 'book'],
    ['label' => 'Puzzle Library', 'route' => 'student.puzzle-library.index', 'active' => request()->routeIs('student.puzzle-library*'), 'icon' => 'puzzle'],
    ['label' => 'Analyse', 'route' => 'student.analyse', 'active' => request()->routeIs('student.analyse*'), 'icon' => 'analyse'],
    ['label' => 'Tournaments', 'route' => 'student.tournaments.index', 'active' => request()->routeIs('student.tournaments*'), 'icon' => 'trophy'],
    ['label' => 'Quiz Sessions', 'route' => 'student.quiz-sessions.index', 'active' => request()->routeIs('student.quiz-sessions*'), 'icon' => 'quiz'],
  ];
@endphp

@foreach($studentItems as $item)
  <a class="ui-admin-nav__link {{ $item['active'] ? 'is-active' : '' }}" href="{{ route($item['route']) }}">
    <svg class="ui-admin-nav__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      @switch($item['icon'])
        @case('journey')
          <path d="M10 2.5L12 7.4L17.2 7.8L13.2 11.2L14.4 16.3L10 13.6L5.6 16.3L6.8 11.2L2.8 7.8L8 7.4L10 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          @break
        @case('calendar')
          <rect x="3.2" y="4.3" width="13.6" height="12.2" rx="1.6" stroke="currentColor" stroke-width="1.5"/><path d="M6.7 2.5V5.8M13.3 2.5V5.8M3.2 8.2H16.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          @break
        @case('report')
          <path d="M4 16.5V11.8H7V16.5H4ZM8.5 16.5V7.8H11.5V16.5H8.5ZM13 16.5V3.5H16V16.5H13Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          @break
        @case('book')
          <path d="M4 3.5H12.5C13.3 3.5 14 4.2 14 5V16.5H5.5C4.7 16.5 4 15.8 4 15V3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6.8 7H11.5M6.8 10H11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          @break
        @case('puzzle')
          <path d="M3.3 4.2H8V6C8 7 8.8 7.8 9.8 7.8S11.6 7 11.6 6V4.2H16.7V9H14.9C13.9 9 13.1 9.8 13.1 10.8S13.9 12.6 14.9 12.6H16.7V16.7H3.3V4.2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          @break
        @case('analyse')
          <circle cx="8.5" cy="8.5" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M12.2 12.2L16.5 16.5M6.5 9.5L8 8L9.3 9.3L11.2 7.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
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

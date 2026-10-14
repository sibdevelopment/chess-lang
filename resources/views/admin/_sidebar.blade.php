@php
  $portalPrefix = auth()->user()?->isStaff() ? 'staff' : 'admin';
  $isDashboardActive = request()->routeIs($portalPrefix.'.dashboard')
    || request()->routeIs($portalPrefix.'.students*')
    || request()->routeIs($portalPrefix.'.coaches*')
    || request()->routeIs($portalPrefix.'.staff*')
    || request()->routeIs($portalPrefix.'.groups*');
  $isDatabaseActive = request()->routeIs($portalPrefix.'.library*')
    || request()->routeIs($portalPrefix.'.puzzle-library*')
    || request()->routeIs($portalPrefix.'.quizzes*');
  $isOperationsActive = request()->routeIs($portalPrefix.'.tournaments*')
    || request()->routeIs($portalPrefix.'.quiz-sessions*');
@endphp

<a class="ui-admin-nav__link {{ $isDashboardActive ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.dashboard') }}">
  <svg class="ui-admin-nav__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <path d="M3.33331 7.5L9.41069 2.94197C9.7623 2.67826 10.2373 2.67826 10.5889 2.94197L16.6666 7.5V15.8333C16.6666 16.2936 16.2935 16.6667 15.8333 16.6667H4.16665C3.70641 16.6667 3.33331 16.2936 3.33331 15.8333V7.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    <path d="M7.5 16.6667V10.8333H12.5V16.6667" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
  </svg>
  <span class="ui-admin-nav__label">Dashboard</span>
</a>

<details class="ui-admin-nav__group" {{ $isDatabaseActive ? 'open' : '' }}>
  <summary class="ui-admin-nav__summary {{ $isDatabaseActive ? 'is-active' : '' }}">
    <svg class="ui-admin-nav__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <ellipse cx="10" cy="4.16667" rx="5.83333" ry="2.5" stroke="currentColor" stroke-width="1.5"/>
      <path d="M4.16669 4.16666V10C4.16669 11.3807 6.77834 12.5 10 12.5C13.2217 12.5 15.8334 11.3807 15.8334 10V4.16666" stroke="currentColor" stroke-width="1.5"/>
      <path d="M4.16669 10V15.8333C4.16669 17.214 6.77834 18.3333 10 18.3333C13.2217 18.3333 15.8334 17.214 15.8334 15.8333V10" stroke="currentColor" stroke-width="1.5"/>
    </svg>
    <span class="ui-admin-nav__label">Database</span>
    <svg class="ui-admin-nav__chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </summary>

  <div class="ui-admin-nav__children">
    <a class="ui-admin-nav__child {{ request()->routeIs($portalPrefix.'.library*') ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.library') }}">Curriculum</a>
    <a class="ui-admin-nav__child {{ request()->routeIs($portalPrefix.'.quizzes*') ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.quizzes.index') }}">Quiz Bank</a>
    <a class="ui-admin-nav__child {{ request()->routeIs($portalPrefix.'.puzzle-library*') ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.puzzle-library.index') }}">Puzzle Bank</a>
  </div>
</details>

<a class="ui-admin-nav__link {{ request()->routeIs($portalPrefix.'.classes*') ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.classes') }}">
  <svg class="ui-admin-nav__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <rect x="3.33331" y="4.16666" width="13.3333" height="11.6667" rx="1.66667" stroke="currentColor" stroke-width="1.5"/>
    <path d="M6.66669 2.5V5.83333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    <path d="M13.3333 2.5V5.83333" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    <path d="M3.33331 8.33334H16.6666" stroke="currentColor" stroke-width="1.5"/>
  </svg>
  <span class="ui-admin-nav__label">Classes</span>
</a>

<a class="ui-admin-nav__link {{ request()->routeIs($portalPrefix.'.homework*') ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.homework') }}">
  <svg class="ui-admin-nav__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <path d="M6.66669 3.33334H13.3334C14.2538 3.33334 15 4.07953 15 5.00001V16.6667L10 13.75L5.00002 16.6667V5.00001C5.00002 4.07953 5.74621 3.33334 6.66669 3.33334Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    <path d="M7.5 7.08334H12.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    <path d="M7.5 10H11.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
  </svg>
  <span class="ui-admin-nav__label">Homework</span>
</a>

<details class="ui-admin-nav__group" {{ $isOperationsActive ? 'open' : '' }}>
  <summary class="ui-admin-nav__summary {{ $isOperationsActive ? 'is-active' : '' }}">
    <svg class="ui-admin-nav__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <path d="M4.16669 5.83334H15.8334V15.8333C15.8334 16.2936 15.4603 16.6667 15 16.6667H5.00002C4.53978 16.6667 4.16669 16.2936 4.16669 15.8333V5.83334Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
      <path d="M6.66669 5.83334V4.16667C6.66669 3.70643 7.03978 3.33334 7.50002 3.33334H12.5C12.9603 3.33334 13.3334 3.70643 13.3334 4.16667V5.83334" stroke="currentColor" stroke-width="1.5"/>
      <path d="M4.16669 9.16667H15.8334M8.33335 9.16667V10.4167H11.6667V9.16667" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    </svg>
    <span class="ui-admin-nav__label">Operations</span>
    <svg class="ui-admin-nav__chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </summary>

  <div class="ui-admin-nav__children">
    <a class="ui-admin-nav__child {{ request()->routeIs($portalPrefix.'.tournaments*') ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.tournaments.index') }}">Tournament</a>
    <a class="ui-admin-nav__child {{ request()->routeIs($portalPrefix.'.quiz-sessions*') ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.quiz-sessions.index') }}">Quiz Session</a>
  </div>
</details>

<a class="ui-admin-nav__link {{ request()->routeIs($portalPrefix.'.reports*') ? 'is-active' : '' }}" href="{{ route($portalPrefix.'.reports') }}">
  <svg class="ui-admin-nav__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <path d="M3.33331 16.6667V11.6667H6.66665V16.6667H3.33331ZM8.33331 16.6667V7.50001H11.6666V16.6667H8.33331ZM13.3333 16.6667V3.33334H16.6666V16.6667H13.3333Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
  </svg>
  <span class="ui-admin-nav__label">Reports</span>
</a>

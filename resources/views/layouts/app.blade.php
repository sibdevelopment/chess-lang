<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Archer Chess Academy') — {{ auth()->user()?->role ? ucfirst(auth()->user()->role) : '' }}</title>
<link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@stack('styles')
</head>
@php
  $authUser = auth()->user();
  $routeName = request()->route()?->getName() ?? '';
  $isAdminShell = (bool) $authUser;
  $pageTitle = trim($__env->yieldContent('page-title')) ?: 'Archer LMS';
  $portalPrefix = match ($authUser?->role) {
      'staff' => 'staff',
      'coach' => 'coach',
      'student' => 'student',
      default => 'admin',
  };
  $workspaceLabel = match ($authUser?->role) {
      'staff' => 'Staff Workspace',
      'coach' => 'Coach Workspace',
      'student' => 'Student Workspace',
      default => 'Admin Workspace',
  };
  $roleLabel = match ($authUser?->role) {
      'staff' => 'Staff',
      'coach' => 'Coach',
      'student' => 'Student',
      default => 'Super Admin',
  };

  $adminBreadcrumbs = [];
  if ($isAdminShell) {
      $role = $authUser?->role ?? 'admin';
      $portalSections = match ($role) {
          'coach' => [
              ['label' => 'Classes', 'route' => 'coach.classes', 'patterns' => ['coach.classes*', 'coach.class.*']],
              ['label' => 'Homework', 'route' => 'coach.homework', 'patterns' => ['coach.homework*']],
              ['label' => 'Reports', 'route' => 'coach.reports', 'patterns' => ['coach.reports*']],
              ['label' => 'Curriculum', 'route' => 'coach.database', 'patterns' => ['coach.database*']],
              ['label' => 'Tournaments', 'route' => 'coach.tournaments.index', 'patterns' => ['coach.tournaments*']],
              ['label' => 'Quiz Sessions', 'route' => 'coach.quiz-sessions.index', 'patterns' => ['coach.quiz-sessions*']],
              ['label' => 'Archer Achievements', 'route' => 'coach.achievements.index', 'patterns' => ['coach.achievements*']],
              ['label' => 'Archer Discovery', 'route' => 'coach.archer-discovery.index', 'patterns' => ['coach.archer-discovery*']],
          ],
          'student' => [
              ['label' => 'Archer Journey', 'route' => 'student.archer-journey', 'patterns' => ['student.archer-journey*']],
              ['label' => 'Classes', 'route' => 'student.classes', 'patterns' => ['student.classes*', 'student.class.*']],
              ['label' => 'Reports', 'route' => 'student.reports', 'patterns' => ['student.reports*']],
              ['label' => 'Homework', 'route' => 'student.homework', 'patterns' => ['student.homework*']],
              ['label' => 'Puzzle Library', 'route' => 'student.puzzle-library.index', 'patterns' => ['student.puzzle-library*']],
              ['label' => 'Analyse', 'route' => 'student.analyse', 'patterns' => ['student.analyse*']],
              ['label' => 'Tournaments', 'route' => 'student.tournaments.index', 'patterns' => ['student.tournaments*']],
              ['label' => 'Quiz Sessions', 'route' => 'student.quiz-sessions.index', 'patterns' => ['student.quiz-sessions*']],
          ],
          'staff' => [
              ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'patterns' => ['staff.dashboard']],
              ['label' => 'Students', 'route' => 'staff.students', 'patterns' => ['staff.students*']],
              ['label' => 'Coaches', 'route' => 'staff.coaches', 'patterns' => ['staff.coaches*']],
              ['label' => 'Batches', 'route' => 'staff.groups', 'patterns' => ['staff.groups*']],
              ['label' => 'Curriculum', 'route' => 'staff.library', 'patterns' => ['staff.library*']],
              ['label' => 'Puzzle Bank', 'route' => 'staff.puzzle-library.index', 'patterns' => ['staff.puzzle-library*']],
              ['label' => 'Quiz Bank', 'route' => 'staff.quizzes.index', 'patterns' => ['staff.quizzes*']],
              ['label' => 'Classes', 'route' => 'staff.classes', 'patterns' => ['staff.classes*', 'staff.class.*']],
              ['label' => 'Homework', 'route' => 'staff.homework', 'patterns' => ['staff.homework*']],
              ['label' => 'Tournaments', 'route' => 'staff.tournaments.index', 'patterns' => ['staff.tournaments*']],
              ['label' => 'Quiz Sessions', 'route' => 'staff.quiz-sessions.index', 'patterns' => ['staff.quiz-sessions*']],
              ['label' => 'Reports', 'route' => 'staff.reports', 'patterns' => ['staff.reports*']],
          ],
          default => [
              ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'patterns' => ['admin.dashboard']],
              ['label' => 'Students', 'route' => 'admin.students', 'patterns' => ['admin.students*']],
              ['label' => 'Coaches', 'route' => 'admin.coaches', 'patterns' => ['admin.coaches*']],
              ['label' => 'Staff', 'route' => 'admin.staff', 'patterns' => ['admin.staff*']],
              ['label' => 'Batches', 'route' => 'admin.groups', 'patterns' => ['admin.groups*']],
              ['label' => 'Curriculum', 'route' => 'admin.library', 'patterns' => ['admin.library*']],
              ['label' => 'Database Access', 'route' => 'admin.database', 'patterns' => ['admin.database*']],
              ['label' => 'Puzzle Bank', 'route' => 'admin.puzzle-library.index', 'patterns' => ['admin.puzzle-library*']],
              ['label' => 'Quiz Bank', 'route' => 'admin.quizzes.index', 'patterns' => ['admin.quizzes*']],
              ['label' => 'Classes', 'route' => 'admin.classes', 'patterns' => ['admin.classes*', 'admin.class.*']],
              ['label' => 'Homework', 'route' => 'admin.homework', 'patterns' => ['admin.homework*']],
              ['label' => 'Tournaments', 'route' => 'admin.tournaments.index', 'patterns' => ['admin.tournaments*']],
              ['label' => 'Quiz Sessions', 'route' => 'admin.quiz-sessions.index', 'patterns' => ['admin.quiz-sessions*']],
              ['label' => 'Reports', 'route' => 'admin.reports', 'patterns' => ['admin.reports*']],
              ['label' => 'Archer Journey', 'route' => 'admin.archer-journey.index', 'patterns' => ['admin.archer-journey*']],
              ['label' => 'Archer Discovery', 'route' => 'admin.archer-discovery.index', 'patterns' => ['admin.archer-discovery*']],
          ],
      };

      $activeSection = collect($portalSections)->first(function (array $section) {
          return collect($section['patterns'])->contains(fn (string $pattern) => request()->routeIs($pattern));
      });

      if ($activeSection) {
          $isSectionIndex = $routeName === $activeSection['route'];
          $sectionHref = route($activeSection['route']);
          $subCrumbs = [];

          if ($isSectionIndex && str_ends_with($activeSection['route'], '.dashboard')) {
              $dashboardTabs = ['students' => 'Students', 'coaches' => 'Coaches', 'staff' => 'Staff', 'groups' => 'Batches'];
              $dashboardTab = request()->query('tab');
              if (isset($dashboardTabs[$dashboardTab])) {
                  $subCrumbs[] = ['label' => $dashboardTabs[$dashboardTab]];
              }
          }

          if ($isSectionIndex && str_ends_with($activeSection['route'], '.classes')) {
              $classTabs = ['live' => 'Live', 'today' => 'Today', 'upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'];
              $classTab = request()->query('range', request()->query('section', request()->query('tab')));
              if (isset($classTabs[$classTab])) {
                  $subCrumbs[] = ['label' => $classTabs[$classTab]];
              }
          }

          if ($isSectionIndex && $activeSection['label'] === 'Curriculum') {
              $curriculumLevels = ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'expert' => 'Expert'];
              $curriculumLevel = request()->query('level');
              if (isset($curriculumLevels[$curriculumLevel])) {
                  $subCrumbs[] = ['label' => $curriculumLevels[$curriculumLevel]];
              }
          }

          if ($isSectionIndex && str_ends_with($activeSection['route'], '.reports')) {
              $audience = request()->query('tab');
              if (in_array($audience, ['student', 'coach'], true)) {
                  $audienceLabel = ucfirst($audience);
                  $reportStatus = $audience === 'student' ? request()->query('status') : request()->query('type');
                  $statusLabels = [
                      'saved' => 'Saved Reports',
                      'need_assessment' => 'Needs Assessment',
                      'attendance' => 'Attendance',
                      'cancelled' => 'Cancelled Classes',
                      'late' => 'Joined Late',
                  ];
                  $subCrumbs[] = [
                      'label' => $audienceLabel,
                      'href' => isset($statusLabels[$reportStatus]) ? route($activeSection['route'], ['tab' => $audience]) : null,
                  ];
                  if (isset($statusLabels[$reportStatus])) {
                      $subCrumbs[] = ['label' => $statusLabels[$reportStatus]];
                  }
              }
          }

          $hasNestedPage = !$isSectionIndex && strcasecmp($pageTitle, $activeSection['label']) !== 0;
          $hasSubCrumbs = $subCrumbs !== [];
          $adminBreadcrumbs[] = [
              'label' => $activeSection['label'],
              'href' => ($hasNestedPage || $hasSubCrumbs) ? $sectionHref : null,
          ];

          if ($hasNestedPage) {
              $adminBreadcrumbs[] = ['label' => $pageTitle];
          } else {
              foreach ($subCrumbs as $subCrumb) {
                  $adminBreadcrumbs[] = $subCrumb;
              }
          }
      } else {
          $adminBreadcrumbs[] = ['label' => $pageTitle];
      }
  }
@endphp
<body>
@if($isAdminShell)
<div class="ui-admin-shell">
  <aside class="ui-admin-sidebar" id="admin-sidebar">
    <div class="ui-admin-brand">
      <div class="ui-admin-brand__identity">
        <div class="ui-admin-brand__logo">
          <img src="{{ asset('logo.png') }}" alt="Archer Chess Academy">
        </div>
        <div class="ui-admin-brand__copy">
          <div class="ui-admin-brand__title">Archer LMS</div>
          <div class="ui-admin-brand__subtitle">{{ $workspaceLabel }}</div>
        </div>
      </div>
      <x-ui.button type="button" variant="secondary" size="sm" class="ui-button--icon ui-admin-sidebar-collapse" data-admin-sidebar-collapse aria-label="Collapse sidebar" title="Collapse sidebar">
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M12.5 4.16666L6.66669 9.99999L12.5 15.8333" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </x-ui.button>
    </div>

    <nav class="ui-admin-nav" aria-label="{{ $roleLabel }} navigation">
      @yield('sidebar-nav')
    </nav>

    <div class="ui-admin-sidebar__footer">
      <div class="ui-admin-user">
        <div class="av" style="background:{{ $authUser->avatar_color }}">{{ $authUser->initials }}</div>
        <div class="ui-admin-user__meta">
          <div class="ui-admin-user__name">{{ $authUser->name }}</div>
          <div class="ui-admin-user__handle">@ {{ $authUser->username }}</div>
        </div>
      </div>
    </div>
  </aside>

  <div class="ui-admin-overlay" id="admin-sidebar-overlay"></div>

  <div class="ui-admin-main" id="admin-main">
    <header class="ui-admin-topbar">
      <div class="ui-admin-topbar__start">
        <x-ui.button type="button" variant="secondary" size="sm" class="ui-button--icon ui-admin-sidebar-toggle" data-admin-sidebar-toggle aria-label="Open sidebar" title="Open sidebar">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M3.33331 5.83334H16.6666" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            <path d="M3.33331 10H16.6666" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            <path d="M3.33331 14.1667H16.6666" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
          </svg>
        </x-ui.button>

        <nav class="ui-admin-topbar__breadcrumbs" aria-label="Breadcrumb">
          @foreach($adminBreadcrumbs as $index => $crumb)
            @php $isCurrentCrumb = $index === count($adminBreadcrumbs) - 1; @endphp
            @if($index > 0)
              <span class="ui-admin-topbar__crumb-divider">/</span>
            @endif
            @if(!empty($crumb['href']) && !$isCurrentCrumb)
              <a href="{{ $crumb['href'] }}" class="ui-admin-topbar__crumb">{{ $crumb['label'] }}</a>
            @else
              <span class="ui-admin-topbar__crumb {{ $isCurrentCrumb ? 'is-current' : '' }}">{{ $crumb['label'] }}</span>
            @endif
          @endforeach
        </nav>
      </div>

      <div class="ui-admin-topbar__end">
        @yield('topbar-actions')
        <div class="ui-admin-topbar__profile">
          <div class="av" style="background:{{ $authUser->avatar_color }}">{{ $authUser->initials }}</div>
          <div class="ui-admin-topbar__profile-meta">
            <span class="ui-admin-topbar__profile-name">{{ $authUser->name }}</span>
            <span class="ui-admin-topbar__profile-role">{{ $roleLabel }}</span>
          </div>
        </div>
      </div>
    </header>

    <main class="ui-admin-page">
      @yield('content')
    </main>

    <footer class="ui-admin-footer">
      Archer LMS · {{ $workspaceLabel }}
    </footer>
  </div>
</div>
@else
<main class="page">
  @yield('content')
</main>
@endif

<x-ui.toast />

<script>
// CSRF helper for AJAX
window.CSRF = '{{ csrf_token() }}';
window.ArcherToast = {
  show(message, type = 'success', timeout = 3000) {
    if (!message) return;
    const stack = document.getElementById('global-toast-stack');
    if (!stack) return;
    const toast = document.createElement('div');
    toast.className = `toast is-${type}`;
    const toastMessage = document.createElement('span');
    toastMessage.textContent = message;
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'toast-close';
    closeButton.setAttribute('aria-label', 'Close');
    closeButton.innerHTML = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /></svg>';
    toast.append(toastMessage, closeButton);
    const remove = () => {
      toast.classList.remove('is-visible');
      setTimeout(() => toast.remove(), 220);
    };
    closeButton.addEventListener('click', remove);
    stack.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    setTimeout(remove, timeout);
  }
};
function post(url, data={}) {
  return fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-TOKEN': window.CSRF,'Accept':'application/json'},
    body: JSON.stringify(data)
  }).then(r => r.json());
}
function del(url) {
  const f = document.createElement('form');
  f.method = 'POST'; f.action = url;
  f.innerHTML = `<input name="_method" value="DELETE"><input name="_token" value="${window.CSRF}">`;
  document.body.appendChild(f); f.submit();
}

@if(session('success'))
window.addEventListener('DOMContentLoaded', () => window.ArcherToast.show(@json(session('success')), 'success'));
@endif
@if(session('error'))
window.addEventListener('DOMContentLoaded', () => window.ArcherToast.show(@json(session('error')), 'error'));
@endif
@if(session('info'))
window.addEventListener('DOMContentLoaded', () => window.ArcherToast.show(@json(session('info')), 'info'));
@endif

window.addEventListener('DOMContentLoaded', () => {
  const adminShell = document.querySelector('.ui-admin-shell');
  if (!adminShell) return;

  const sidebar = document.getElementById('admin-sidebar');
  const overlay = document.getElementById('admin-sidebar-overlay');
  const toggleButton = document.querySelector('[data-admin-sidebar-toggle]');
  const collapseButton = document.querySelector('[data-admin-sidebar-collapse]');
  const collapsedKey = 'archer-admin-sidebar-collapsed';

  const setDrawer = (open) => {
    sidebar?.classList.toggle('is-open', open);
    overlay?.classList.toggle('is-open', open);
  };

  const setCollapsed = (collapsed) => {
    adminShell.classList.toggle('is-collapsed', collapsed);
    try {
      localStorage.setItem(collapsedKey, collapsed ? '1' : '0');
    } catch (error) {
      // Ignore storage failures and keep the UI interactive.
    }
  };

  const applyStoredCollapse = () => {
    if (window.innerWidth < 1200) {
      adminShell.classList.remove('is-collapsed');
      return;
    }

    try {
      setCollapsed(localStorage.getItem(collapsedKey) === '1');
    } catch (error) {
      adminShell.classList.remove('is-collapsed');
    }
  };

  toggleButton?.addEventListener('click', () => setDrawer(!sidebar?.classList.contains('is-open')));
  overlay?.addEventListener('click', () => setDrawer(false));
  collapseButton?.addEventListener('click', () => {
    if (window.innerWidth < 768) {
      setDrawer(false);
      return;
    }

    setCollapsed(!adminShell.classList.contains('is-collapsed'));
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
      setDrawer(false);
    }
    applyStoredCollapse();
  });

  applyStoredCollapse();
});
</script>

@auth
@if(auth()->user()->role === 'student')
{{-- Tournament auto-redirect poller. When a round starts and the student gets paired,
     this pulls them straight into their game from any page they happen to be on.
     The play page itself sets window.__INSIDE_TOURNAMENT_GAME so we don't redirect a
     student who's already at the board. --}}
<script>
(function () {
  if (window.__INSIDE_TOURNAMENT_GAME) return; // play page disables this
  const URL = "{{ route('student.active-game-check') }}";
  const POLL_MS = 5000;
  let stopped = false;
  async function tick() {
    if (stopped) return;
    try {
      const r = await fetch(URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      if (!r.ok) return;
      const data = await r.json();
      if (data && data.has_game && data.url) {
        stopped = true;
        // Hard navigation — middleware also confirms before play page renders.
        window.location.href = data.url;
      }
    } catch (e) { /* network blip — try again next tick */ }
  }
  // First check after 1s so the page paints first, then every 5s.
  setTimeout(tick, 1000);
  setInterval(tick, POLL_MS);
})();
</script>
@endif
@endauth

@stack('scripts')
</body>
</html>

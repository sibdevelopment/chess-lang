# ARCHER CURRENT SYSTEM ANALYSIS

## 1. Project Overview

Archer Chess Academy is an existing Laravel 11 LMS focused on role-based chess academy operations. The current system already provides:

- Username/password login for `admin`, `staff`, `coach`, and `student`
- Separate portals and sidebars for each role
- User management for students, staff, and coaches
- Group management with coach assignment, student assignment, curriculum sequencing, and weekly schedules
- PGN/chapter library management
- Coach-facing database view of curriculum chapters
- Auto-generated scheduled live classes from group schedules
- Coach live classroom with board broadcast, chat, presence, questions, and leaderboard
- Student live classroom join and board polling
- Homework assignment and submission
- Student analysis/study upload area
- Tournament scheduling, registration, Swiss pairings, play, spectate, and standings
- Quiz bank, quiz session scheduling, live quiz broadcast, and student answering
- Scheduled console commands for class generation, class no-show cancellation, and tournament processing

The application is not a generic LMS. It is a role-driven chess operations platform with heavy business logic in controllers and support classes rather than in a domain-service or repository layer.

Key architectural characteristics:

- Laravel version: `^11.0`
- PHP requirement: `^8.2`
- Chess move validation library: `p-chess/chess`
- Reverb is installed, but the platform still relies primarily on HTTP polling
- UI is Blade-based with large inline CSS in the main layout and additional inline scripts inside views
- Business-critical scheduling logic is centralized in `app/Support/ClassScheduler.php`, `app/Support/GroupChapterProgressionService.php`, and tournament support classes

## 2. Folder Structure

Important folders in the current project:

- [`app/Http/Controllers`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers)
  Main application controllers. Split by role and feature:
  - `Admin`
  - `Coach`
  - `Student`
  - `Live`
  - `Quiz`
  - `Tournament`

- [`app/Models`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models)
  Eloquent models for users, groups, classes, homework, quizzes, tournaments, analyses, and support tables.

- [`app/Support`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support)
  Core business-logic helpers:
  - `ClassScheduler`
  - `GroupChapterProgressionService`
  - `SwissPairing`
  - `TournamentScheduler`

- [`app/Http/Middleware`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Middleware)
  Custom middleware:
  - role-based access middleware
  - forced redirect for students with active tournament games

- [`app/Console/Commands`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Console/Commands)
  Operational commands for:
  - academy reset
  - auto-cancelling late classes
  - generating class schedules
  - removing legacy users

- [`resources/views`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views)
  Blade UI by module and role:
  - `admin`
  - `coach`
  - `student`
  - `staff`
  - `tournaments`
  - `quizzes`
  - `quiz-sessions`
  - shared `partials`
  - `layouts`
  - shared dashboard styling

- [`resources/js`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/js)
  Minimal Vite entrypoint only. Most page behavior is inline in Blade views.

- [`resources/css`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/css)
  Minimal Tailwind/Vite entrypoint. The actual UI styling mostly lives inline in Blade layouts and dashboard partials.

- [`database/migrations`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/database/migrations)
  Schema history. Contains base schema plus many feature migrations for classes, quizzes, tournaments, schedules, and progression.

- [`database/schema/mysql-schema.sql`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/database/schema/mysql-schema.sql)
  SQL dump-like schema reference. Useful because current code and current DB are not guaranteed to be perfectly aligned.

- [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php)
  All web routes. The app is organized primarily by route prefix and role middleware.

- [`routes/console.php`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/console.php)
  Scheduled commands for tournaments and live-class schedule maintenance.

- [`tests/Feature`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature)
  Feature coverage exists for auth hardening, group scheduling, portal safety, and tournament backend behavior.

Filesystem anomalies currently present:

- There are malformed extra directories under `resources/views`:
  - `resources/views/{admin`
  - `resources/views/{admin/{users,groups,classes,homework},coach`
- These look like accidental shell-expansion artifacts or bad copy operations and should be treated as structural clutter.

## 3. Database

### Current Tables

Observed from the running schema:

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `groups`
- `group_student`
- `group_schedules`
- `group_chapter_completions`
- `pgn_folders`
- `chapters`
- `chapter_group`
- `database_assignments`
- `live_classes`
- `class_participants`
- `class_chat_messages`
- `class_questions`
- `class_question_attempts`
- `homework`
- `homework_submissions`
- `student_analyses`
- `tournaments`
- `tournament_groups`
- `tournament_users`
- `tournament_participants`
- `tournament_pairings`
- `quizzes`
- `quiz_sessions`
- `quiz_session_quizzes`
- `quiz_session_attendees`
- `quiz_session_responses`
- `migrations`

### Main Relationships

- `users`
  - has many coached `groups`
  - belongs to many `groups` through `group_student`
  - has many `live_classes` as coach
  - has many `homework_submissions`
  - has many `database_assignments` as coach

- `groups`
  - belongs to `coach` and `creator`
  - belongs to many `students`
  - belongs to many `chapters` via `chapter_group`
  - has many `group_schedules`
  - has many `live_classes`
  - has many `homework`
  - has many `group_chapter_completions`

- `chapters`
  - belongs to optional `pgn_folder`
  - belongs to uploader `user`
  - belongs to many `groups`
  - has many `homework`
  - has many `live_classes`

- `live_classes`
  - belongs to `coach`
  - belongs to `group`
  - belongs to `chapter`
  - belongs to `group_schedule`
  - belongs to many `participants` through `class_participants`
  - has many `homework`
  - has many `class_questions`
  - has many `class_chat_messages`

- `homework`
  - belongs to `chapter`
  - belongs to `group`
  - belongs to assigner `user`
  - belongs to optional source `live_class`
  - has many `homework_submissions`

- `student_analyses`
  - belongs to `user`

- `tournaments`
  - belongs to creator `user`
  - belongs to many target `groups`
  - belongs to many target `users`
  - has many `tournament_participants`
  - has many `tournament_pairings`

- `tournament_pairings`
  - belongs to `tournament`
  - belongs to `white`, `black`, `winner`

- `quiz_sessions`
  - belongs to `coach`
  - belongs to creator `user`
  - belongs to optional current `quiz`
  - has many `quiz_session_quizzes`
  - has many `quiz_session_attendees`
  - has many `quiz_session_responses`

- `quiz_session_responses`
  - belongs to `quiz_session`
  - belongs to `quiz`
  - belongs to `user`

### Missing or Weak Foreign Keys

- `sessions.user_id` is indexed but not constrained to `users.id`
- `password_reset_tokens.email` is not linked to `users.email` by design, but that means no referential protection
- Some runtime compatibility code suggests production databases may not have all migrated columns, especially:
  - `live_classes.cancelled_at`
  - `group_schedules.is_active`
  - `group_schedules.duration_minutes`
- This is not a foreign-key issue, but it is a real schema consistency risk

### Duplicate Tables or Duplicate Concepts

There are no obvious duplicate tables with identical purpose, but there are duplicate concepts and legacy leftovers:

- `pgn_folders` still exists even though `Admin\LibraryController` explicitly treats folders as deprecated and the active library is effectively flat
- `database_assignments` still exists even though coach access is now effectively global and assignment/revoke methods return informational messages
- Quiz country handling shows historical churn:
  - `2026_05_04_000002_add_countries_to_quizzes.php` is intentionally blank
  - `2026_05_04_000003_drop_countries_from_quizzes.php` removes the reverted feature
- `groups.country`, `users.country`, and quiz-session country filtering exist together, but country usage is inconsistent across modules

### Notable Schema Drift

The current runtime database still lacks some columns referenced by newer code. The most visible example was `live_classes.cancelled_at`. Compatibility code now exists in the model/service layer, which confirms the system must currently operate across mixed schema states.

## 4. Authentication

### Login

Authentication is implemented in [`app/Http/Controllers/AuthController.php`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/AuthController.php).

Current behavior:

- Login uses `username` plus `password`
- User must also be `is_active = true`
- Successful login redirects to a role-specific dashboard using `User::dashboardPath()`
- Failed attempts are throttled using Laravel `RateLimiter`
- Logout invalidates session and CSRF token

### Roles

Roles are stored directly in `users.role` as:

- `admin`
- `staff`
- `coach`
- `student`

Role helpers exist on the `User` model:

- `isAdmin()`
- `isStaff()`
- `isCoach()`
- `isStudent()`

### Permissions

There is no full policy/gate system in the codebase.

Authorization is handled by:

- route grouping with role middleware
- controller-level `abort_unless(...)`
- model helper methods in tournament and user flows

Examples:

- Tournament manager permissions are checked by `Tournament::canBeManagedBy()`
- Tournament viewing permissions are checked by `Tournament::canBeViewedBy()`
- Coach ownership is checked manually in coach controllers
- Student enrollment is checked manually in class and quiz flows

### Middleware

Custom middleware:

- [`app/Http/Middleware/RoleMiddleware.php`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Middleware/RoleMiddleware.php)
  Enforces role access for route groups

- [`app/Http/Middleware/EnsureActiveTournamentGame.php`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Middleware/EnsureActiveTournamentGame.php)
  Forces a student with an active tournament game back into the play screen

Middleware alias registration:

- [`app/bootstrap/app.php`](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/bootstrap/app.php)
  Registers alias `role`

Issue:

- The student route group uses middleware name `active_tournament_game`, but `app/bootstrap/app.php` only aliases `role`. That implies either:
  - another alias exists outside the inspected file, or
  - the route is relying on old cached middleware registration, or
  - this is a latent bootstrap inconsistency

### Policies

No `app/Policies` folder exists.

Current conclusion:

- There is no formal Laravel policy layer in the current project

## 5. Existing Modules

### Dashboard

Purpose:

- Role-based landing pages for admin, staff, coach, and student

Controllers:

- `Admin\DashboardController`
- `Coach\DashboardController`
- `Student\DashboardController`

Views:

- `resources/views/admin/dashboard.blade.php`
- `resources/views/staff/dashboard.blade.php`
- `resources/views/coach/dashboard.blade.php`
- `resources/views/student/dashboard.blade.php`
- `resources/views/dashboard/_styles.blade.php`

Routes:

- `admin.dashboard`
- `staff.dashboard`
- `coach.dashboard`
- `student.dashboard`

Database Tables:

- `users`
- `groups`
- `live_classes`
- `homework`
- `chapters`
- `tournaments`

Current Features:

- Role-specific dashboard summaries
- Admin/staff operational counts and recent activity widgets
- Coach dashboard for live/next class, homework review, groups, classes, tournaments
- Student dashboard for next/live class, homework, groups, tournaments, progress

Missing Features:

- No dedicated reporting dashboard module
- No analytics or historical charts beyond list widgets
- No reusable dashboard service layer

Problems:

- Data assembly is controller-heavy
- Schema drift affected dashboard queries
- Dashboard styling is split across shared dashboard partials and the global layout

### Users

Purpose:

- CRUD-style management of students, staff, and coaches

Controller:

- `Admin\UserController`

Views:

- `resources/views/admin/users/*.blade.php`

Routes:

- `admin.students*`
- `admin.staff*`
- `admin.coaches*`
- mirrored subsets under `staff.*`

Database Tables:

- `users`
- `group_student`

Current Features:

- Student create/edit
- Staff create/edit
- Coach create/edit
- Active/inactive toggle
- Student CSV and XLSX import
- Student CSV export
- Sequential student username suggestion
- Sequential coach username suggestion

Missing Features:

- No delete flows for users
- No profile/self-service user management module
- No password-reset email flow in active use

Problems:

- One large controller handles all user types and import logic
- XLSX parsing is implemented manually in the controller
- Staff and admin share the same controller actions without a separate service layer

### Groups

Purpose:

- Central batch/group management with curriculum and schedule

Controller:

- `Admin\GroupController`

Views:

- `resources/views/admin/groups/index.blade.php`
- `resources/views/admin/groups/create.blade.php`
- `resources/views/admin/groups/show.blade.php`

Routes:

- `admin.groups*`
- `staff.groups*`

Database Tables:

- `groups`
- `group_student`
- `group_schedules`
- `chapter_group`
- `group_chapter_completions`
- `live_classes`

Current Features:

- Create and update groups
- Assign coach and students
- Set starting chapter
- Set weekly schedule rows
- Auto-sequence assigned chapters from starting chapter onward
- Show chapter states and class history
- Add/remove student from a group
- Regenerate future classes after updates

Missing Features:

- No delete/archive group flow
- No explicit staff ownership model for “managed by staff”
- No separate group service/repository

Problems:

- Group controller contains validation, normalization, sequencing, schedule sync, and progression bootstrapping
- Multiple schema-compatibility branches imply inconsistent production state
- `country` is still present in the model and migrations even though business direction changed multiple times

### Curriculum

Purpose:

- Sequence chapters to groups and manage current/locked/completed progression

Controllers/Services:

- `Admin\GroupController`
- `Coach\ClassController`
- `App\Support\GroupChapterProgressionService`
- `App\Support\ClassScheduler`

Models:

- `Group`
- `Chapter`
- `GroupChapterCompletion`

Current Features:

- Group-specific chapter ordering through `chapter_group.sequence_order`
- Pointer-based progression using `groups.unlocked_chapter_index`
- Completion ledger using `group_chapter_completions`
- Backend locking of current vs completed vs future chapter
- Idempotent end-class progression behavior

Missing Features:

- No promotion flow between levels once curriculum is complete
- No dedicated curriculum-management UI beyond group edit + library

Problems:

- Progression relies on integer pointer semantics that must remain consistent everywhere
- Legacy comments still mention cross-level rollover, while implementation now intentionally stops at current assigned curriculum

### Puzzle Library

Purpose:

- Store chapter PGNs and expose them to admin and coach

Controller:

- `Admin\LibraryController`
- `Coach\DatabaseController`

Models:

- `Chapter`
- `PgnFolder`
- `DatabaseAssignment`

Views:

- `resources/views/admin/library/index.blade.php`
- `resources/views/admin/library/database.blade.php`
- `resources/views/coach/database/index.blade.php`
- `resources/views/coach/database/chapter.blade.php`

Routes:

- `admin.library*`
- `admin.database*`
- `coach.database*`
- `staff.database`

Database Tables:

- `chapters`
- `pgn_folders`
- `database_assignments`

Current Features:

- Upload PGN as chapter with level and sequence number
- Replace or merge chapter PGN
- Download chapter PGN
- Library overview by level
- Coach database read-only chapter view

Missing Features:

- No real folder-based management despite folder table existing
- No dedicated puzzle entity separate from chapter PGN
- No search/index service beyond basic query filters

Problems:

- Folder system is deprecated in code but not removed from schema
- `database_assignments` is effectively obsolete but still persists in database structure

### Quiz

Purpose:

- Build a quiz bank and run scheduled live quiz sessions

Controllers:

- `Quiz\AdminQuizController`
- `Quiz\AdminQuizSessionController`
- `Quiz\CoachQuizSessionController`
- `Quiz\StudentQuizSessionController`
- `Quiz\BroadcastPayload`

Models:

- `Quiz`
- `QuizSession`
- `QuizSessionQuiz`
- `QuizSessionAttendee`
- `QuizSessionResponse`

Views:

- `resources/views/quizzes/*.blade.php`
- `resources/views/quiz-sessions/*.blade.php`

Routes:

- `admin.quizzes*`
- `admin.quiz-sessions*`
- `staff.quiz-sessions*`
- `coach.quiz-sessions*`
- `student.quiz-sessions*`

Database Tables:

- `quizzes`
- `quiz_sessions`
- `quiz_session_quizzes`
- `quiz_session_attendees`
- `quiz_session_responses`

Current Features:

- Quiz bank creation and editing
- Session scheduling by admin/staff
- Session execution by coach
- Country-targeted session eligibility
- Student join and answer flow
- Live polling payloads
- Ended-session leaderboard

Missing Features:

- No WebSocket-first delivery; polling remains primary
- No explicit anti-cheat or answer-time ranking refinement
- No standalone quiz reports module

Problems:

- Live state is controller-driven, not event-driven
- Week cycling rules are embedded in controller logic
- Quiz bank and session execution are tightly coupled to controller code

### Homework

Purpose:

- Assign class-linked homework and capture student submissions

Controllers:

- `Admin\HomeworkController`
- `Coach\HomeworkController`
- `Student\HomeworkController`

Models:

- `Homework`
- `HomeworkSubmission`

Views:

- `resources/views/admin/homework/*`
- `resources/views/coach/homework/*`
- `resources/views/student/homework/*`

Routes:

- `admin.homework*`
- `staff.homework*`
- `coach.homework*`
- `student.homework*`

Database Tables:

- `homework`
- `homework_submissions`

Current Features:

- Homework auto-created when coach ends class
- Per-student submission records
- Coach/admin marking and notes
- Student solve/submit flow

Missing Features:

- No dedicated homework analytics or report export
- No due-date automation beyond storing a date

Problems:

- Homework generation is embedded inside class ending
- Marking logic is duplicated between admin and coach controllers

### Live Classes

Purpose:

- Handle scheduled teaching sessions and live classroom interaction

Controllers:

- `Coach\ClassController`
- `Student\ClassController`
- `Coach\QuestionController`
- `Student\QuestionController`
- `Live\ChatController`
- `Live\PresenceController`
- `Live\LeaderboardController`
- `Admin\ClassesController`

Models:

- `LiveClass`
- `ClassParticipant`
- `ClassQuestion`
- `ClassQuestionAttempt`
- `ClassChatMessage`

Support:

- `ClassScheduler`
- `GroupChapterProgressionService`

Views:

- `resources/views/coach/classes/*`
- `resources/views/student/classes/*`
- `resources/views/admin/classes/index.blade.php`
- shared partials in `resources/views/partials`

Routes:

- `coach.classes*`
- `coach.class.*`
- `student.classes*`
- `student.class.*`
- `admin.classes*`
- `staff.classes*`

Database Tables:

- `live_classes`
- `class_participants`
- `class_chat_messages`
- `class_questions`
- `class_question_attempts`
- `group_schedules`

Current Features:

- Recurring scheduled classes from weekly group schedules
- Coach start window enforcement
- Student enrollment-based join enforcement
- Board polling and optional broadcast event
- Live chat
- Presence heartbeat
- Live question broadcasting and move attempts
- Class leaderboard
- Class end progression and homework creation

Missing Features:

- No dedicated attendance report module
- No archived recording/session playback layer
- No true real-time socket-first synchronization

Problems:

- Polling is dominant, with optional broadcasting
- Significant logic is in controllers instead of an application-service layer
- Inline JS in Blade views likely carries substantial interaction complexity

### Tournament

Purpose:

- Schedule and run Swiss tournaments with live play

Controllers:

- `Tournament\AdminTournamentController`
- `Tournament\StudentTournamentController`
- `Tournament\PlayController`
- `Tournament\SpectateController`

Models:

- `Tournament`
- `TournamentParticipant`
- `TournamentPairing`

Support:

- `TournamentScheduler`
- `SwissPairing`

Views:

- `resources/views/tournaments/*.blade.php`

Routes:

- `admin.tournaments*`
- `staff.tournaments*`
- `coach.tournaments*`
- `student.tournaments*`

Database Tables:

- `tournaments`
- `tournament_groups`
- `tournament_users`
- `tournament_participants`
- `tournament_pairings`

Current Features:

- Tournament CRUD for admin/staff/coach
- Audience targeting by all students, specific groups, or specific students
- Student join/leave
- Automatic start and round processing via scheduler
- Pairings, byes, standings, Buchholz
- Spectate and PGN export
- Server-authoritative move validation and clocks

Missing Features:

- No separate tournament admin/reporting dashboard
- No explicit arbitration workflow
- No long-term tournament archive/report export layer beyond PGN

Problems:

- Complex real-time game logic lives directly in controller/support classes
- Tournament lifecycle depends on cron accuracy
- Mixed use of polling and redirect-forced navigation can be hard to maintain

### Reports

Purpose:

- Intended module name in the business context, but no implementation is present

Current State:

- No report controllers
- No report routes
- No report views
- No reporting service layer

Conclusion:

- Reports module is currently missing

### Student Portal

Purpose:

- Student-facing dashboard and workflows

Controllers:

- `Student\DashboardController`
- `Student\ClassController`
- `Student\HomeworkController`
- `Student\AnalyseController`
- `Student\QuestionController`
- `Tournament\StudentTournamentController`
- `Quiz\StudentQuizSessionController`

Current Features:

- Dashboard
- Live classes
- Homework
- Analysis/study
- Tournament participation
- Quiz participation

Missing Features:

- No student-specific reports page
- No dedicated progress transcript page

Problems:

- Portal access is route-protected but business logic is spread across many controllers

### Coach Portal

Purpose:

- Coach-facing class execution and monitoring

Controllers:

- `Coach\DashboardController`
- `Coach\ClassController`
- `Coach\QuestionController`
- `Coach\HomeworkController`
- `Coach\DatabaseController`
- `Quiz\CoachQuizSessionController`
- tournament manager via `AdminTournamentController`

Current Features:

- Dashboard
- Start/manage classes
- Board broadcast
- Live questions
- Homework review
- Database/chapter reading
- Quiz execution
- Tournament management for own tournaments

Missing Features:

- No coach-specific reports module
- No coach self-service profile/settings flow

Problems:

- Tournament creation/edit is reused from an admin controller
- Large controller responsibilities

### Staff Portal

Purpose:

- Operational management without full admin authority

Controllers:

- `Admin\DashboardController`
- `Admin\UserController`
- `Admin\GroupController`
- `Admin\HomeworkController`
- `AdminTournamentController`
- `AdminQuizSessionController`

Views:

- `resources/views/staff/dashboard.blade.php`
- `resources/views/staff/database.blade.php`

Current Features:

- Dashboard
- Student management
- Coach creation
- Group management
- Homework view/mark
- Tournament scheduling and monitoring
- Quiz session scheduling

Missing Features:

- No dedicated staff controller namespace
- No explicit staff ownership/audit layer
- No staff reports

Problems:

- Staff functionality is mostly a restricted reuse of admin controllers
- Separation of concerns is weak at the controller layer

## 6. UI Audit

### Navigation

- Navigation is role-based and sidebar-driven
- Main layout is centralized in `resources/views/layouts/app.blade.php`
- Sidebars exist per role:
  - `admin/_sidebar`
  - `staff/_sidebar`
  - `coach/_sidebar`
  - `student/_sidebar`
  - quiz/tournament sidebars are partially role-aware wrappers

### Sidebar

Strengths:

- Clear role-based grouping
- Persistent portal identity

Issues:

- Sidebar styling is large and hardcoded inline in the main layout
- Navigation logic is distributed across separate sidebar partials and some module-specific wrappers

### Dashboard

- Each role has a dashboard
- Dashboard styles are shared through `dashboard/_styles.blade.php`
- Dashboard markup is custom and non-componentized

Issues:

- Custom dashboard styling duplicates broader layout design tokens
- Dashboard data assembly is server-side and repeated by role

### Forms

Strengths:

- Most forms use Laravel validation and server redirects
- Group, tournament, quiz, and user forms exist for major workflows

Issues:

- Validation errors and form state handling are mostly controller-specific
- No shared form components
- Some modules rely on inline JS for dynamic UI

### Tables and Lists

- Tournaments, quizzes, homework, groups, and users rely on Blade list/table layouts
- Dashboard widgets favor list rows over reusable table partials

Issues:

- UI patterns are not standardized into reusable components
- Pagination exists selectively, not consistently

### Responsive Issues

Observed risks from structure:

- Heavy inline CSS and desktop-first layout shell
- Large sticky sidebar with custom mobile toggle behavior
- Live classroom and tournament play views are likely highly sensitive on smaller screens

### Duplicate Pages

Notable duplication:

- Admin and staff share controller logic but have separate dashboards
- Admin and coach tournament management reuse the same controller but separate route namespaces
- Homework mark flows are duplicated across admin and coach controllers

### Design Problems

- Styling is split across:
  - `layouts/app.blade.php`
  - `dashboard/_styles.blade.php`
  - inline page scripts/styles
- Tailwind is configured but not the dominant UI system
- The codebase is not using a clean design-component layer

## 7. Business Logic Audit

### Group Scheduling

Current implementation:

- Group schedules live in `group_schedules`
- Future scheduled classes are generated by `App\Support\ClassScheduler`
- Generation is rolling-window based and cron-backed
- Scheduled classes are rebuilt from group schedule slots and current chapter pointer
- Inactive groups or unassigned groups cause scheduled rows to be cancelled
- Weekly schedules are normalized and overlap-validated in `Admin\GroupController`

Confirmed behavior:

- Class generation exists
- Class regeneration after updates exists
- Missed/no-show auto-cancel command exists

Observed logic caveat:

- Current scheduler assigns the same current chapter to all future scheduled rows until a class is completed. That is intentional in the latest tests, but it means future rows are placeholders, not permanently chapter-advanced reservations.

### Chapter Progression

Current implementation:

- `groups.unlocked_chapter_index` points to the current chapter
- `chapter_group.sequence_order` is zero-based
- `GroupChapterProgressionService` defines `current`, `completed`, and `locked` chapter states
- Ending a live class records `group_chapter_completions` and increments the pointer once
- Duplicate end-class requests are handled idempotently

Confirmed behavior:

- Current chapter completion is enforced against the live class row
- Future chapter skipping is blocked by pointer equality checks
- Progress history is preserved in `group_chapter_completions`

Missing:

- No implemented level promotion beyond the assigned curriculum

### Homework

Current implementation:

- Homework is created automatically when a class ends
- Homework is linked to chapter, group, assigner, and optional source class
- Student submissions are per-user rows with status and coach evaluation

Missing:

- No aggregate homework reporting module
- No background reminders

### Quiz

Current implementation:

- Quiz bank is created by admin
- Sessions are scheduled by admin/staff
- Coaches start and broadcast sessions
- Students join based on level and country eligibility
- Student answering is idempotent per quiz/session/user

Missing:

- No live socket broadcast layer as the primary transport
- No coach-independent automated progression of quiz questions

### Tournament

Current implementation:

- Tournaments can be scheduled by admin/staff/coach
- Student eligibility depends on audience targeting
- Pairings and rounds auto-advance through `TournamentScheduler`
- Live chess play is server-authoritative
- Swiss pairing and Buchholz scoring exist

Missing:

- No dedicated tournament reports module
- No moderation/arbitration workflow

### Attendance

Current implementation:

- Presence heartbeat updates `class_participants.last_seen_at`
- Roster endpoint exists for coaches

Missing:

- No dedicated attendance reporting or export
- No explicit attendance table separate from class participation

### Live Classes

Current implementation:

- Start window enforced
- Join authorization by group membership
- Board state persisted to `live_classes`
- Students poll board state
- Chat and leaderboard available
- Class questions track attempts and correctness

Missing:

- No strong event-driven classroom channel architecture
- No session archival/reporting

### Student Progress

Current implementation:

- Chapter progression per group
- Homework status
- Student analysis saves
- Tournament standings
- Quiz leaderboard results after completion

Missing:

- No unified student progress module or transcript
- No reports dashboard combining class, homework, quiz, and tournament performance

## 8. Security Audit

### Authorization

Strengths:

- Route groups are role-guarded
- Controllers frequently re-check ownership and eligibility
- Tournament permissions are modeled on the `Tournament` model

Gaps:

- No Laravel policies
- Authorization rules are duplicated across controllers
- Staff permissions are implicit by route reuse rather than centrally modeled

### Validation

Strengths:

- Most create/update endpoints validate inputs
- Tournament and group validation are relatively strict
- Safety hardening tests exist

Gaps:

- Controllers remain responsible for most validation logic directly
- Some old/legacy flows may still depend on UI assumptions

### Mass Assignment

Strengths:

- Models use `$fillable`

Risks:

- Many models expose broad fillable arrays
- Since logic is controller-heavy, accidental field reuse remains possible if forms/routes expand

### CSRF

- Standard Laravel web middleware stack is active
- Forms use normal POST/PUT/DELETE patterns
- No evidence of disabled CSRF protection for these modules

### Role Protection

Strengths:

- `RoleMiddleware` is consistently used at route-group level

Gaps:

- There is no fine-grained permission matrix beyond role and manual controller checks

### Admin Access

- Full operational access is implemented
- Admin can manage all major modules

### Student Access

- Student flows are generally well constrained
- Tournament move/join/play routes perform player eligibility checks
- Live class join checks group membership

### Coach Access

- Coach access to groups, classes, homework, and own tournaments is enforced
- Coach cannot edit admin-owned tournaments

### Staff Access

- Staff routes are explicitly protected by role middleware
- Staff still reuses admin controllers, so incorrect future route exposure would be high-risk

## 9. Performance Audit

### N+1 Queries

Potential hotspots:

- Dashboard controllers aggregate multiple widgets with separate queries
- Group and class views load related chapters, students, homework, and live classes
- Tournament standings and pairings may repeatedly load related models depending on view usage

Positive signs:

- Many controllers use `with(...)` and `withCount(...)`

### Large Controllers

Notable large controllers:

- `Admin\UserController`
- `Admin\GroupController`
- `Coach\ClassController`
- `Tournament\AdminTournamentController`
- `Tournament\PlayController`
- quiz session controllers

### Duplicate Code

Examples:

- Homework mark logic duplicated in admin and coach controllers
- Staff routes reuse admin controllers without extraction
- Dashboard logic is split per role instead of using a shared service

### Slow Queries

Likely sensitive areas:

- tournament listings and standings
- dashboard aggregates
- class schedule generation across all groups
- quiz leaderboards for larger sessions

### Missing Indexes

Potential candidates:

- `group_schedules(group_id, day_of_week, start_time)` composite index is not obvious from migrations
- `live_classes(group_id, status, scheduled_for)` composite index is not obvious
- `group_chapter_completions(group_id, chapter_id)` has uniqueness, which helps
- tournament hardening migration added some indexes, but several operational queries still rely on multi-column filters without confirmed matching indexes

### Unused Files or Legacy Artifacts

- Deprecated folder/library assignment flows remain in schema and controller stubs
- Malformed extra `resources/views/{...` directories
- `fix.py`, SQL helper files, and old schema dump suggest operational drift

## 10. Code Quality

### Controllers

Current state:

- Controllers are doing orchestration, validation, business logic, persistence, and user messaging
- Some controllers are well hardened, but many are too large

Assessment:

- Functional but controller-heavy
- Good short-term delivery, weaker long-term maintainability

### Services

Present services/support classes:

- `ClassScheduler`
- `GroupChapterProgressionService`
- `SwissPairing`
- `TournamentScheduler`

Assessment:

- Core scheduling logic has been extracted sensibly
- The service layer is partial, not comprehensive

### Repositories

- No repository layer exists

### Models

Strengths:

- Most models are readable
- Domain helper methods exist where useful

Weaknesses:

- Some models carry policy/business semantics that are not consistently centralized
- Runtime schema compatibility code in models indicates environment drift

### Naming

Strengths:

- Naming is mostly clear and feature-oriented

Weaknesses:

- “Database” means coach chapter library access, which is conceptually overloaded
- “Library” and “Database” overlap in terminology
- Staff portal is not backed by a dedicated namespace, which blurs role boundaries

### Coding Style

- Mixed style
- Some files are concise and clean
- Others contain heavy inline comments, compatibility guards, or controller utility methods

### Technical Debt

Highest current technical debt areas:

- schema drift between migrations and actual runtime databases
- controller bloat
- inline CSS/JS and weak frontend component structure
- deprecated but still-present library folder system
- absence of policies and unified permission abstraction

## 11. Missing Features

Based on existing code only, the following major features/modules are missing or incomplete:

- Reports module
- Dedicated attendance reporting
- Unified student progress reporting
- Coach reports
- Staff reports
- Admin analytics/report export
- Dedicated profile/settings module
- User deletion/archive lifecycle
- Real reusable component system for UI
- Formal Laravel policies/gates
- Consistent real-time transport layer beyond polling
- Explicit staff ownership model for operational records
- Curriculum promotion workflow after finishing assigned sequence
- Full removal of deprecated PGN folder/assignment architecture

## 12. Suggested Implementation Order

Safest build order based on current architecture:

1. Stabilize schema consistency
   - align live database with migrations
   - remove compatibility uncertainty around `live_classes`, `group_schedules`, and related fields

2. Formalize authorization
   - introduce policies/gates
   - centralize role and ownership rules

3. Refactor high-risk business logic behind services
   - group scheduling
   - class lifecycle
   - homework generation
   - tournament lifecycle
   - quiz session lifecycle

4. Introduce missing reporting foundation
   - attendance data extraction
   - homework progress aggregation
   - tournament/quiz summary aggregation

5. Build Reports module
   - admin first
   - then coach/staff/student filtered views

6. Clean up deprecated curriculum/library leftovers
   - decide whether `pgn_folders` and `database_assignments` remain or are formally retired

7. Reduce controller duplication
   - shared services for homework marking, dashboard data, tournament management, and quiz scheduling

8. Improve UI structure without changing business rules
   - extract shared components
   - reduce inline CSS/JS
   - normalize responsive behavior

9. Expand automated testing
   - reports
   - dashboards
   - live classroom polling and authorization edge cases
   - schema compatibility assumptions

## Additional Current Observations

- The project already includes meaningful feature tests for auth, group scheduling, portal safety, and tournament backend logic
- The current codebase is functional and feature-rich, but it is operationally fragile where schema drift or route/controller reuse crosses role boundaries
- No source-code files were modified during this audit

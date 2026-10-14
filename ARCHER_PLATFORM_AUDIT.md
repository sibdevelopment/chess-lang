# Archer Platform Technical Audit

Date: 2026-07-23
Project Root: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy`

## 1. Executive Summary

This audit is based on the code currently present in the Laravel application. No source code was changed during this audit.

The codebase is a Laravel 11 application, not Laravel 12. It contains a multi-role academy platform for admin, staff, coach, and student users, plus group scheduling, live classroom, tournament, quiz, homework, and reporting features.

The strongest implemented areas are:

- basic username/password authentication
- role-specific dashboards and portals
- group-based class scheduling with weekly slots
- a usable curriculum sequencing foundation using `groups.unlocked_chapter_index`
- live classroom polling flows for board, questions, chat, and leaderboard
- a functional but fragile tournament workflow

The highest-risk areas are:

- admin routes are not protected by admin-only middleware
- authorization is inconsistent across some update and management endpoints
- live classroom and tournament chess logic rely heavily on client-sent board state
- several critical flows use polling instead of server-push or queue-backed orchestration
- tournament and scheduling engines have race-condition exposure
- Hostinger shared-hosting deployment is likely to be unstable without correctly configured cron, cache clearing, and asset deployment

Several features exist in partial form, but the code does not fully prove production-safe correctness for:

- strict curriculum locking under concurrency
- server-authoritative chess legality
- exact-time tournament auto-start without traffic or cron
- robust queue/retry semantics
- large-scale polling performance

## 2. Current Project Architecture

### Laravel Version

- `composer.json` requires `"laravel/framework": "^11.0"`.
- Runtime error traces previously shown in the app referenced Laravel `11.51.0`.

### Authentication Structure

- Session-based authentication via Laravel auth.
- Login is username/password based in `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/AuthController.php`.
- Role redirects are handled after login:
  - admin -> `admin.dashboard`
  - staff -> `staff.dashboard`
  - coach -> `coach.dashboard`
  - student -> `student.dashboard`

### Roles and Permissions

- Roles are stored in `users.role` as `admin`, `staff`, `coach`, `student`.
- Role middleware exists in `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Middleware/RoleMiddleware.php`.
- Important risk: the admin route group in `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php` uses only `auth`, not `role:admin`.

### Database

- MySQL-oriented schema, with some raw SQL migrations.
- Main domains:
  - users and sessions
  - groups, schedules, chapters, live classes
  - homework
  - tournaments, participants, pairings
  - quizzes and quiz sessions
  - reports and analysis data

### Frontend Stack

- Vite build pipeline in `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/vite.config.js`
- Tailwind CSS 4 and Vite 7 from `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/package.json`
- Blade templates with large inline JavaScript blocks
- Polling-heavy frontend behavior for live features

### Real-Time or Polling Architecture

- Mostly polling, not true real-time.
- Present but limited event broadcasting references:
  - `App\Events\BoardMoved`
  - `App\Events\ClassEnded`
- Student and coach live screens rely mainly on periodic fetch polling.
- Tournament play also relies on polling for state refresh.

### Queue, Scheduler and Cron Usage

- Console schedule defined in `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/console.php`
- Known scheduled command:
  - `classes:auto-cancel-late` every minute
- Manual recovery command:
  - `classes:resync`
- No strong evidence of a robust queue-driven architecture for tournaments, live class orchestration, or quiz broadcasting.

## 3. Authentication Audit

### Current Implementation

File: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/AuthController.php`

- `loginForm()`
- `login(Request $request)`
- `logout(Request $request)`

Behavior:

- validates `username` and `password`
- trims username
- requires `users.is_active = true`
- checks password with `Hash::check`
- logs in with `Auth::login`
- regenerates session
- redirects by role

### Routes

File: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php`

- `GET /login`
- `POST /`
- `POST /logout`

### Database

File: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/database/migrations/0001_01_01_000000_create_users_table.php`

Key columns:

- `name`
- `username` unique
- `email` nullable unique
- `password`
- `role`
- `is_active`
- `last_seen`

### Security Issues

- No login throttling or rate limiting.
- No lockout policy.
- Username comparison is exact and trimmed, but there is no case-normalization strategy.
- Admin route group is not protected by `role:admin`.
- Role middleware redirects users rather than failing hard with `403`, which is acceptable UX but weaker for strict security auditing.

### Role-by-Role Notes

#### Admin

- Can authenticate through the normal login flow.
- Risk: admin area is under `auth` only in routes.

#### Staff

- Protected with `role:staff` route middleware.
- Some admin-like actions are also allowed through shared controllers.

#### Coach

- Protected with `role:coach`.
- Coach-facing routes are narrower and generally better constrained than admin routes.

#### Student

- Protected with `role:student`.
- Additional `active_tournament_game` middleware can force redirect into tournament play.

## 4. Group and Scheduling Audit

### Main Files

- Model: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/Group.php`
- Model: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/GroupSchedule.php`
- Service: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/ClassScheduler.php`
- Admin controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/GroupController.php`
- Coach controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/ClassController.php`
- Student controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/ClassController.php`

### Group Creation

Method: `Admin\GroupController::store()`

The implemented flow includes:

- `name`
- `level`
- `coach_id`
- `students[]`
- `first_chapter_id`
- one or more schedules in `schedules[][day_of_week,start_time]`

Country is not currently used in group creation code.

### Country

- The current code path does not actively use group country in the group create/update flow.
- There is schema drift risk because a migration named `2026_07_23_000001_add_country_to_groups_and_is_active_to_group_schedules.php` exists, while current group controller and model logic do not rely on group country.
- Student country still exists and is used elsewhere, especially quizzes.

### Level

- `level` is required on group creation and update.
- First chapter must belong to the same level as the group.
- `ClassScheduler` also attempts level-based chapter fallback/extension.

### Coach Assignment

- Required on group create/update.
- Validation uses `exists:users,id` plus role/is_active restriction via query rule in the controller.

### Student Assignment

- `students[]` validated on create/update.
- `addStudent()` is weaker and only validates `exists:users,id`, not student role or active status.

### Weekly Day and Time

- Stored in `group_schedules`
- `GroupSchedule::DAYS` maps integer weekday values.
- Duplicate weekly slots are rejected by `Admin\GroupController::normalizeSchedules()`.

### Timezone Handling

- Scheduler timezone is hardcoded in `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/ClassScheduler.php` as `Asia/Kolkata`.
- `User::preferredTimezone()` also hardcodes `Asia/Kolkata`.
- This means multi-country student timezone behavior is not fully implemented in the current class scheduling flow.

### Automatic Scheduled-Class Generation

Method: `ClassScheduler::syncForGroup(Group $group)`

Current behavior:

- does not require admin to manually create classes
- generates scheduled `live_classes` from active weekly group schedules
- assigns chapter based on `group.unlocked_chapter_index` and slot chronology
- preserves ended classes
- resyncs future scheduled classes when schedules change

### Coach Start Class Availability

Method: `Coach\ClassController::start(LiveClass $class)`

Implemented rules:

- assigned coach only
- class must be `scheduled`
- class can start only from 5 minutes before `scheduled_for`

### Student Join Class Availability

Method: `Student\ClassController::join(LiveClass $class)`

Implemented rules:

- class must be `live`
- student must belong to the class group

### State Flow

1. Admin/staff creates group.
2. Group stores coach, students, first chapter, schedules.
3. `ClassScheduler::syncForGroup()` generates one future scheduled class per active slot.
4. Coach dashboard calls scheduler sync again and displays today/future classes.
5. Coach can start scheduled class within allowed time window.
6. Students can join only after class is live.
7. Ending class advances curriculum pointer and resyncs future scheduled classes.

### Confirmed Risks

- timezone is effectively single-zone
- schedule generation is lazy and request-triggered
- `addStudent()` lacks role/is_active validation
- schedule generation depends on chronological calculations in application code, not DB constraints

## 5. Curriculum and Chapter Progression Audit

### Main Files

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/Group.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/GroupController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/ClassController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/ClassScheduler.php`

### Chapter Sequence

- Group chapters are attached through `chapter_group`.
- Sequence is controlled by pivot column `sequence_order`.
- `Admin\GroupController::store()` builds sequence starting at the chosen first chapter, then all later chapters in that level.

### Current Chapter Pointer

- `groups.unlocked_chapter_index` is the curriculum pointer.
- Actual semantics are zero-based.

### Completed Chapter History

- History is inferable from ended `live_classes` and from chapters with `sequence_order < unlocked_chapter_index`.
- Coach live classroom separates current chapter and prior review chapters in controller logic.

### Future Chapter Locking

- Backend now relies on `Coach\ClassController::getChaptersForGroup()` to only expose chapters with `sequence_order <= unlocked_chapter_index`.
- Future chapter PGN should not be sent to the coach browser through this endpoint.

### Chapter Skipping Risks

- If any endpoint exposes arbitrary `chapter_id` or PGN without validating against `unlocked_chapter_index`, skipping becomes possible.
- `Admin\GroupController::assignChapters()` can rewrite chapter ordering only before class history exists.
- Live class advancement must use exact equality, not `>=`.

### Duplicate Completion Risks

- Duplicate end requests can over-advance if idempotency is missing.
- Current audit confirms `Coach\ClassController::end()` uses transaction and row locking logic to avoid double advancement.

### Zero-Based or One-Based Indexing Issues

Critical semantic point:

- `unlocked_chapter_index` is zero-based
- `chapter_group.sequence_order` is zero-based in current creation flow
- `Group::getNextChapter()` uses exact equality on `sequence_order = unlocked_chapter_index`
- `Group::chapterAtOffset($offset)` uses `unlocked_chapter_index + $offset`

Previous suspicion was valid: any code using `unlocked_chapter_index + 1` to identify the current unlocked chapter is an off-by-one bug.

### Relevant Methods

- `Group::getNextChapter()`
- `Group::chapterAtOffset(int $offset)`
- `ClassScheduler::syncForGroup(Group $group)`
- `Coach\ClassController::getChaptersForGroup(Group $group)`
- `Coach\ClassController::end(LiveClass $class)`
- `Admin\GroupController::assignChapters(Group $group, Request $request)`

## 6. Coach Live Classroom Audit

### Main Files

- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/ClassController.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/QuestionController.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Live/PresenceController.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Live/ChatController.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Live/LeaderboardController.php`
- View: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/coach/classes/live.blade.php`

### Starting a Class

Method: `Coach\ClassController::start()`

- assigned coach only
- only scheduled class
- available from 5 minutes before scheduled time

### PGN/Chapter Directory

Method: `Coach\ClassController::live()`

- loads current class chapter
- loads completed/past chapters for review
- future chapters are intended to remain hidden

### Synchronized Board

Method: `Coach\ClassController::updateBoard()`

- stores `board_state`, `move_index`, optional arrows/highlights
- student screen polls this state
- board synchronization is functional but not server-authoritative

### Board State Storage

Stored on `live_classes`:

- `board_state`
- `current_move_index`
- `arrows`
- `highlights`

### Questions

Files:

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/QuestionController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/QuestionController.php`

The coach can push a question with:

- FEN
- expected moves
- optional text
- arrows/highlights

### Timers

- Question timing exists.
- General live class timing is not implemented as a strict synchronized class clock in the same way tournaments require.

### Student Answers by Piece Movement

Method: `Student\QuestionController::attemptMove()`

- validates `from`, `to`, `resulting_fen`
- compares against expected move sequence

### Correct and Incorrect Validation

- Validation is application-defined and partly based on normalized move matching.
- It is not a full chess-engine legality system.

### Presence

Methods:

- `PresenceController::heartbeat()`
- `PresenceController::roster()`

### Chat

Methods:

- `ChatController::poll()`
- `ChatController::send()`

Notable risk:

- Chat table can be created dynamically by `ensureChatTable()`, which is fragile operationally.

### Leaderboard

Method: `LeaderboardController::show()`

- aggregates question attempts only

### Attendance

- Attendance is inferred through `class_participants` and heartbeat/join records.

### Class Ending

Method: `Coach\ClassController::end()`

Expected business behavior in code now:

- only assigned coach may end
- uses transaction and row locking
- advances group pointer exactly once
- does not advance on cancel/start only
- resyncs future scheduled classes after successful ending

## 7. Student Live Classroom Audit

### Main Files

- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/ClassController.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/QuestionController.php`
- View: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/student/classes/live.blade.php`

### Joining Authorization

Method: `Student\ClassController::join()`

- requires direct membership in the group
- requires class status `live`

### Board Synchronization

Method: `Student\ClassController::pollBoard()`

- polls current board state
- receives `board_state`, `move_index`, arrows, highlights, status

### Question State

Method: `Student\QuestionController::active()`

- returns active question and attempt state

### Timer

- Poll-driven question timer exists
- no evidence of strong reconciling server clock for every student interaction beyond per-question timing payloads

### Move Submission

Method: `Student\QuestionController::attemptMove()`

- move is submitted as application data, not a fully verified engine move

### Correct/Incorrect Feedback

- tracked via `class_question_attempts`

### Reconnection

- Polling architecture gives basic reconnection resilience
- No evidence of a robust session resume protocol beyond polling current state

### Class-Ended Behavior

- Student live view can detect ended status and display an ended state
- Behavior depends on polling and optional event listeners

## 8. Tournament Audit

### Main Files

- Model: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/Tournament.php`
- Model: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/TournamentPairing.php`
- Model: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/TournamentParticipant.php`
- Service: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/TournamentScheduler.php`
- Service: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/SwissPairing.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/AdminTournamentController.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/StudentTournamentController.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/PlayController.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/SpectateController.php`
- Views:
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/tournaments/index.blade.php`
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/tournaments/show.blade.php`
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/tournaments/play.blade.php`
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/tournaments/spectate.blade.php`

### Tournament Creation

Method: `AdminTournamentController::store()`

Current creation supports:

- `name`
- `format`
- `time_control`
- `rounds`
- `scheduled_at`
- `audience_type`
- target groups or target users depending on audience mode

### Admin Permissions

- Admin can create and manage tournaments through admin routes.
- Risk remains because admin route group is not role-protected globally.

### Staff Permissions

- Staff can access tournament management through shared admin/staff paths.

### Coach Permissions

- Coaches can edit tournaments in current logic where controller methods intentionally allow coach-scoped access to visible groups/students.
- Coach visibility is restricted via `formOptions()`.

### Student Permissions

- Students can list, view, join, and play eligible tournaments.

### Eligibility Modes

Implemented in `Tournament::eligibleStudentsQuery()`:

- all students
- all groups
- specific groups
- specific students

Note:

- User-request wording mentions `selected groups`; code uses `specific_groups`.

### Today, Upcoming and Past Sections

- Implemented at controller/view level using status and time filters.

### Student Registration

Method: `StudentTournamentController::join()`

- opt-in join is required
- already joined is idempotently tolerated

### Minimum Four Players

- Not implemented as specified.
- Current scheduler only requires at least 2 participants to start a tournament.

### Exact Scheduled Auto-Start

Method: `TournamentScheduler::advance()`

- lazy start on page load or scheduler activity
- not guaranteed to trigger exactly on time without cron or user traffic

### Automatic Swiss Pairing

Method: `SwissPairing::generateRound()`

- present
- score-group based
- simplified pairing strategy

### Color Balancing

- partially implemented using recent color history heuristics

### Repeat-Opponent Prevention

- attempted in pairing selection logic
- not formally proven by tests

### Byes

- supported through `is_bye`
- bye score handling exists

### Live Chess Board

- implemented in tournament play Blade
- visually functional
- not server-authoritative enough for production trust

### Legal Moves

- Frontend appears to use client-side chess logic
- server ultimately accepts client-sent position progression
- this is not equivalent to lichess/chess.com server-authoritative move legality

### Server-Authoritative Clocks

- clocks are computed and stored server-side in pairing columns
- however they still depend on correct `last_move_at` state and request timing
- `ensureOpeningClocks()` exists as a repair mechanism, indicating fragility

### Increment

- `Tournament::incrementMs()` parses increment from time-control string

### Game Results

- Resign and draw APIs exist
- timeout handling exists
- checkmate/stalemate detection depends on client-sent move semantics

### Automatic Next Rounds

- `TournamentScheduler::advanceRoundIfReady()` can create next rounds when all current games complete

### Standings

- `Tournament::standings()`

### Buchholz

- `TournamentScheduler::recomputeBuchholz()`

### Tournament Completion

- tournament marked `completed` after final round finishes

### Reconnection

- player page is polling based, so basic reconnection is possible

### Duplicate Start and Pairing Risks

- Scheduler is request-driven and does not fully lock all transitions.
- `TournamentScheduler::setResult()` updates scores in a transaction but not with comprehensive row locks across all callers.
- Concurrent requests can still be risky under load.

## 9. Quiz Audit

### Main Files

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/AdminQuizController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/AdminQuizSessionController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/CoachQuizSessionController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/StudentQuizSessionController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/quiz-sessions/show.blade.php`

### Current Implementation

- Admin manages quiz bank and quiz sessions.
- Coach runs assigned live quiz sessions.
- Student joins eligible sessions and answers current live question.

### Database Tables

Evidence from code and migrations:

- `quizzes`
- `quiz_sessions`
- `quiz_session_quizzes`
- `quiz_session_attendees`
- `quiz_session_responses`

### State Flow

1. Admin creates quiz bank items.
2. Admin schedules a quiz session.
3. Coach starts the session.
4. Coach broadcasts current quiz.
5. Students answer.
6. Session ends and leaderboard can be shown.

### Security Issues

- Eligibility relies on level and country checks in controller logic.
- Poll endpoints must trust joined attendance records.

### Performance Issues

- Polling-based live session updates.

### Missing Validations

- Coach role consistency should be rechecked carefully on session update paths.
- Country and level coupling is logic-heavy and should be better test-covered.

### Hostinger Risks

- Polling at classroom scale may be expensive on shared hosting.

## 10. Homework Audit

### Main Files

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/HomeworkController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/HomeworkController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/HomeworkController.php`

### Current Implementation

- Homework is auto-created when a live class is ended.
- Student submissions are tracked separately.
- Admin and coach can review and mark submissions.

### Security Issues

- `Coach\HomeworkController::markSubmission()` needs strict ownership verification of the homework/submission relationship.

### Performance Issues

- Mostly conventional CRUD-level load, lower risk than tournament/live polling.

### Missing Validations

- Submission ownership and cross-role access need more tests.

## 11. Reports Audit

### Main Files

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/ReportController.php`
- report-related admin views under `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/reports/`

### Current Implementation

- Reporting exists but is secondary compared with classes and tournaments.
- It appears to aggregate data from groups, classes, homework, and related student activity.

### Audit Confidence

- Moderate.
- This module received less direct deep-path inspection than authentication, groups, live classes, and tournaments.

### Risks

- Potential N+1 queries depending on filter/report breadth
- role protection inherits the admin-route weakness

## 12. Database Audit

### Relevant Tables Confirmed

- `users`
- `sessions`
- `groups`
- `group_schedules`
- `group_student`
- `chapters`
- `chapter_group`
- `live_classes`
- `class_participants`
- `class_questions`
- `class_question_attempts`
- `class_chat_messages`
- `homework`
- `homework_submissions`
- `tournaments`
- `tournament_group`
- `tournament_user`
- `tournament_participants`
- `tournament_pairings`
- `quizzes`
- `quiz_sessions`
- `quiz_session_quizzes`
- `quiz_session_attendees`
- `quiz_session_responses`

### Relationships

Important relationships from models:

- `User` <-> `Group` many-to-many for students
- `Group` -> `User` coach
- `Group` <-> `Chapter` many-to-many with ordered pivot
- `Group` -> `GroupSchedule` one-to-many
- `GroupSchedule` -> `LiveClass` one-to-many
- `LiveClass` -> `Group`, `Coach`, `Chapter`
- `Tournament` <-> `Group` and `User` targeting pivots
- `Tournament` -> `TournamentParticipant`
- `Tournament` -> `TournamentPairing`

### Missing Indexes

Not exhaustively benchmarked, but likely candidates:

- `live_classes` composite indexes around `status`, `scheduled_for`, `group_id`, `coach_id`
- `class_participants` indexes around `live_class_id`, `user_id`, `last_seen_at`
- `tournament_pairings` indexes around `tournament_id`, `round`, `status`, `white_user_id`, `black_user_id`
- `quiz_session_responses` indexes if volume increases

### Foreign Keys

- Many relationships appear conventionally modeled.
- Some operational logic suggests not all invariants are fully enforced at the DB layer.

### Data-Integrity Risks

- Curriculum pointer correctness depends on application logic, not DB constraints.
- Tournament result progression depends on transaction discipline across multiple code paths.
- Raw SQL migrations may behave differently across MySQL versions or SQLite test runs.
- Dynamic chat-table creation is operationally unusual.

## 13. Routes, Controllers, Models, Services, Jobs and Blade File Map

### Authentication Workflow

- Route file: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php`
- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/AuthController.php`
- Methods:
  - `loginForm`
  - `login`
  - `logout`

### Group Creation and Update Workflow

- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/GroupController.php`
- Methods:
  - `index`
  - `create`
  - `store`
  - `show`
  - `update`
  - `addStudent`
  - `removeStudent`
  - `assignChapters`
  - `normalizeSchedules`
  - `syncSchedules`
- Views:
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/groups/index.blade.php`
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/groups/create.blade.php`
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/groups/show.blade.php`

### Automatic Scheduling Workflow

- Service: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/ClassScheduler.php`
- Methods:
  - `syncForGroups`
  - `syncForGroup`
  - `nextOccurrence`

### Coach Class Workflow

- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/ClassController.php`
- Methods:
  - `index`
  - `start`
  - `live`
  - `updateBoard`
  - `end`
  - `getChaptersForGroup`
- View:
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/coach/classes/index.blade.php`
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/coach/classes/live.blade.php`

### Student Class Workflow

- Controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/ClassController.php`
- Methods:
  - `index`
  - `join`
  - `pollBoard`
- View:
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/student/classes/index.blade.php`
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/student/classes/live.blade.php`

### Live Question Workflow

- Coach controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/QuestionController.php`
- Student controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/QuestionController.php`

### Presence, Chat, Leaderboard Workflow

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Live/PresenceController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Live/ChatController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Live/LeaderboardController.php`

### Tournament Workflow

- Admin controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/AdminTournamentController.php`
- Student controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/StudentTournamentController.php`
- Play controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/PlayController.php`
- Spectate controller: `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/SpectateController.php`
- Services:
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/TournamentScheduler.php`
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/SwissPairing.php`
- Views:
  - `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/tournaments/*.blade.php`

### Quiz Workflow

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/AdminQuizController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/AdminQuizSessionController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/CoachQuizSessionController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/StudentQuizSessionController.php`

### Homework Workflow

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/HomeworkController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/HomeworkController.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/HomeworkController.php`

### Jobs

- No dedicated application jobs were a central part of the verified core flows.
- The current architecture is mainly request-driven plus console scheduling.

## 14. Security Issues

1. Admin route group is not protected by `role:admin` in `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php`.
2. Authentication lacks login throttling.
3. Live classroom board state trusts client-sent state too much.
4. Tournament play trusts client-side legality and result semantics too much.
5. `Admin\GroupController::addStudent()` does not strongly validate role or active status.
6. Homework marking ownership constraints need tighter verification.
7. Some modules rely on redirect behavior rather than hard authorization failure.

## 15. Performance Issues

1. Heavy polling across coach live class, student live class, tournament play, quiz sessions, and active-game checks.
2. Scheduler logic runs on normal page loads.
3. Tournament scheduler can be triggered repeatedly by browsing.
4. Live leaderboard, chat, presence, and board endpoints can multiply request volume.
5. Some dashboards and reports may incur N+1 risks if data volume grows.

## 16. Race Conditions

1. Tournament round advancement under concurrent requests.
2. Tournament result setting from move, resign, draw, and timeout paths.
3. Lazy auto-start and auto-advance depending on request timing.
4. Live class ending and curriculum advancement if not fully locked.
5. Schedule regeneration while simultaneous class state changes occur.

## 17. Hostinger Deployment Risks

### Shared-Hosting Limitations

- Shared hosting is poorly suited to polling-heavy, chess-live, timer-sensitive workflows.

### Scheduler/Cron Requirements

- `php artisan schedule:run` must run every minute, or auto-cancel and lazy scheduled maintenance will drift.

### Queue Requirements

- If future improvements rely on queues, they must be explicitly configured. Current code does not prove a queue-first deployment model.

### WebSocket Limitations

- Current app does not depend on reliable WebSockets, but any future move to true real-time play would likely exceed normal shared-hosting constraints.

### Polling Load

- Student tournament and live-class polling can create significant concurrent request load.

### Storage Permissions

- File uploads and cached view/storage paths must be writable.

### Vite Production Assets

- Production deploy must include built assets and correct `public/build` output.

### Database Configuration

- Hostinger environment must point Laravel to the correct MySQL host/port/database.
- Several earlier runtime issues described by the user are consistent with cache/config drift and deployment path mismatches.

## 18. Bug Register

| Bug ID | Severity | Module | Description | Exact file | Exact method | User impact | Recommended fix | Risk of fixing |
|---|---|---|---|---|---|---|---|---|
| BUG-001 | CRITICAL | Auth/Admin | Admin route group is protected only by `auth`, not `role:admin`. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php` | route group definition | Non-admin authenticated users may reach admin endpoints if controller checks are inconsistent. | Add `role:admin` middleware to admin route group and verify shared pages explicitly. | Medium |
| BUG-002 | CRITICAL | Tournament | Tournament move legality is not fully server-authoritative; server accepts client-driven board progression. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/PlayController.php` | `move` | Players may submit manipulated or illegal states if client protections are bypassed. | Recompute legality server-side from trusted game state using a chess engine/library. | High |
| BUG-003 | CRITICAL | Live Classroom | Coach board updates trust client-sent board state and move index without engine verification. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/ClassController.php` | `updateBoard` | Classroom board can drift or be tampered with. | Make server authoritative for board state or validate transitions strictly. | High |
| BUG-004 | HIGH | Group Scheduling | Group add-student flow validates only user existence, not student role or active status. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/GroupController.php` | `addStudent` | Wrong users could be assigned to groups. | Restrict to active students in validation/query. | Low |
| BUG-005 | HIGH | Curriculum | Off-by-one risk exists anywhere current chapter is treated as `unlocked_chapter_index + 1`. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/ClassController.php` | `getChaptersForGroup` | Coach may see wrong chapter as current/unlocked if indexing drifts. | Standardize zero-based semantics and test them. | Medium |
| BUG-006 | HIGH | Scheduling | Automatic class generation is request-driven and not guaranteed to materialize exactly on time without traffic or cron. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/ClassScheduler.php` | `syncForGroup` | Coach/student may not see expected classes at the correct time. | Run scheduler/cron reliably and reduce lazy-only dependency. | Medium |
| BUG-007 | HIGH | Tournament | Tournament auto-start is lazy and may not start exactly at scheduled time. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/TournamentScheduler.php` | `advance` | Tournament timing appears broken or late. | Trigger by cron/queue and lock transitions. | Medium |
| BUG-008 | HIGH | Tournament | Minimum-player rule is 2 in code, not 4. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/TournamentScheduler.php` | `startTournament` | Tournament can start with fewer players than business rule expects. | Enforce minimum participant threshold in scheduler and UI. | Low |
| BUG-009 | HIGH | Tournament | Pairing/result transitions are concurrency-sensitive and not comprehensively row-locked. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/TournamentScheduler.php` | `setResult`, `advanceRoundIfReady` | Duplicate scoring or inconsistent round advancement is possible under concurrent requests. | Add row locking and idempotent guards across result transitions. | High |
| BUG-010 | HIGH | Homework | Coach marking flow needs stronger ownership validation. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/HomeworkController.php` | `markSubmission` | Coach may mark submissions outside intended scope if route/model binding is abused. | Verify homework ownership and submission relation explicitly. | Low |
| BUG-011 | HIGH | Deployment | Raw SQL and cache-sensitive deployment patterns are fragile on Hostinger. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/database/migrations/2026_05_17_000001_add_cancelled_status_to_live_classes.php` | `up` | Live deploys can fail or diverge between local/testing/production. | Reduce raw SQL where possible and formalize deployment checklist. | Medium |
| BUG-012 | MEDIUM | Auth | No login rate limiting. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/AuthController.php` | `login` | Brute-force resistance is weak. | Add Laravel rate limiting. | Low |
| BUG-013 | MEDIUM | Timezone | Class scheduling is effectively hardcoded to IST despite country/timezone requirements elsewhere. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/ClassScheduler.php` | constant `SCHEDULE_TZ` | Multi-country scheduling can display incorrect times. | Introduce explicit timezone strategy and consistent display conversion. | Medium |
| BUG-014 | MEDIUM | User Model | `preferredTimezone()` is hardcoded to `Asia/Kolkata`. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/User.php` | `preferredTimezone` | User-specific timezone display is not actually implemented. | Use stored timezone or derived country mapping. | Low |
| BUG-015 | MEDIUM | Quiz | Quiz session update path needs stronger role/eligibility audit for coach assignment consistency. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/AdminQuizSessionController.php` | `update` | Session may be assigned inconsistently if validation is bypassed. | Mirror store-side role guards in update path. | Low |
| BUG-016 | MEDIUM | Chat | Chat table can be created dynamically at runtime. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Live/ChatController.php` | `ensureChatTable` | Operational surprises and privilege assumptions in production. | Create via migration only and remove runtime schema mutation. | Medium |
| BUG-017 | MEDIUM | Tournament | Pairing status handling should be checked against DB enum support for cancelled transitions. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/AdminTournamentController.php` | `cancel` | Cancelling tournaments may fail on strict schema if pairing status enum excludes `cancelled`. | Align schema and code values. | Medium |
| BUG-018 | MEDIUM | Reports | Report queries may become heavy without careful eager loading and indexing. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/ReportController.php` | multiple | Slow admin reporting at scale. | Audit eager loading and add indexes. | Low |
| BUG-019 | LOW | UX/Operations | Tournament play healing logic exists because opening clock state can become broken. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/PlayController.php` | `ensureOpeningClocks` | Indicates fragile initial game-state consistency. | Remove root causes and keep repair as fallback only. | Medium |
| BUG-020 | LOW | Tests | Most major modules lack dedicated feature/integration tests. | `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests` | overall | Regressions are likely. | Expand test coverage by workflow. | Low |

## 19. Recommended Implementation Order

### 1. Authentication and Authorization Errors

- lock down admin route group
- add login throttling
- tighten homework and group membership authorization edges

### 2. Group Scheduling

- finish schedule reliability and timezone strategy
- harden schedule regeneration and assignment validation

### 3. Curriculum Chapter Locking

- keep zero-based semantics consistent
- enforce backend-only current chapter access
- verify end-class idempotency and chapter advancement with tests

### 4. Live Classroom Synchronization

- reduce trust in client board state
- tighten question and board validation
- rationalize polling load

### 5. Tournament Engine

- make move legality server-authoritative
- harden clocks and result transitions
- add locking and exact-time orchestration

### 6. Quiz and Homework

- strengthen authorization and test coverage
- validate level/country/session edge cases

### 7. UI Redesign

- only after business logic is stable

### 8. Hostinger Deployment

- formal deploy checklist
- scheduler/cron validation
- asset/version/cache procedure

## 20. Tests Currently Present

Confirmed files:

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature/ExampleTest.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature/GroupSchedulingFlowTest.php`
- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Unit/ExampleTest.php`

Audit notes:

- `GroupSchedulingFlowTest` is the only substantive workflow test verified during this audit.
- It covers the class scheduling and curriculum sequence flow substantially better than other modules are covered.

## 21. Tests Missing

High-priority missing coverage:

- authentication authorization matrix by role
- admin route protection
- coach homework marking ownership
- tournament join/play/result/round advancement
- tournament timeout and draw/resign concurrency
- tournament eligibility modes
- quiz session assignment, join, answer, and timing
- live classroom board authorization and state integrity
- chat and presence authorization
- report query correctness
- Hostinger-sensitive deployment smoke tests

## 22. Files Recommended for the First Fix Sprint

1. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php`
2. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/AuthController.php`
3. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/GroupController.php`
4. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/ClassScheduler.php`
5. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/ClassController.php`
6. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Tournament/PlayController.php`
7. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/TournamentScheduler.php`
8. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/SwissPairing.php`
9. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/HomeworkController.php`
10. `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature/GroupSchedulingFlowTest.php`
11. New future tests under `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature/` for auth, tournaments, quiz, homework, and live classroom

## Assumptions and Confidence Notes

- This audit is based on code inspection of the local project tree.
- Where a behavior was inferred from controller/model/service code but not backed by explicit automated tests, it is described as implemented but not proven.
- Where a module was less extensively inspected than classes and tournaments, that lower confidence is noted.
- The phrase "working" is intentionally avoided unless the implementation is directly verifiable from code and current tests.

## Final Confirmation

Only one new documentation file was created:

- `/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_PLATFORM_AUDIT.md`

No existing source-code files were modified.

# ARCHER IMPLEMENTATION PLAN

## Executive Overview

This document is the complete sprint-based development roadmap for Archer Chess Academy LMS.

It is based on the locked reference documents:

- [ARCHER_CURRENT_SYSTEM_ANALYSIS.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_CURRENT_SYSTEM_ANALYSIS.md)
- [ARCHER_LMS_MASTER_SYSTEM_DESIGN.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_LMS_MASTER_SYSTEM_DESIGN.md)
- [ARCHER_GAP_ANALYSIS.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_GAP_ANALYSIS.md)

## Planning Principles

- Every sprint must be independently deployable.
- Every sprint must leave the system working.
- Existing functionality must not be broken.
- Existing modules must be reused whenever possible.
- High-risk business logic must be stabilized before dependent modules are expanded.
- Reports and analytics must be built only after source workflows are trustworthy.

## Sprint 1

### Sprint Number

- `Sprint 1`

### Sprint Name

- `Project Cleanup`

### Objective

- Stabilize the codebase foundation before module expansion

### Modules Included

- project structure
- schema alignment review
- legacy artifact review
- permission foundation review

### Files Likely to Change

- `routes/web.php`
- `bootstrap/app.php`
- `app/Http/Middleware/*`
- `app/Models/*`
- `app/Support/*`
- `resources/views/layouts/*`
- malformed or unused view directories if formally cleaned

### Database Changes

- schema alignment only if required after verification
- no feature tables yet unless drift must be corrected

### Routes

- remove accidental inconsistencies only
- keep route names backward compatible

### Controllers

- minimal cleanup only where required for stability

### Models

- compatibility cleanup
- relationship verification

### Views

- no redesign
- structural cleanup only if necessary

### JavaScript

- no feature expansion
- only cleanup if broken references are found

### Policies

- define policy rollout approach

### Middleware

- verify role middleware and active-game redirect logic

### Validation

- identify high-risk missing validation paths

### Testing

- baseline regression suite
- smoke tests for authentication, dashboards, groups, tournaments

### Estimated Risk

- `Medium`

### Dependencies

- none

### Prerequisites

- full codebase audit
- schema verification against live and local environments

### Deliverables

- cleaned project baseline
- confirmed route/middleware map
- confirmed schema drift list
- prioritized stabilization backlog

### Acceptance Criteria

- application boots cleanly
- core modules still load
- no production-facing feature regressions introduced

### Regression Tests

- login by role
- dashboard access by role
- core group pages
- tournament pages
- live class listing pages

### Manual Testing Checklist

- login as admin, staff, coach, student
- open dashboards
- open groups page
- open tournament index and detail
- verify no fatal errors in common navigation

### Known Risks

- hidden schema drift
- legacy file clutter masking real issues

### Rollback Plan

- revert cleanup commit set
- restore previous route and middleware registrations

### Definition of Done

- project baseline is stable and documented
- regression suite passes

## Sprint 2

### Sprint Number

- `Sprint 2`

### Sprint Name

- `Dashboard`

### Objective

- standardize dashboard behavior without redesigning the whole UI

### Modules Included

- admin dashboard
- staff dashboard
- coach dashboard
- student dashboard

### Files Likely to Change

- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Coach/DashboardController.php`
- `app/Http/Controllers/Student/DashboardController.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/staff/dashboard.blade.php`
- `resources/views/coach/dashboard.blade.php`
- `resources/views/student/dashboard.blade.php`
- shared dashboard partials

### Database Changes

- none expected

### Routes

- existing dashboard routes reused

### Controllers

- extract duplicated widget/query logic where safe

### Models

- add safe query scopes only if useful

### Views

- align data blocks and missing-state handling

### JavaScript

- minimal, only if dashboard widgets require it

### Policies

- dashboard visibility rules clarified by role

### Middleware

- existing role middleware reused

### Validation

- not a major driver for this sprint

### Testing

- dashboard feature tests
- role-access tests

### Estimated Risk

- `Low`

### Dependencies

- Sprint 1 cleanup baseline

### Prerequisites

- stable dashboard queries identified

### Deliverables

- role dashboards working consistently
- reduced query duplication

### Acceptance Criteria

- each role sees only intended dashboard data
- no broken widgets

### Regression Tests

- dashboard access
- widget data visibility by role

### Manual Testing Checklist

- compare dashboard counts to actual records
- verify coach and student next/live class cards
- verify staff/admin summaries

### Known Risks

- N+1 query regressions
- inconsistent widget filtering

### Rollback Plan

- revert dashboard controller/view changes

### Definition of Done

- dashboards load correctly for all roles
- no role leakage

## Sprint 3

### Sprint Number

- `Sprint 3`

### Sprint Name

- `Users`

### Objective

- harden user administration and role-safe CRUD behavior

### Modules Included

- students
- staff
- coaches
- import/export
- user lifecycle rules

### Files Likely to Change

- `app/Http/Controllers/Admin/UserController.php`
- `app/Models/User.php`
- `resources/views/admin/users/*`
- staff user-management views if reused
- tests for user management

### Database Changes

- optional indexes or lifecycle columns only if justified

### Routes

- reuse current admin/staff user routes

### Controllers

- reduce controller sprawl if safe

### Models

- user scopes and guards

### Views

- improve consistency of create/edit/list flows

### JavaScript

- only for import/export UX if needed

### Policies

- user management policies

### Middleware

- existing role middleware retained

### Validation

- centralize repeated validation rules where useful

### Testing

- CRUD tests
- import/export tests
- unauthorized access tests

### Estimated Risk

- `Medium`

### Dependencies

- Sprint 1 baseline

### Prerequisites

- permission map for user operations

### Deliverables

- safe user management
- role-restricted access
- stable import/export behavior

### Acceptance Criteria

- admin/staff permissions behave exactly as intended
- user creation/edit/import flows are reliable

### Regression Tests

- login still works
- student/staff/coach creation and edit
- import/export paths

### Manual Testing Checklist

- create each user type
- edit each user type
- import sample students
- export students
- verify inactive users cannot log in if intended

### Known Risks

- mass-assignment mistakes
- import logic regressions

### Rollback Plan

- revert user controller/model/view changes

### Definition of Done

- user module is stable, role-safe, and regression-tested

## Sprint 4

### Sprint Number

- `Sprint 4`

### Sprint Name

- `Groups`

### Objective

- stabilize group creation, update, assignment, and schedule integrity

### Modules Included

- groups
- schedules
- coach assignment
- student assignment

### Files Likely to Change

- `app/Http/Controllers/Admin/GroupController.php`
- `app/Models/Group.php`
- `app/Models/GroupSchedule.php`
- `app/Support/ClassScheduler.php`
- group views under `resources/views/admin/groups/*`
- shared staff group views if applicable

### Database Changes

- possible index improvements
- no destructive changes

### Routes

- existing group routes reused

### Controllers

- extract schedule normalization or assignment logic where needed

### Models

- group helpers/scopes

### Views

- group create/edit/show consistency

### JavaScript

- weekly schedule UI behavior if needed

### Policies

- group management authorization

### Middleware

- role middleware reused

### Validation

- schedule conflicts
- coach/student assignment validity

### Testing

- group create/update feature tests
- schedule regeneration tests
- authorization tests

### Estimated Risk

- `High`

### Dependencies

- Sprints 1 and 3

### Prerequisites

- user-role rules stable
- schema integrity verified

### Deliverables

- reliable group setup flow
- safe schedule edits
- preserved class history

### Acceptance Criteria

- group edits do not break future scheduling or history
- coach/students remain correctly assigned

### Regression Tests

- create group
- update group
- add/remove students
- change coach
- resync schedules

### Manual Testing Checklist

- create new group
- assign coach and students
- add multiple weekly schedule rows
- edit schedule
- verify future class list remains sensible

### Known Risks

- schedule resync may corrupt future classes if not handled carefully

### Rollback Plan

- revert group/scheduler changes
- restore pre-sprint schedule behavior

### Definition of Done

- group workflows are reliable and independently deployable

## Sprint 5

### Sprint Number

- `Sprint 5`

### Sprint Name

- `Curriculum`

### Objective

- enforce chapter sequencing and progression rules across the platform

### Modules Included

- chapter progression
- chapter locking
- completion history
- review history rules

### Files Likely to Change

- `app/Support/GroupChapterProgressionService.php`
- `app/Models/Group.php`
- `app/Models/Chapter.php`
- `app/Http/Controllers/Coach/ClassController.php`
- group show views
- coach class-related curriculum views

### Database Changes

- indexes or constraints only if justified

### Routes

- existing routes reused

### Controllers

- standardize chapter-access checks

### Models

- sequence helper consistency

### Views

- current/review/future chapter separation if needed

### JavaScript

- no future chapter data leakage

### Policies

- chapter-access rules for coach/student contexts

### Middleware

- not primary

### Validation

- exact chapter equality rules
- no skipping

### Testing

- progression tests
- duplicate completion idempotency tests
- future chapter blocking tests

### Estimated Risk

- `High`

### Dependencies

- Sprint 4 groups stability

### Prerequisites

- verified current pointer semantics

### Deliverables

- universal chapter-rule enforcement
- preserved completion history

### Acceptance Criteria

- future chapters cannot be accessed improperly
- duplicate end flows do not advance twice

### Regression Tests

- current chapter completion
- chapter advancement
- no-skip behavior
- review-only access to completed items

### Manual Testing Checklist

- start class on current chapter
- end class
- verify next chapter unlock
- verify future chapters stay blocked

### Known Risks

- off-by-one sequence bugs

### Rollback Plan

- revert progression service/controller changes

### Definition of Done

- curriculum rules are backend-safe across all entrypoints

## Sprint 6

### Sprint Number

- `Sprint 6`

### Sprint Name

- `Puzzle Library`

### Objective

- stabilize and clarify chapter/PGN library management

### Modules Included

- admin library
- coach database access
- PGN upload/download/replace/merge

### Files Likely to Change

- `app/Http/Controllers/Admin/LibraryController.php`
- `app/Http/Controllers/Coach/DatabaseController.php`
- `app/Models/Chapter.php`
- `app/Models/PgnFolder.php`
- library/database views

### Database Changes

- possible legacy cleanup planning
- optional indexes

### Routes

- existing library/database routes reused

### Controllers

- clarify responsibilities

### Models

- deprecate legacy concepts safely if approved

### Views

- keep UI stable while removing ambiguity

### JavaScript

- only upload/preview behaviors if needed

### Policies

- upload/edit/read permissions

### Middleware

- current role routing reused

### Validation

- PGN content and metadata validation

### Testing

- upload/download tests
- role access tests

### Estimated Risk

- `Medium`

### Dependencies

- Sprint 5 curriculum rules

### Prerequisites

- final decision on legacy folder behavior

### Deliverables

- stable library flows
- coach read access rules clarified

### Acceptance Criteria

- admin can manage chapter PGNs safely
- coach only sees intended content

### Regression Tests

- upload chapter
- replace/merge
- coach view

### Manual Testing Checklist

- upload PGN
- verify chapter listing
- download PGN
- verify coach database access

### Known Risks

- legacy folder remnants may cause confusion

### Rollback Plan

- revert library/controller/view changes

### Definition of Done

- puzzle library workflows are stable and role-safe

## Sprint 7

### Sprint Number

- `Sprint 7`

### Sprint Name

- `Quiz Bank`

### Objective

- harden quiz-bank authoring and management

### Modules Included

- quiz creation
- quiz editing
- quiz listing
- quiz metadata rules

### Files Likely to Change

- `app/Http/Controllers/Quiz/AdminQuizController.php`
- `app/Models/Quiz.php`
- `resources/views/quizzes/*`

### Database Changes

- none expected beyond optional indexes

### Routes

- existing quiz bank routes reused

### Controllers

- reduce direct controller complexity

### Models

- quiz scopes/helpers

### Views

- consistent authoring flows

### JavaScript

- only if question builder behavior needs support

### Policies

- quiz bank management permissions

### Middleware

- existing role middleware reused

### Validation

- quiz content validation

### Testing

- quiz CRUD
- permission tests

### Estimated Risk

- `Low`

### Dependencies

- Sprint 3 users/permissions baseline

### Prerequisites

- module ownership clarified

### Deliverables

- stable quiz bank administration

### Acceptance Criteria

- quiz authoring is predictable and role-safe

### Regression Tests

- create/edit/delete if supported
- view listing

### Manual Testing Checklist

- create quiz
- edit quiz
- verify visibility

### Known Risks

- validation gaps in question structures

### Rollback Plan

- revert quiz bank changes

### Definition of Done

- quiz bank module is stable and deployable

## Sprint 8

### Sprint Number

- `Sprint 8`

### Sprint Name

- `Live Classes`

### Objective

- harden class start/join/end workflows and live classroom synchronization

### Modules Included

- coach class operations
- student join
- board sync
- chat
- presence
- leaderboard
- class completion flow

### Files Likely to Change

- `app/Http/Controllers/Coach/ClassController.php`
- `app/Http/Controllers/Student/ClassController.php`
- `app/Http/Controllers/Live/*`
- `app/Models/LiveClass.php`
- `app/Models/ClassParticipant.php`
- coach/student class views
- classroom JS embedded in Blade pages

### Database Changes

- possible indexes for class queries

### Routes

- existing class/live routes reused

### Controllers

- move multi-step workflows toward services

### Models

- class state helpers

### Views

- no redesign
- stabilize operational states and errors

### JavaScript

- board polling/sync cleanup
- presence/chat consistency

### Policies

- class start/join/end authorization

### Middleware

- preserve route role safety

### Validation

- class timing
- membership
- state transition rules

### Testing

- start/join/end feature tests
- authorization tests
- duplicate request tests

### Estimated Risk

- `Very High`

### Dependencies

- Sprints 4 and 5

### Prerequisites

- group and curriculum rules stable

### Deliverables

- reliable live classroom lifecycle

### Acceptance Criteria

- assigned coach can start within allowed window
- assigned students can join valid classes
- class end is idempotent and safe

### Regression Tests

- coach start timing
- student join authorization
- chat/presence endpoints
- class completion advancement

### Manual Testing Checklist

- start class as coach
- join as student
- verify board state changes
- verify chat
- end class
- verify post-class behavior

### Known Risks

- live synchronization regressions
- duplicate requests affecting state

### Rollback Plan

- revert class/live controller and view changes

### Definition of Done

- live classroom remains stable across main teaching flows

## Sprint 9

### Sprint Number

- `Sprint 9`

### Sprint Name

- `Quiz Sessions`

### Objective

- stabilize scheduled quiz-session lifecycle and live participation

### Modules Included

- session scheduling
- coach execution
- student join/answer
- leaderboard/result flow

### Files Likely to Change

- `app/Http/Controllers/Quiz/AdminQuizSessionController.php`
- `app/Http/Controllers/Quiz/CoachQuizSessionController.php`
- `app/Http/Controllers/Quiz/StudentQuizSessionController.php`
- `app/Models/QuizSession*.php`
- `resources/views/quiz-sessions/*`

### Database Changes

- optional indexes only

### Routes

- existing quiz-session routes reused

### Controllers

- session lifecycle cleanup

### Models

- status helpers

### Views

- improve missing-state and status handling

### JavaScript

- polling consistency

### Policies

- schedule/run/join permissions

### Middleware

- role middleware reused

### Validation

- eligibility, schedule, duplicate-answer rules

### Testing

- session creation/run/join/answer tests

### Estimated Risk

- `High`

### Dependencies

- Sprint 7 quiz bank

### Prerequisites

- quiz content stable

### Deliverables

- reliable quiz-session execution flow

### Acceptance Criteria

- eligible students can join and answer
- unauthorized users cannot interfere

### Regression Tests

- session scheduling
- coach start/run
- student participation
- leaderboard end state

### Manual Testing Checklist

- create session
- run as coach
- join as student
- submit answers
- verify results

### Known Risks

- live-state polling drift

### Rollback Plan

- revert quiz session changes

### Definition of Done

- quiz session lifecycle is stable and deployable

## Sprint 10

### Sprint Number

- `Sprint 10`

### Sprint Name

- `Tournament Improvements`

### Objective

- harden tournament lifecycle, permissions, and live-play reliability

### Modules Included

- tournament CRUD
- registration
- audience eligibility
- round generation
- pairings
- standings
- live game

### Files Likely to Change

- `app/Http/Controllers/Tournament/AdminTournamentController.php`
- `app/Http/Controllers/Tournament/StudentTournamentController.php`
- `app/Http/Controllers/Tournament/PlayController.php`
- `app/Http/Controllers/Tournament/SpectateController.php`
- `app/Support/TournamentScheduler.php`
- `app/Support/SwissPairing.php`
- `app/Models/Tournament*.php`
- `resources/views/tournaments/*`

### Database Changes

- possible index improvements
- optional audit/support columns if justified

### Routes

- existing tournament routes reused

### Controllers

- extract lifecycle logic where safe

### Models

- status and eligibility helpers

### Views

- no redesign
- stabilize operational states

### JavaScript

- live play polling/clock handling if applicable

### Policies

- manage/view/join/play permissions

### Middleware

- verify active-game redirect behavior

### Validation

- eligibility
- time control
- state transitions

### Testing

- tournament lifecycle tests
- play authorization
- standings/round progression

### Estimated Risk

- `Very High`

### Dependencies

- Sprint 1 baseline
- Sprint 3 permissions

### Prerequisites

- live environment scheduler strategy confirmed

### Deliverables

- safer tournament workflow
- clearer role permissions

### Acceptance Criteria

- tournaments can be created, joined, progressed, and completed reliably

### Regression Tests

- create/edit tournaments
- join/leave
- auto-start/round progression
- play moves
- standings

### Manual Testing Checklist

- create tournament
- register students
- start round
- play game
- verify standings and next pairing

### Known Risks

- scheduler timing issues
- complex live state regressions

### Rollback Plan

- revert tournament controller/support/model/view changes

### Definition of Done

- tournament lifecycle is stable enough for production progression

## Sprint 11

### Sprint Number

- `Sprint 11`

### Sprint Name

- `Homework Automation`

### Objective

- stabilize homework generation, submission, and marking workflows

### Modules Included

- auto-created homework
- student submissions
- coach/admin marking

### Files Likely to Change

- `app/Http/Controllers/Admin/HomeworkController.php`
- `app/Http/Controllers/Coach/HomeworkController.php`
- `app/Http/Controllers/Student/HomeworkController.php`
- `app/Models/Homework.php`
- `app/Models/HomeworkSubmission.php`
- homework views

### Database Changes

- possible indexes
- no major schema changes expected

### Routes

- existing homework routes reused

### Controllers

- deduplicate mark logic

### Models

- status helpers

### Views

- consistency only

### JavaScript

- minimal

### Policies

- assign/view/mark permissions

### Middleware

- current role routing reused

### Validation

- submission and marking rules

### Testing

- homework creation/submission/marking tests

### Estimated Risk

- `Medium`

### Dependencies

- Sprint 8 live classes

### Prerequisites

- class end workflow stable

### Deliverables

- reliable homework downstream flow

### Acceptance Criteria

- class-linked homework generates once
- submission and marking behave correctly

### Regression Tests

- auto-create on class end
- submit as student
- mark as coach/admin

### Manual Testing Checklist

- end class
- verify homework generated
- submit homework
- mark homework

### Known Risks

- duplicate generation from repeated class end calls

### Rollback Plan

- revert homework controller/model/view changes

### Definition of Done

- homework automation is stable and role-safe

## Sprint 12

### Sprint Number

- `Sprint 12`

### Sprint Name

- `Reports`

### Objective

- build first formal reporting module

### Modules Included

- student reports
- coach reports
- group reports
- class reports
- homework reports
- quiz reports
- tournament reports

### Files Likely to Change

- new report controllers
- new report views
- query or report services
- route additions under role namespaces

### Database Changes

- optional report support indexes
- possible summary tables if required

### Routes

- add report route groups

### Controllers

- new reporting controllers

### Models

- read/query scopes only where useful

### Views

- new report pages

### JavaScript

- filters/export helpers if needed

### Policies

- report visibility by role

### Middleware

- existing role middleware plus report permissions

### Validation

- report filter validation

### Testing

- report access tests
- data correctness tests

### Estimated Risk

- `High`

### Dependencies

- Sprints 4 through 11 stable

### Prerequisites

- source workflows must be trusted

### Deliverables

- first production-ready reporting layer

### Acceptance Criteria

- reports show correct filtered data
- users cannot view unauthorized reports

### Regression Tests

- report access by role
- sample data correctness

### Manual Testing Checklist

- open each report type
- filter by date/group/user where applicable
- confirm totals match operational data

### Known Risks

- inaccurate reports if upstream modules still drift

### Rollback Plan

- remove report routes/controllers/views

### Definition of Done

- reports module is usable and role-safe

## Sprint 13

### Sprint Number

- `Sprint 13`

### Sprint Name

- `Analytics`

### Objective

- add academy-wide operational and performance analytics

### Modules Included

- KPI dashboards
- performance trends
- summary metrics

### Files Likely to Change

- analytics controllers/services
- analytics views
- dashboard integrations

### Database Changes

- optional aggregate tables or caches if needed

### Routes

- add analytics routes

### Controllers

- new analytics controllers

### Models

- metric scopes/helpers only if justified

### Views

- analytics pages/widgets

### JavaScript

- charting/filter scripts if introduced

### Policies

- analytics visibility permissions

### Middleware

- role access

### Validation

- analytics filters

### Testing

- access tests
- metric integrity spot checks

### Estimated Risk

- `Medium`

### Dependencies

- Sprint 12 reports

### Prerequisites

- reliable reporting data

### Deliverables

- analytics module and role-safe visibility

### Acceptance Criteria

- KPIs are consistent with report data

### Regression Tests

- analytics route access
- metric output checks

### Manual Testing Checklist

- open analytics pages
- compare KPIs to reports

### Known Risks

- metric-definition inconsistencies

### Rollback Plan

- revert analytics routes/controllers/views

### Definition of Done

- analytics outputs are consistent and stable

## Sprint 14

### Sprint Number

- `Sprint 14`

### Sprint Name

- `Settings`

### Objective

- formalize platform settings and operational configuration

### Modules Included

- system settings
- academy defaults
- module-level operational settings

### Files Likely to Change

- new settings controllers/views
- config integration points
- possible model/service for settings

### Database Changes

- add settings persistence only if required

### Routes

- add settings routes

### Controllers

- new settings controllers

### Models

- settings model only if persistence is implemented

### Views

- settings pages

### JavaScript

- minimal

### Policies

- admin-only or restricted management policies

### Middleware

- role access enforcement

### Validation

- configuration validation

### Testing

- settings access and persistence tests

### Estimated Risk

- `Medium`

### Dependencies

- Sprint 1 baseline

### Prerequisites

- clear list of which settings belong in database vs config

### Deliverables

- formal settings module

### Acceptance Criteria

- approved settings can be changed safely without code edits

### Regression Tests

- settings access
- persistence
- downstream effect verification

### Manual Testing Checklist

- open settings page
- update allowed setting
- verify expected effect

### Known Risks

- over-centralizing settings that should remain code-configured

### Rollback Plan

- revert settings routes/controllers/views and persistence layer

### Definition of Done

- settings management is safe and clearly scoped

## Sprint 15

### Sprint Number

- `Sprint 15`

### Sprint Name

- `Performance Optimisation`

### Objective

- improve speed, query efficiency, and operational scalability

### Modules Included

- dashboards
- reports
- live classes
- tournaments
- heavy list pages

### Files Likely to Change

- query-heavy controllers/services
- models/scopes
- caching hooks
- indexes via migrations if approved

### Database Changes

- performance indexes
- optional summary/aggregate support

### Routes

- minimal

### Controllers

- query optimization

### Models

- scopes and eager-loading helpers

### Views

- pagination/filter adjustments if needed

### JavaScript

- polling interval or request optimization if justified

### Policies

- not primary

### Middleware

- optional rate limiting improvements

### Validation

- not primary

### Testing

- regression plus performance checks

### Estimated Risk

- `Medium`

### Dependencies

- most prior feature sprints

### Prerequisites

- performance hotspots measured

### Deliverables

- optimized high-traffic workflows

### Acceptance Criteria

- key pages and workflows are measurably faster

### Regression Tests

- dashboards
- reports
- tournaments
- live classes

### Manual Testing Checklist

- compare page load behavior before/after
- verify no data loss from caching or pagination changes

### Known Risks

- over-optimization causing stale data

### Rollback Plan

- revert optimization-specific changes

### Definition of Done

- performance improvements are measurable and safe

## Sprint 16

### Sprint Number

- `Sprint 16`

### Sprint Name

- `Security Audit`

### Objective

- close remaining authorization, validation, and abuse-prevention gaps

### Modules Included

- policies
- middleware
- validation hardening
- rate limiting
- audit logging planning or implementation

### Files Likely to Change

- policy classes
- auth-related middleware
- controllers with weak authorization
- request validation classes

### Database Changes

- audit logging tables only if approved

### Routes

- add middleware/policy protections where needed

### Controllers

- authorization cleanup

### Models

- policy-related helpers only if needed

### Views

- minor messaging updates only

### JavaScript

- not primary

### Policies

- major focus

### Middleware

- rate limiting and role-safety improvements

### Validation

- comprehensive hardening

### Testing

- security regression tests
- role-denial tests

### Estimated Risk

- `High`

### Dependencies

- module behaviors stable from prior sprints

### Prerequisites

- permission matrix finalized

### Deliverables

- hardened authorization and validation layer

### Acceptance Criteria

- sensitive actions are consistently protected

### Regression Tests

- denied actions by wrong role
- forbidden ownership actions
- invalid payload handling

### Manual Testing Checklist

- try protected routes as wrong roles
- try invalid submissions
- verify CSRF and rate-limited paths behave correctly

### Known Risks

- accidental over-restriction of valid workflows

### Rollback Plan

- revert policy/middleware hardening if valid access is blocked

### Definition of Done

- security posture is materially improved without breaking valid flows

## Sprint 17

### Sprint Number

- `Sprint 17`

### Sprint Name

- `Testing & Bug Fixes`

### Objective

- expand automated coverage and resolve remaining high-priority defects

### Modules Included

- full regression coverage
- bug backlog
- edge-case hardening

### Files Likely to Change

- `tests/Feature/*`
- `tests/Unit/*`
- targeted fixes across touched modules only

### Database Changes

- none expected unless bug fixes require them

### Routes

- only if bugs require correction

### Controllers

- targeted bug fixes only

### Models

- targeted bug fixes only

### Views

- minor bug fixes only

### JavaScript

- targeted client-side bug fixes

### Policies

- fix gaps found during testing

### Middleware

- fix gaps found during testing

### Validation

- expand test coverage for invalid states

### Testing

- major focus of this sprint

### Estimated Risk

- `Medium`

### Dependencies

- all major feature sprints

### Prerequisites

- consolidated bug list and coverage map

### Deliverables

- stronger test suite
- reduced open critical/high bug count

### Acceptance Criteria

- all critical regressions addressed
- target test suite passes reliably

### Regression Tests

- full suite plus manual smoke pass

### Manual Testing Checklist

- core end-to-end flows by role
- bug reproduction and retest

### Known Risks

- bug-fix batching can introduce new regressions

### Rollback Plan

- revert isolated bug-fix commits as needed

### Definition of Done

- priority defects reduced to acceptable pre-release level

## Sprint 18

### Sprint Number

- `Sprint 18`

### Sprint Name

- `Production Readiness`

### Objective

- prepare the system for stable production deployment and handover

### Modules Included

- deployment readiness
- environment configuration
- scheduler/cron readiness
- queue readiness
- backup/rollback readiness
- documentation finalization

### Files Likely to Change

- deployment docs
- environment docs
- queue/scheduler config references
- minor production-safe code/config adjustments if required

### Database Changes

- none expected except final approved migrations already prepared

### Routes

- none expected

### Controllers

- only final critical fixes if required

### Models

- none expected

### Views

- none expected beyond critical fixes

### JavaScript

- none expected beyond critical fixes

### Policies

- final verification only

### Middleware

- final verification only

### Validation

- final verification only

### Testing

- release smoke testing
- UAT checklist

### Estimated Risk

- `Medium`

### Dependencies

- all prior sprints

### Prerequisites

- critical defects resolved
- release candidate built

### Deliverables

- release-ready platform
- deployment checklist
- production ops checklist

### Acceptance Criteria

- system can be deployed with documented steps
- scheduler and queue requirements are clearly defined
- rollback path is documented

### Regression Tests

- full release regression suite

### Manual Testing Checklist

- login by all roles
- core create/update flows
- group scheduling
- live class run
- homework
- quiz session
- tournament
- reports

### Known Risks

- environment-specific production issues

### Rollback Plan

- rollback to previous release tag/database backup using documented release process

### Definition of Done

- production release candidate is approved and operationally supportable

## MASTER_CHECKLIST

- [ ] Project baseline cleaned and documented
- [ ] Route and middleware map verified
- [ ] Schema drift identified and resolved
- [ ] Role permissions reviewed across admin, staff, coach, and student
- [ ] Dashboard module stabilized
- [ ] User management stabilized
- [ ] Import/export flows tested
- [ ] Group create/edit flows stabilized
- [ ] Weekly schedule generation verified
- [ ] Group schedule edits preserve history
- [ ] Coach assignment rules verified
- [ ] Student assignment rules verified
- [ ] Curriculum pointer rules verified
- [ ] Chapter progression is backend enforced
- [ ] Future chapters blocked server-side
- [ ] Duplicate completion requests are idempotent
- [ ] Puzzle library upload/download flows stabilized
- [ ] Coach database/library access restricted correctly
- [ ] Quiz bank authoring stabilized
- [ ] Live class start rules stabilized
- [ ] Live class join authorization stabilized
- [ ] Live class board synchronization verified
- [ ] Live class end flow verified
- [ ] Homework auto-generation verified
- [ ] Homework submission and marking stabilized
- [ ] Quiz session scheduling stabilized
- [ ] Quiz session participation and scoring verified
- [ ] Tournament create/edit permissions verified
- [ ] Tournament registration verified
- [ ] Tournament Swiss pairing verified
- [ ] Tournament live game stability verified
- [ ] Tournament standings and Buchholz verified
- [ ] Reports module implemented
- [ ] Reports validated against source records
- [ ] Analytics module implemented
- [ ] Settings module implemented
- [ ] Performance hotspots optimized
- [ ] High-risk queries indexed
- [ ] Security hardening completed
- [ ] Policy coverage added for sensitive modules
- [ ] Validation gaps closed
- [ ] Rate limiting reviewed for critical endpoints
- [ ] Audit logging strategy implemented or explicitly deferred
- [ ] Automated regression suite expanded
- [ ] Critical and high bugs resolved
- [ ] Production deployment checklist completed
- [ ] Scheduler/cron requirements documented
- [ ] Queue requirements documented
- [ ] Backup and rollback process documented
- [ ] UAT completed
- [ ] Production release approved

No application source-code files were modified while creating this roadmap. This document is documentation-only.

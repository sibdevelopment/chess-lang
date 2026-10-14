# ARCHER GAP ANALYSIS

## 1. Executive Summary

This document compares the current Archer Chess Academy Laravel platform, as documented in [ARCHER_CURRENT_SYSTEM_ANALYSIS.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_CURRENT_SYSTEM_ANALYSIS.md), against the target architecture defined in [ARCHER_LMS_MASTER_SYSTEM_DESIGN.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_LMS_MASTER_SYSTEM_DESIGN.md).

### Overall Project Status

The current platform is already a substantial working LMS rather than an early prototype. Core role-based authentication, users, groups, curriculum sequencing, live classes, homework, quiz sessions, and tournament play are present. However, the implementation remains uneven against the master design because several modules are only partially complete, some business rules are embedded in large controllers, reporting and analytics are missing, and the permission model is still route-centric rather than policy-centric.

### Estimated Status

- Overall completion: `68%`
- Core modules substantially present: authentication, users, groups, curriculum sequencing, live classes, homework, quiz sessions, tournaments
- Core modules still incomplete or missing: reports, analytics, settings formalization, progress reporting, permission centralization, UI standardization
- Technical debt level: `High`
- Delivery risk level: `High`

### Summary Assessment

- The platform can be reused as the implementation base.
- The business core does not need replacement.
- The highest risk is not feature absence alone, but architectural inconsistency:
  - controller-heavy workflows
  - schema drift
  - duplicated authorization logic
  - mixed polling and pseudo-real-time behavior
  - incomplete reporting foundation

## 2. Module-by-Module Comparison

### Dashboard

**Current State**

- Present for admin, staff, coach, and student
- Role-specific widgets already exist
- Data assembly is controller-driven
- No analytics-grade dashboard layer

**Required State**

- Master system expects standardized, role-aware dashboards with operational summaries, quick actions, alerts, and cleaner module alignment

**Gap**

- `Partially Complete`
- Reusable cards, analytics widgets, consistent metric definitions, and centralized dashboard service layer are missing

**Priority**

- `Medium`

**Estimated Complexity**

- `Medium`

**Dependencies**

- reporting queries
- analytics module
- UI component cleanup

**Status Tags**

- Already Complete: role-based entry dashboards
- Partially Complete: widgets and role separation
- Needs Improvement: data consistency, service extraction, visual standardization
- Should be Refactored: dashboard query aggregation logic
- Can Be Reused: existing route structure, role-specific dashboard views

### Users

**Current State**

- Student, staff, and coach management exists
- Import/export exists
- Role-based login exists
- No policy layer
- No full lifecycle management such as archive/delete/profile/settings

**Required State**

- Master system expects complete user administration with clearer lifecycle handling and role-governed operations

**Gap**

- `Partially Complete`
- CRUD is present, but lifecycle completeness and permission centralization are not

**Priority**

- `High`

**Estimated Complexity**

- `Medium`

**Dependencies**

- permission matrix
- policy layer
- audit/reporting requirements

**Status Tags**

- Already Complete: core creation/edit/toggle flows
- Partially Complete: imports, username generation, role-specific management
- Needs Improvement: self-service/profile/reset/audit handling
- Should be Refactored: `Admin\UserController`
- Can Be Reused: user schema, role model, import/export base

### Groups

**Current State**

- Group creation/edit exists
- Coach and student assignment exists
- Weekly schedule exists
- Starting chapter exists
- Future live class generation exists
- Some schema/business drift remains from prior rule changes

**Required State**

- Master system expects group creation to be the authoritative source for recurring classes, curriculum progression, assigned coach/students, and chapter sequencing

**Gap**

- `Partially Complete`
- Core flow exists, but it needs hardening, cleaner service separation, and removal of lingering fields/legacy assumptions

**Priority**

- `Critical`

**Estimated Complexity**

- `High`

**Dependencies**

- scheduling service
- curriculum progression service
- permission centralization
- database cleanup

**Status Tags**

- Already Complete: coach assignment, students, weekly schedule, starting chapter, class generation hooks
- Partially Complete: edit safety, schedule resync semantics, historical preservation
- Needs Improvement: validation clarity, schedule/history integrity, staff ownership logic
- Should be Refactored: `Admin\GroupController`, schedule sync orchestration
- Can Be Reused: `groups`, `group_schedules`, `chapter_group`, `group_chapter_completions`, `ClassScheduler`

### Curriculum

**Current State**

- Chapters are sequenced per group
- Progress pointer exists through `groups.unlocked_chapter_index`
- Completion ledger exists
- Future chapter locking is implemented in backend logic
- No broader curriculum admin module beyond library/group flows

**Required State**

- Master system expects a more formal curriculum structure with strict chapter order, review history, and safe progression semantics across teaching operations

**Gap**

- `Partially Complete`

- Strict sequencing is present, but curriculum administration and visibility are thinner than the master system

**Priority**

- `Critical`

**Estimated Complexity**

- `High`

**Dependencies**

- groups module
- live class completion logic
- coach classroom chapter exposure rules

**Status Tags**

- Already Complete: per-group chapter sequence, locked future chapters, completion history
- Partially Complete: review separation and admin-facing curriculum tooling
- Needs Improvement: clearer indexing conventions and cross-module enforcement
- Should be Refactored: chapter-selection entrypoints across controllers/views
- Can Be Reused: `GroupChapterProgressionService`, `chapter_group`, `group_chapter_completions`

### Puzzle Library

**Current State**

- Chapter PGN upload and viewing exist
- Coach database/library browsing exists
- Legacy folder concepts still remain in schema/code naming
- No clean standalone puzzle entity

**Required State**

- Master system expects a formal puzzle library with searchable, reusable learning content, sequence-aware upload, and clearer separation from curriculum chapters

**Gap**

- `Partially Complete`

- Storage and viewing exist, but domain boundaries are blurred

**Priority**

- `High`

**Estimated Complexity**

- `High`

**Dependencies**

- curriculum standardization
- naming cleanup
- reporting/search requirements

**Status Tags**

- Already Complete: PGN upload/download, level grouping, coach viewing
- Partially Complete: library indexing and structure
- Needs Improvement: terminology, separation of chapter vs puzzle concepts
- Should be Refactored: `Admin\LibraryController`, `Coach\DatabaseController`, legacy folder remnants
- Can Be Reused: `chapters` table, existing chapter upload workflow

### Quiz Bank

**Current State**

- Quiz bank exists
- Session scheduling exists
- Coach run flow exists
- Student answer flow exists
- Polling-based live behavior exists

**Required State**

- Master system expects a stable bank + scheduled session workflow with clear lifecycle, reporting, and stronger live-state abstraction

**Gap**

- `Partially Complete`

- Functional coverage is good, but architecture is controller-heavy and reporting is absent

**Priority**

- `High`

**Estimated Complexity**

- `Medium`

**Dependencies**

- permissions
- reports
- event/polling strategy

**Status Tags**

- Already Complete: quiz bank, session scheduling, coach execution, student participation
- Partially Complete: lifecycle reporting and cleaner orchestration
- Needs Improvement: live-state transport, analytics, anti-cheat/time analysis
- Should be Refactored: quiz session controllers
- Can Be Reused: existing quiz/session tables and views

### Live Classes

**Current State**

- Scheduled classes are generated from group schedules
- Coach start window exists
- Students can join based on group membership
- Chat, presence, board state, questions, and leaderboard exist
- Class end triggers progression and homework

**Required State**

- Master system expects reliable synchronized classroom operation with strict permissions, stable chapter flow, attendance, question flow, reconnection handling, and reporting

**Gap**

- `Partially Complete`

- Broad feature coverage exists, but real-time robustness and architecture lag behind the master design

**Priority**

- `Critical`

**Estimated Complexity**

- `High`

**Dependencies**

- group scheduling
- curriculum progression
- board synchronization strategy
- attendance/reporting foundation

**Status Tags**

- Already Complete: scheduling linkage, coach start rules, student join rules, board persistence, questions, chat, leaderboard
- Partially Complete: reconnection semantics, reporting, transport robustness
- Needs Improvement: service extraction, classroom state consistency, mobile stability
- Should be Refactored: `Coach\ClassController`, classroom JS in Blade views
- Can Be Reused: class models, participant tables, scheduler and progression services

### Quiz Sessions

**Current State**

- Present as part of quiz module
- Coach can run sessions
- Students can join and answer
- Leaderboard exists after session completion

**Required State**

- Master system treats quiz sessions as an operational module with complete scheduling, execution, and results visibility

**Gap**

- `Partially Complete`

**Priority**

- `Medium`

**Estimated Complexity**

- `Medium`

**Dependencies**

- quiz bank
- permissions
- reporting

**Status Tags**

- Already Complete: core scheduled run flow
- Partially Complete: reporting, results administration, operational consistency
- Needs Improvement: lifecycle clarity and result analytics
- Should be Refactored: session orchestration logic
- Can Be Reused: current session models, routes, response tracking

### Tournament

**Current State**

- CRUD exists
- Eligibility modes exist
- Student join exists
- Scheduler-driven pairings and round advancement exist
- Live board, clocks, legal move validation, standings, Buchholz, and spectating exist
- Operational fragility remains around scheduler accuracy and mixed live behavior

**Required State**

- Master system expects reliable Swiss lifecycle management, exact start/end behavior, strong permissions, standings, clock integrity, and tournament reporting

**Gap**

- `Partially Complete`

- Functionality is broad, but implementation hardening and reporting remain incomplete

**Priority**

- `Critical`

**Estimated Complexity**

- `Very High`

**Dependencies**

- scheduler reliability
- permission centralization
- play-state consistency
- reporting/export foundation

**Status Tags**

- Already Complete: creation, registration, pairings, Swiss rounds, standings, live play core
- Partially Complete: admin tooling depth, reporting, moderation, reconnection UX
- Needs Improvement: scheduler guarantees, controller size, live gameplay resilience
- Should be Refactored: `AdminTournamentController`, `PlayController`, tournament schedulers
- Can Be Reused: tournament schema, Swiss logic, pairing records, standings model methods

### Homework

**Current State**

- Homework exists
- It is auto-created at class end
- Students submit
- Coaches/admin mark

**Required State**

- Master system expects homework to be a stable downstream workflow with stronger reporting and progress tracking

**Gap**

- `Partially Complete`

- Core use case exists, analytics/reporting and cleaner service boundaries do not

**Priority**

- `High`

**Estimated Complexity**

- `Medium`

**Dependencies**

- live classes
- reports
- student progress module

**Status Tags**

- Already Complete: create, submit, mark
- Partially Complete: reporting and overdue automation
- Needs Improvement: due-date workflows, reminder handling, mark-flow deduplication
- Should be Refactored: admin/coach homework marking duplication
- Can Be Reused: homework schema and submission flow

### Reports

**Current State**

- Not implemented as a module

**Required State**

- Master system expects reports for students, coaches, groups, classes, tournaments, homework, and quiz sessions

**Gap**

- `Missing`

**Priority**

- `Critical`

**Estimated Complexity**

- `High`

**Dependencies**

- stable source data
- normalized metrics
- permission matrix

**Status Tags**

- Already Complete: none
- Partially Complete: none
- Missing: full module
- Needs Improvement: underlying data extraction readiness
- Should be Refactored: supporting aggregation queries before report UI
- Can Be Reused: existing operational tables as raw report sources

### Analytics

**Current State**

- No dedicated analytics module
- Some dashboard counters exist

**Required State**

- Master system expects academy analytics, trend views, and performance summaries

**Gap**

- `Missing`

**Priority**

- `Medium`

**Estimated Complexity**

- `High`

**Dependencies**

- reports foundation
- stable metrics layer
- UI charting decisions

**Status Tags**

- Already Complete: basic counters only
- Partially Complete: none
- Missing: analytics module
- Needs Improvement: metric definitions and historical aggregation
- Should be Refactored: dashboard data sources before analytics rollout
- Can Be Reused: dashboard counters as seeds for KPIs

### Settings

**Current State**

- No formal settings module documented in the current system
- Some behavior is hard-coded in controllers/services/config

**Required State**

- Master system expects settings as a formal administrative module

**Gap**

- `Missing`

**Priority**

- `Medium`

**Estimated Complexity**

- `Medium`

**Dependencies**

- permission centralization
- configuration audit

**Status Tags**

- Already Complete: none
- Partially Complete: implicit config-only behavior
- Missing: settings module
- Needs Improvement: centralization of operational defaults
- Should be Refactored: hard-coded business defaults spread across code
- Can Be Reused: existing config conventions and admin route grouping

## 3. Database Gap Analysis

### Existing Tables

The current system already has strong operational coverage through these main tables:

- `users`
- `groups`
- `group_student`
- `group_schedules`
- `group_chapter_completions`
- `chapters`
- `chapter_group`
- `live_classes`
- `class_participants`
- `class_chat_messages`
- `class_questions`
- `class_question_attempts`
- `homework`
- `homework_submissions`
- `quizzes`
- `quiz_sessions`
- `quiz_session_quizzes`
- `quiz_session_attendees`
- `quiz_session_responses`
- `tournaments`
- `tournament_groups`
- `tournament_users`
- `tournament_participants`
- `tournament_pairings`
- `student_analyses`

### Missing Tables

Against the master system, the following table families are missing or not formalized:

- report summary tables or materialized reporting tables
- analytics aggregation tables
- settings/configuration persistence tables
- notification/outbox tables if notifications are to become a formal module
- dedicated attendance/report export structures if attendance becomes first-class
- audit trail tables for permission-sensitive actions

### Missing Relationships

- staff ownership or attribution relationships for operational actions are weak or absent
- clearer separation between curriculum entities and puzzle/content entities is absent
- report/analytics entities have no relational layer yet
- settings ownership/scope relationships do not exist

### Missing Indexes

Based on the current analysis, likely missing or unverified indexes include:

- `group_schedules(group_id, day_of_week, start_time)`
- `live_classes(group_id, status, scheduled_for)`
- more composite indexes for dashboard and tournament listing filters
- attendance/presence lookup indexes if class-participant reporting expands

### Foreign Key Issues

- The current analysis identified weak or missing foreign key enforcement in several areas
- Runtime schema drift means actual production and migration expectations may not be perfectly aligned
- Some legacy tables remain without a clearly active domain role

### Normalization Issues

- `pgn_folders` remains although folder workflow is effectively deprecated
- `database_assignments` appears obsolete
- “library”, “database”, “chapter”, and “puzzle” concepts overlap more than they should
- hard-coded defaults in code indicate incomplete normalization of configuration concerns

## 4. Route Gap Analysis

### Existing Routes

The current system already includes broad route coverage for:

- authentication
- role dashboards
- users
- groups
- library/database
- live classes
- homework
- quiz bank
- quiz sessions
- tournaments
- student analysis

### Missing Routes

The master system still requires route groups for:

- reports
- analytics
- settings
- more formal profile/self-service user settings if included in final scope
- any audit, notification, or export modules introduced later

### Duplicate Routes

- Staff functionality largely mirrors admin functionality through reused controllers and parallel route prefixes
- Tournament management exists under multiple role namespaces but reuses major controller paths
- Some route behavior is duplicated structurally rather than abstracted

### Unused or Suspicious Routes

- Current analysis noted structural clutter and historical drift; unused or weakly justified route/controller exposure should be expected around legacy database/library flows
- Student tournament redirect middleware usage should be revalidated against current route registration consistency

## 5. Permission Gap Analysis

### Admin

**Current**

- Broad operational control exists across modules

**Master Requirement**

- Full control remains appropriate, but should be formalized through a consistent permission matrix

**Gap**

- Admin capability exists, but is enforced inconsistently through route middleware plus controller checks rather than centralized policies

### Staff

**Current**

- Staff uses many admin controllers through restricted route groups

**Master Requirement**

- Staff should have explicitly modeled operational permissions rather than implicit admin-controller reuse

**Gap**

- Staff permission boundaries are the weakest role boundary in the current system

### Coach

**Current**

- Coach can run classes, homework, quiz sessions, and own tournaments

**Master Requirement**

- Coach rights should be sharply limited to assigned classes, authorized content, and owned/assigned activities

**Gap**

- Much of this exists functionally, but enforcement is spread across many controllers and should move to a policy/service matrix

### Student

**Current**

- Student access is comparatively well constrained by route and business checks

**Master Requirement**

- Student should only access assigned/joined/eligible learning flows

**Gap**

- Student permissions are better than other roles, but they still depend on distributed controller logic rather than centralized authorization rules

## 6. UI Gap Analysis

### Sidebar

- Current sidebars are role-based and usable
- Master system expects cleaner standardization and tighter alignment between module naming and navigation structure
- The existing sidebar can be reused conceptually, but the implementation should be cleaned up later

### Dashboard

- Current dashboards exist, but they are not yet aligned to a shared component system or analytics-ready layout
- Widget definitions are inconsistent by controller

### Forms

- Core forms exist for users, groups, tournaments, quizzes, and homework
- The master system requires cleaner consistency, fewer legacy fields, and more predictable validation feedback
- Existing forms are functional but inconsistent

### Tables

- List/table pages exist across most operational modules
- Standardized action layouts, filters, exports, and status indicators remain incomplete

### Reports

- Report UI does not exist

### Navigation

- Route-driven navigation works now
- The master system requires formal navigation completeness for reports, analytics, and settings

### Responsive Design

- Current UI carries desktop-first risk because of inline CSS, custom layout shells, and complex live views
- Tournament and live class screens are especially likely to need targeted responsive hardening

### What Must Change

- standardize module naming across sidebars and pages
- reduce inline styling/scripts over time
- introduce consistent form patterns
- introduce report and analytics navigation
- improve live/tournament responsive behavior
- remove legacy visual clutter caused by duplicated/overlapping view structures

## 7. Business Rule Gap Analysis

### Group Scheduling

**Current**

- Implemented through `group_schedules` and `ClassScheduler`
- Recurring generation exists

**Gap**

- Needs stronger guarantees around update safety, history preservation, and clearer separation between future placeholders and historical class records

### Chapter Progression

**Current**

- Pointer-based progression exists and is backed by completion records

**Gap**

- Needs continued strict enforcement across every entrypoint, especially live class exposure and curriculum-facing UI
- Master system expects chapter rules to be universally enforced and clearly represented

### Homework Automation

**Current**

- Homework is auto-created at class end

**Gap**

- Reporting, reminders, and full progress analytics are missing

### Puzzle Assignment

**Current**

- Chapter PGN/library content exists

**Gap**

- Dedicated puzzle assignment and puzzle-domain clarity are incomplete

### Quiz Sessions

**Current**

- Scheduling and participation exist

**Gap**

- Reporting, orchestration separation, and richer live-state management are missing

### Tournament Workflow

**Current**

- Broadly implemented, including Swiss logic and live play

**Gap**

- Hardening, reporting, scheduler reliability, and operational moderation are missing or weak

### Attendance

**Current**

- Presence heartbeat exists

**Gap**

- Dedicated attendance reporting, exports, and operational summaries are missing

### Live Classes

**Current**

- Join/start rules, board sync, chat, and questions exist

**Gap**

- Full synchronization resilience, cleaner service separation, and reporting are incomplete

### Notifications

**Current**

- No formal notification module is documented

**Gap**

- The master system implies more operational communication than the current architecture formally supports

## 8. Recommended Database Changes

List of recommended changes only:

- formalize missing foreign keys where runtime drift currently exists
- verify and add missing composite indexes for schedules, live classes, and tournament filters
- retire or replace deprecated `pgn_folders` usage if the master system no longer needs it
- retire or replace obsolete `database_assignments` if not part of final design
- add settings persistence tables if the settings module becomes formal
- add report/analytics support tables if pre-aggregation is required
- add audit-log tables for high-risk actions
- add attendance summary structures only if reporting cannot be served efficiently from `class_participants`

## 9. Recommended Refactoring

### Controllers

- split very large admin/group/user/tournament/class controllers
- extract orchestration from controllers into dedicated application services

### Services

- formalize service layer for:
  - group lifecycle
  - curriculum progression
  - homework generation
  - quiz session lifecycle
  - tournament lifecycle
  - dashboard aggregation
  - reporting aggregation

### Policies

- introduce Laravel policies or an equivalent centralized permission layer for:
  - users
  - groups
  - classes
  - quizzes
  - homework
  - tournaments
  - reports

### Repositories

- repository layer is optional, but read-heavy reporting queries may benefit from dedicated query classes or repositories

### Models

- reduce business-rule scattering
- clarify overloaded terminology around chapter/library/database/puzzle concepts
- remove schema-compatibility noise once schema is aligned

### Views

- keep current UI structure initially, but gradually extract reusable partials/components
- remove duplicated layouts/pattern drift across admin/staff/coach/student pages

### JavaScript

- reduce inline page scripts
- move live classroom and tournament play logic toward maintainable module boundaries
- standardize polling/event helpers

## 10. Implementation Roadmap

### Sprint 1

**Goal**

- Stabilize high-risk foundations

**Modules**

- authentication/authorization
- groups
- curriculum progression
- schema alignment

**Estimated Risk**

- `High`

**Dependencies**

- current schema verification
- route audit

### Sprint 2

**Goal**

- Harden live operational workflows

**Modules**

- live classes
- homework automation boundaries
- attendance foundations

**Estimated Risk**

- `High`

**Dependencies**

- sprint 1 permission and group fixes

### Sprint 3

**Goal**

- Stabilize quiz and session architecture

**Modules**

- quiz bank
- quiz sessions
- result handling

**Estimated Risk**

- `Medium`

**Dependencies**

- policy layer
- reporting data conventions

### Sprint 4

**Goal**

- Harden tournament lifecycle

**Modules**

- tournament CRUD
- registration
- pairings
- live play
- standings

**Estimated Risk**

- `Very High`

**Dependencies**

- scheduler reliability
- authorization stabilization

### Sprint 5

**Goal**

- Build reporting foundation

**Modules**

- reports
- attendance summaries
- homework summaries
- quiz/tournament summaries

**Estimated Risk**

- `Medium`

**Dependencies**

- stable source-of-truth logic from earlier sprints

### Sprint 6

**Goal**

- Build analytics and settings

**Modules**

- analytics
- settings
- KPI dashboards

**Estimated Risk**

- `Medium`

**Dependencies**

- reports foundation

### Sprint 7

**Goal**

- UI/system cleanup and maintainability pass

**Modules**

- dashboard cleanup
- sidebar/navigation cleanup
- shared components
- responsive fixes

**Estimated Risk**

- `Medium`

**Dependencies**

- module behavior freeze from prior sprints

## 11. Risks

### Major Risks

- schema drift between code, migrations, and live environments
- hidden authorization leaks because staff reuses admin controllers
- fragile real-time behavior due to polling-heavy classroom/tournament flows
- regression risk when extracting logic from large controllers
- reporting inaccuracies if modules are built before source workflows are stabilized
- legacy tables/terminology causing incorrect feature assumptions

### Mitigation Strategies

- verify runtime schema before each major refactor
- centralize permissions before expanding features
- add regression tests before touching class/tournament lifecycle logic
- stabilize business rules before building reports
- treat deprecated tables/features explicitly rather than leaving them ambiguous
- ship refactors in thin slices with feature tests around scheduling, progression, and permissions

## 12. Final Recommendation

The safest implementation strategy is to use the current platform as the base and evolve it in controlled layers rather than attempting a rewrite.

Recommended strategy:

1. Lock down permissions and schema consistency first.
2. Stabilize groups, scheduling, and chapter progression before any UI redesign.
3. Harden live classes and tournament lifecycle before building reports.
4. Build reports and analytics only after source workflows are trustworthy.
5. Standardize UI last, after business logic and permissions are stable.

Overall conclusion:

- The current system is reusable.
- It is not ready for unchecked feature expansion.
- The next phase should prioritize rule safety, permission centralization, and operational reliability over new screens.

No source-code files were modified while preparing this gap analysis. This document is documentation-only and is intended to serve as the implementation blueprint before coding begins.

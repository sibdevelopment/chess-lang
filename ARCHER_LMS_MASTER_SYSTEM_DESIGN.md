# ARCHER LMS MASTER SYSTEM DESIGN

## Section 1. Project Overview

### Project Vision

Archer Chess Academy LMS is a role-based academy operations platform for managing chess training, curriculum delivery, live teaching, assessments, tournaments, homework, reporting, and academy analytics from a single system.

The platform is designed to support daily academy workflows across administration, coaching, student engagement, and long-term performance tracking while preserving strict business rules around scheduling, chapter progression, and access control.

### Objectives

- Centralize academy operations in one Laravel application
- Provide role-specific portals for admin, staff, coach, and student
- Manage structured curriculum progression by level and chapter
- Automatically generate scheduled live classes from weekly group schedules
- Deliver live chess learning experiences with synchronized boards and classroom tools
- Support assessments through quiz sessions and tournaments
- Track homework, class progress, and student performance
- Produce operational reports and academy-wide analytics

### Target Users

- Admin
  Overall platform owner and academy operator
- Staff
  Operational support user managing day-to-day administration
- Coach
  Teaching user delivering classes, quizzes, and tournaments
- Student
  Learner consuming classes, homework, quizzes, tournaments, and progress tools

### System Architecture

The Archer LMS is a modular Laravel web application built around role-based route groups and server-side rendered Blade interfaces.

Architectural principles:

- One shared core application
- Strict role-based access
- Business logic enforced server-side
- Scheduled background processing for recurring operations
- Polling-based real-time classroom and tournament updates, with event support where available
- Curriculum and class progression driven by database state, not only UI controls

High-level architectural layers:

- Presentation Layer
  Blade views, shared layout, sidebars, page-specific JavaScript
- Application Layer
  Controllers orchestrating module workflows
- Domain Support Layer
  Scheduling and progression services
- Persistence Layer
  Eloquent models and relational database tables
- Background Processing Layer
  Artisan commands and scheduler-driven automation

### Technology Stack

- Backend Framework: Laravel 11
- Language: PHP 8.2+
- Database: MySQL-compatible relational database
- Frontend Rendering: Blade templates
- Frontend Build: Vite
- Styling: custom CSS and Blade-embedded styles
- Authentication: Laravel session-based authentication
- Scheduling: Laravel scheduler and Artisan commands
- Chess Rules Engine: `p-chess/chess`
- Optional real-time package: Laravel Reverb

## Section 2. User Roles

### Admin

Purpose:

- Full control over academy operations, modules, users, curriculum, groups, classes, quizzes, tournaments, reports, analytics, and settings

Permissions:

- Access every module
- Create, edit, activate, and monitor users
- Create and manage groups
- Manage curriculum, puzzle library, and quiz bank
- View and end live classes where permitted by business logic
- Create and manage tournaments
- View all reports and analytics
- Manage settings

Restrictions:

- Must still follow core business rules, such as chapter progression rules and tournament lifecycle rules

Navigation:

- Full sidebar

Dashboard:

- Global operational dashboard with academy-wide metrics, urgent items, live activity, and quick actions

### Staff

Purpose:

- Support admin in operational workflows without full system ownership

Permissions:

- Manage student and selected coach/staff operational records
- Create and manage groups
- Schedule quiz sessions
- Create and manage tournaments where permitted
- View operational reports assigned to staff scope

Restrictions:

- No admin-only settings ownership
- No unrestricted system reconfiguration
- No business-rule override capability

Navigation:

- Operational subset of sidebar

Dashboard:

- Operations-first dashboard showing groups, today’s classes, unassigned students, missed sessions, and upcoming tournaments

### Coach

Purpose:

- Deliver learning sessions and interact with assigned students

Permissions:

- View own dashboard and assigned groups
- Start and end assigned classes
- Operate live classroom tools
- Review and mark homework for own students
- Access permitted curriculum/puzzle content
- Conduct assigned quiz sessions
- Create and manage only own tournaments if enabled by business rules

Restrictions:

- Cannot access admin-only user management or global settings
- Cannot operate another coach’s class, homework, or groups
- Cannot skip future curriculum chapters

Navigation:

- Teaching-focused sidebar

Dashboard:

- Live class priority dashboard with next class, start action, current chapter, upcoming sessions, homework review, and recent teaching activity

### Student

Purpose:

- Attend classes, complete homework, join quiz sessions, play tournaments, and view progress

Permissions:

- View own dashboard
- Join assigned live classes
- Access homework
- Join eligible quiz sessions
- Join eligible tournaments
- Use analysis/study tools if enabled

Restrictions:

- Cannot access other students’ or coaches’ data
- Cannot join unrelated groups or classes
- Cannot bypass tournament or class authorization rules

Navigation:

- Learning-focused sidebar

Dashboard:

- Next-class and progress-oriented dashboard with join action, current chapter, homework status, and tournament visibility

## Section 3. Complete Sidebar

The official sidebar structure is:

- Dashboard
- Users
  - Coach
  - Staff
  - Student
- Operations
  - Groups
  - Curriculum
  - Puzzle Library
  - Quiz Bank
- Activities
  - Live Classes
  - Quiz Sessions
  - Tournament
- Reports
  - Homework
  - Student
  - Coach
  - Group
  - Class
  - Tournament
  - Quiz Session
- Analytics
- Settings

### Menu Definitions

#### Dashboard

Role-specific landing page showing actionable summaries, alerts, and shortcuts.

#### Users

Central module for people records.

Submenus:

- Coach
  Manage coach accounts
- Staff
  Manage staff accounts
- Student
  Manage student accounts

#### Operations

Operational configuration and academic content.

Submenus:

- Groups
  Group setup, coach assignment, students, schedules, and chapter progression
- Curriculum
  Level and chapter sequence management
- Puzzle Library
  Puzzle/PGN/FEN content repository and assignment logic
- Quiz Bank
  Question repository for quiz sessions

#### Activities

Time-based academy activities and live delivery modules.

Submenus:

- Live Classes
  Scheduled and live classroom operation
- Quiz Sessions
  Scheduled and live quiz delivery
- Tournament
  Tournament scheduling and execution

#### Reports

Operational and academic reporting.

Submenus:

- Homework
- Student
- Coach
- Group
- Class
- Tournament
- Quiz Session

#### Analytics

KPI and trend visualizations across academy performance.

#### Settings

System-level configuration, standards, defaults, and non-operational administration.

## Section 4. Dashboard

The dashboard must be role-aware and show only actionable, role-relevant information.

### Widgets

- Total active users relevant to role
- Live classes
- Today’s classes
- Upcoming tournaments
- Pending homework
- Notifications
- Recent activity

### Statistics

- Admin: global academy counts
- Staff: operational counts
- Coach: own classes, own homework review, assigned groups
- Student: own homework, own classes, own progress

### Cards

- Next class
- Live now
- Pending review
- Upcoming tournament
- Recent updates

### Quick Actions

- Create Group
- Add Student
- Create Tournament
- Manage Curriculum
- Start Class
- Join Class
- Review Homework

### Notifications

- Missed classes
- Unassigned students
- Groups without coach
- Quiz session status
- Tournament status

### Upcoming Classes

- Admin/Staff: academy-wide operational view
- Coach: own next scheduled classes
- Student: own assigned classes only

### Today’s Tournament

- Show tournaments relevant to role and eligibility

### Pending Homework

- Coach: submissions needing review
- Student: pending assigned work
- Admin/Staff: operational overview/report link

## Section 5. Users Module

### Coach

- Create coach
- Edit coach
- Activate or deactivate coach

### Staff

- Create staff
- Edit staff
- Activate or deactivate staff

### Student

- Create student
- Edit student
- Activate or deactivate student
- Import and export student records

### Validation

- Username must be unique
- Role must be valid
- Password rules must be enforced on create and reset/update flows
- Student assignment fields must reference valid student records

### Permissions

- Admin has full user management
- Staff has only operationally allowed user management
- Coach and student cannot access user-management screens

### Business Rules

- Only active users can log in
- Role determines dashboard and navigation
- Student usernames and bulk-import behavior must follow academy conventions where configured
- Staff and coach records cannot be treated as students in operational assignment flows

## Section 6. Groups Module

### Create Group

A group must support:

- Group name
- Level
- Assigned coach
- Assigned students
- Starting chapter
- One or more weekly schedule rows

### Edit Group

Editing a group may update:

- name
- level
- coach
- students
- schedule
- starting chapter, subject to progression rules

### Assign Coach

- Exactly one coach per group at a time
- Coach must be a valid active coach user

### Assign Students

- Only valid student users can be assigned
- Duplicate assignments must be prevented

### Weekly Schedule

- One or more day/time schedule rows
- Overlapping rows must be rejected
- Schedule rows are the source for automatic class generation

### Attendance

- Attendance is derived from class participation/presence during live classes
- Group-level attendance reporting must aggregate class participation history

### Automatic Class Generation

- Admin and staff do not manually create recurring class rows
- Scheduled classes are generated automatically from group schedule rules
- Future class rows are regenerated when group schedule changes

### Class Cancellation Rules

- If coach does not start class within allowed late window, class is cancelled
- Cancelled class must not advance curriculum
- Future scheduled rows must remain aligned to current chapter pointer

### Chapter Progression

- Group progression is pointer-based
- Current chapter is determined by the group’s unlocked chapter index
- Ending a valid current live class advances the pointer once
- Completed chapters become history
- Future chapters remain locked

### Validation

- Valid level
- Valid coach
- Valid students
- Valid chapter for level
- Valid schedule rows

### Edge Cases

- Duplicate end-class request
- Cancelled scheduled class
- Schedule edit after past classes exist
- Group without coach
- Group inactive
- Curriculum complete

## Section 7. Curriculum Module

### Levels

- Beginner
- Intermediate
- Advanced

### Chapters

Each chapter represents a curriculum unit with PGN-backed teaching content.

### Sequences

- Chapters are ordered
- Sequence order drives group progression
- Group-specific chapter order must be explicit and stable

### Upload

- Admin uploads chapter content

### Download

- Admin and permitted roles may download allowed chapter content

### Locking

- Future chapters must be locked server-side
- Current chapter is unlocked
- Completed chapters may be shown separately for revision/history

### Progress

- Progress is tracked per group, not globally per student

### Business Rules

- Group progression follows chapter sequence strictly
- A group cannot skip future chapters
- Chapter progression occurs only after valid class completion

## Section 8. Puzzle Library

### Upload PGN

- Allow PGN upload into the puzzle/content library

### Upload FEN

- Allow direct position-based content support where required

### Merge

- Permit merge workflows where business rules allow combining uploaded content

### Edit

- Edit metadata and content safely

### Delete

- Delete only when not blocked by existing academic dependencies

### Download

- Export/download puzzle content where permitted

### Categories

- Content must support organizational classification

### Difficulty

- Difficulty classification must be supported

### Tags

- Tagging must support search and assignment logic

### Search

- Search by title, level, tags, category, difficulty, and sequence where applicable

### Auto Assignment

- Puzzle Library automatically assigns puzzles to students of the same level

### Bulk Import

- Support structured bulk import for content loading

### Business Rules

- Only authorized roles can create or modify library content
- Future classroom access must respect curriculum and permission rules
- Content linked to live usage or history cannot be deleted unsafely

## Section 9. Quiz Bank

### Upload Quiz

- Create quiz questions in the bank

### Edit Quiz

- Update question, options, category, difficulty, and timing

### Delete Quiz

- Delete only where history and dependency rules permit

### Categories

- Category-based organization is required

### Weekly Quiz

- Weekly sequencing or grouping must be supported

### Search

- Search by week, category, type, text, and difficulty

### Download

- Export capability should exist where reporting or content portability requires it

### Difficulty

- Difficulty metadata should be supported

### Business Rules

- Quiz Sessions are created from Quiz Bank content
- Bank content is reusable
- Session execution must snapshot the session queue where required

## Section 10. Live Classes

### Purpose

Deliver synchronized chess instruction to students through scheduled, coach-led live sessions.

### Workflow

- Group schedule defines future class opportunities
- Scheduled class becomes startable within allowed window
- Coach starts class
- Students join only if assigned and class is live
- Coach delivers content, board updates, questions, and chat
- Coach ends class
- Homework is generated
- Curriculum pointer advances if completion is valid

### Create

- Recurring live classes are generated automatically from group schedule

### Assign Coach

- Coach is inherited from group assignment

### Assign Groups

- Live class belongs to a group

### Assign Students

- Student roster is inherited from group membership

### Start Class

- Only assigned coach can start
- Start allowed only within configured start window

### End Class

- Only assigned coach or explicitly authorized operational user can end
- End Class must be idempotent

### Cancelled Class

- Missed class must be marked cancelled
- Cancelled class does not complete chapter

### Automatic Homework

- Homework is generated only after class completion

### Attendance

- Attendance is derived from live-class participation and heartbeat/presence signals

### Business Rules

- Live Classes are separate from weekly schedule templates
- Chapter progression occurs only after End Class
- Duplicate End Class must not advance twice
- Future chapter content must not be available early

## Section 11. Quiz Session

### Create Session

- Admin or staff creates the session

### Assign Groups

- Session targeting may depend on level, groups, or other academy rules

### Assign Countries

- Session may be restricted by allowed country list

### Assign Levels

- Students are eligible based on level and country rules

### Start

- Coach starts live quiz session

### Complete

- Session ends and results become reportable

### Reports

- Session results must flow into reporting modules

### Business Rules

- Quiz sessions are created from Quiz Bank
- Student can answer only live/current quiz
- Each student can answer each quiz once per session
- Session queue should be stable during live delivery

## Section 12. Tournament

### Swiss Tournament

- Tournament format follows Swiss pairing logic

### Pairings

- Round pairings are generated automatically

### Minimum Players

- Tournament requires minimum player count before start

### Real Time Start

- Tournament starts automatically at scheduled time when conditions are met

### Round Generation

- New rounds generate automatically after previous round completion and break logic

### Bye Rules

- Bye is assigned when participant count is odd
- Repeat byes should be avoided where possible

### Tie Break

- Buchholz is used as tie-break

### Clock

- Server-authoritative chess clocks

### Live Board

- Live chess board with legal move enforcement

### Results

- Win, loss, draw, timeout, resignation, and byes must be reflected accurately

### Reports

- Tournament standings and history feed reporting modules

### Business Rules

- Tournament follows Swiss pairing
- Only eligible students can join
- Players can only move in their own pairing
- Tournament lifecycle must be scheduler-safe and idempotent

## Section 13. Homework

### Automatic Homework

- Homework is generated only after class completion

### Chapter Based

- Homework is tied to the completed chapter/class context

### Assignment

- Assigned to students in the class group

### Attempt

- Student may attempt and submit work

### Correct

- Coach/admin may mark as correct

### Wrong

- Coach/admin may mark as incorrect with guidance

### Reports

- Homework status must feed homework and student reports

### Business Rules

- Homework is not created for cancelled classes
- Homework must be linked to actual class/chapter context where available

## Section 14. Reports

Official report modules:

- Homework Report
- Student Report
- Coach Report
- Group Report
- Class Report
- Tournament Report
- Quiz Session Report

### Filters

- Date range
- Role/user
- Group
- Coach
- Level
- Status

### Export

- CSV and Excel export should be supported where operationally required

## Section 15. Analytics

Official analytics areas:

- Student Growth
- Attendance
- Homework
- Quiz
- Tournament
- Puzzle Accuracy
- Coach Performance

### Charts

- Time-based trends
- completion trends
- participation trends
- performance comparisons

### KPIs

- Active students
- Class completion rate
- Homework completion rate
- Quiz accuracy
- Tournament participation
- Attendance rate
- Coach performance indicators

## Section 16. Database Design

### Core Table Groups

- Identity and access
  - users
  - sessions
  - password_reset_tokens

- Academic structure
  - groups
  - group_student
  - group_schedules
  - chapters
  - chapter_group
  - group_chapter_completions

- Content
  - puzzle library and curriculum content tables
  - optional metadata/tag/category support tables where introduced

- Live activity
  - live_classes
  - class_participants
  - class_chat_messages
  - class_questions
  - class_question_attempts

- Homework
  - homework
  - homework_submissions

- Quiz
  - quizzes
  - quiz_sessions
  - quiz_session_quizzes
  - quiz_session_attendees
  - quiz_session_responses

- Tournament
  - tournaments
  - tournament_groups
  - tournament_users
  - tournament_participants
  - tournament_pairings

- Analysis
  - student_analyses

### Relationships

- User to groups
- Group to schedules
- Group to chapters
- Group to completions
- Group to live classes
- Live class to coach, chapter, participants, questions, chat, homework
- Quiz session to coach, attendees, queue, responses
- Tournament to participants, pairings, audience targets

### Foreign Keys

All core operational relationships must use foreign keys where practical to preserve integrity.

### Indexes

Required index philosophy:

- status-driven indexes for live operational tables
- datetime indexes for schedule-driven tables
- composite indexes for frequent filtering and reporting

### ER Diagram Description

The system centers on `users`, `groups`, `chapters`, and `live_classes`.

- `users` connect to `groups` as coaches and students
- `groups` connect to `chapters` and `group_schedules`
- `group_schedules` generate `live_classes`
- `live_classes` produce `homework` and `group_chapter_completions`
- `quizzes` feed `quiz_sessions`
- `tournaments` feed `tournament_pairings` and `tournament_participants`

## Section 17. Business Rules

Official rules include:

- Homework is generated only after class completion
- Chapter progression occurs only after End Class
- Cancelled class does not advance chapter
- Puzzle Library automatically assigns puzzles to students of the same level
- Quiz Sessions are created from Quiz Bank
- Live Classes are separate from weekly scheduled classes
- Tournament follows Swiss pairing
- Students may access only their own groups, classes, homework, quizzes, tournaments, and analyses
- Coaches may operate only their own assigned classes and groups
- Future curriculum content must be blocked server-side
- Duplicate completion/end actions must be idempotent
- Tournament clocks are server-authoritative
- Quiz responses are one per student per question per session
- Automatic scheduling must not corrupt historical data

## Section 18. UI & UX Standards

### Sidebar

- Role-aware
- Consistent order
- No hidden business logic in UI

### Dashboard

- Actionable
- Compact
- Real data only

### Buttons

- Clear labels
- Primary action emphasis
- No duplicate actions for same purpose

### Forms

- Consistent validation
- Strong error messages
- Required fields explicit

### Tables

- Compact
- searchable where needed
- exportable in reporting modules

### Colours

- Consistent semantic status colours

### Responsive Design

- Desktop-first but tablet/mobile compatible
- No functional loss on smaller screens

### Icons

- Consistent and meaningful

### Typography

- Clear role-based enterprise/academy style
- readable at all portal levels

## Section 19. Development Standards

### Folder Structure

- Controllers by role or feature namespace
- Models by entity
- Services/support classes for business workflows
- Views by module and role

### Naming Convention

- Clear feature-oriented names
- no ambiguous module naming
- route names aligned with role and feature

### Controllers

- Controllers should orchestrate, not contain deep business logic

### Services

- Scheduling, progression, reports, and orchestration belong in services

### Policies

- Authorization should be centralized through policies/gates for maintainability

### Validation

- Validation must be server-side
- business-rule validation must not rely on UI only

### Testing

- Feature tests for every business-critical workflow
- unit tests for domain services
- regression tests for scheduler-sensitive modules

### Git Workflow

- Small, reviewable changes
- business-rule-safe rollout order

### Coding Standards

- Consistent Laravel conventions
- clear naming
- minimal duplication
- idempotent workflow handling

## Section 20. Implementation Roadmap

Recommended order for remaining work:

1. Schema alignment and database integrity
2. Authorization and permission centralization
3. Group scheduling and curriculum progression stabilization
4. Live class workflow hardening
5. Puzzle Library and Curriculum standardization
6. Quiz Bank and Quiz Session completeness
7. Tournament lifecycle and reporting completion
8. Homework reporting and student progress reporting
9. Reports module buildout
10. Analytics module buildout
11. Settings module formalization
12. UI standardization and frontend cleanup

## Document Control

This document is the permanent architecture reference for Archer Chess Academy LMS.

Rules for future development:

- Business rules in this document are the source of truth unless explicitly superseded
- Navigation and module naming must follow this document unless explicitly changed
- Implementation must preserve backend rule enforcement
- UI must not replace backend authorization or validation

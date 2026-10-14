# ARCHER DEVELOPMENT RULES

## 1. Purpose

This document is the permanent development guideline for Archer Chess Academy LMS.

It defines how all future features, fixes, refactors, and enhancements must be planned, implemented, reviewed, tested, and deployed.

This document works together with:

- [ARCHER_CURRENT_SYSTEM_ANALYSIS.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_CURRENT_SYSTEM_ANALYSIS.md)
- [ARCHER_LMS_MASTER_SYSTEM_DESIGN.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_LMS_MASTER_SYSTEM_DESIGN.md)
- [ARCHER_GAP_ANALYSIS.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_GAP_ANALYSIS.md)
- [ARCHER_IMPLEMENTATION_PLAN.md](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/ARCHER_IMPLEMENTATION_PLAN.md)

These documents are the official source of truth and must be consulted before any development work begins.

## 2. Project Philosophy

### Core Principles

- Never rewrite working code without clear technical or business justification.
- Reuse existing modules, routes, services, models, and views whenever possible.
- Never duplicate functionality that already exists elsewhere in the system.
- Always follow the Master System Design.
- Preserve backward compatibility unless a documented breaking change is explicitly approved.
- Never break working production features in order to ship a new feature faster.
- Prefer safe extension over risky replacement.
- Prefer incremental improvement over large uncontrolled refactors.
- Every new feature must fit the existing system architecture.
- Business rules must be enforced in backend logic, not only in the UI.

### Delivery Philosophy

- Build in small safe steps.
- Keep the platform operational at every stage.
- Stabilize high-risk workflows before adding new layers on top of them.
- Protect live modules first:
  - authentication
  - groups
  - live classes
  - tournaments
  - homework
  - quiz sessions

## 3. Database Rules

### Reuse First

- Reuse existing tables before creating new ones.
- Confirm whether an existing table already supports the needed behavior before proposing a schema change.
- Avoid creating parallel tables for similar concepts.

### Table Design

- Avoid duplicate tables and overlapping entity definitions.
- Keep naming consistent with the Master System Design.
- Every new table must have a clear business purpose.
- Every new table must have a clear ownership and relationship model.

### Foreign Keys

- Always use foreign keys where the database engine and deployment environment support them safely.
- Foreign keys must reflect real domain relationships.
- Orphaned records must not be accepted silently.

### Indexing

- Add proper indexes for:
  - foreign keys
  - status columns used in filters
  - date/time columns used in scheduling
  - composite query filters used by dashboards, reports, and operational lists
- Review index coverage before shipping any new reporting or scheduling feature.

### Soft Deletes

- Use soft deletes where record history matters.
- Do not hard delete user-created operational data unless explicitly justified.
- Prefer archive/deactivate over destructive deletion for:
  - users
  - groups
  - classes
  - homework
  - tournaments

### Transactions

- Use database transactions for critical multi-step operations.
- Use row locking where race conditions can affect correctness.
- Transactions are mandatory for:
  - class completion and curriculum advancement
  - tournament round generation
  - bulk import workflows
  - attendance-sensitive state changes
  - any workflow that updates multiple related tables

### Migration Safety

- Never add a migration without checking existing schema and production usage first.
- Migrations must be backward-safe where possible.
- Destructive schema changes require explicit approval and rollback planning.

## 4. Coding Rules

### Controllers

- Controllers must stay thin.
- Controllers should orchestrate requests, authorization, validation, and response handling.
- Controllers must not become the permanent home for large business workflows.
- If a controller method grows into a business process, extract it into a service.

### Services

- Multi-step business logic belongs in services.
- Scheduling, progression, tournament lifecycle, reporting aggregation, and quiz/session orchestration should live in services.
- Services must be deterministic, testable, and focused on one business responsibility.

### Repositories or Query Services

- Do not introduce repositories by default.
- Use query services or repositories only when read complexity becomes high and repeated.
- Reporting and analytics queries may justify dedicated query classes.

### Models

- Models should represent domain entities and relationships clearly.
- Keep model helpers focused and readable.
- Do not overload models with scattered workflow logic that belongs in services.
- Keep fillable/guarded protection explicit.

### Policies

- Authorization rules must be centralized in policies or a consistent permission layer.
- Do not depend only on route grouping for sensitive actions.
- Every module with edit, delete, start, join, or status-change behavior must have explicit authorization rules.

### Middleware

- Middleware should handle cross-cutting concerns only.
- Use middleware for:
  - authentication
  - role access
  - rate limiting
  - forced redirection rules
  - maintenance or environment gates
- Do not place deep business logic in middleware.

### Validation

- Validation is required for every input path.
- Never trust Blade forms, query strings, or JavaScript alone.
- Validate business rules, not just field types.
- Validation must cover:
  - required fields
  - ownership checks
  - status restrictions
  - date/time rules
  - sequence integrity
  - membership/assignment rules

### Form Requests

- Use Form Request classes when validation logic becomes repeated or large.
- Shared validation should not stay duplicated across multiple controllers.

### API/Response Resources

- If structured JSON payloads expand, use dedicated resource classes for consistency.
- Polling endpoints and live payloads should not return ad hoc inconsistent shapes.

### Events

- Use events for meaningful domain transitions when it improves decoupling.
- Good event candidates:
  - class started
  - class ended
  - homework created
  - tournament round started
  - quiz session started
- Do not add events purely for complexity.

### Queues

- Use queues for non-immediate work and expensive background processing.
- Suitable queue workloads include:
  - report generation
  - notifications
  - bulk imports/exports where needed
  - heavy analytics aggregation
- Do not queue correctness-critical state transitions unless the design explicitly supports delayed consistency.

### Notifications

- Notifications must follow role, ownership, and relevance rules.
- Notification logic should be centralized and reusable.
- Avoid duplicating email, in-app, and status-banner logic across controllers.

## 5. UI Rules

### UI Stability

- Never redesign the whole UI unless explicitly requested.
- Do not replace working layouts just to modernize appearance.
- Preserve current navigation and portal structure unless a documented design change is approved.

### Consistency

- Keep consistent spacing, typography, status badges, table actions, and form alignment.
- Keep the same color palette unless branding changes are formally approved.
- Reuse existing components and page patterns where possible.

### Reuse

- Avoid duplicate pages for the same workflow.
- If admin, staff, and coach flows are similar, share partials/components where safe.
- Keep module naming consistent across:
  - sidebars
  - headers
  - routes
  - buttons
  - reports

### Responsive Behavior

- Responsive behavior must be considered for every new screen.
- Live class and tournament pages must be tested especially carefully on smaller screens.
- Do not ship desktop-only assumptions into operational workflows.

### UI Safety

- UI may guide the user, but UI must not be the only enforcement layer.
- Disabled buttons are not a substitute for backend authorization.

## 6. Security Rules

### Authorization First

- Authorization must be checked before data mutation and before sensitive reads.
- Every create, update, delete, join, start, stop, mark, publish, or status-change action must be authorized server-side.

### Validation Everywhere

- Validate every request.
- Validate role, ownership, assignment, schedule eligibility, chapter eligibility, and status transitions.

### Mass Assignment Protection

- Keep mass assignment rules explicit.
- Never expose broad update arrays without confirming allowed fields.

### CSRF Protection

- All form and state-changing web flows must remain under CSRF protection.
- Exceptions must be rare, documented, and justified.

### Rate Limiting

- Apply rate limiting to login, polling-heavy endpoints, and abuse-prone write actions where appropriate.

### Role-Based Access

- Role-based access must be explicit and consistent.
- Admin, staff, coach, and student permissions must not drift over time.

### Audit Logging

- High-risk administrative and workflow actions should be auditable.
- Audit logging is recommended for:
  - user role changes
  - imports
  - group reassignments
  - class completion
  - tournament lifecycle changes
  - quiz session state changes

### Principle of Least Privilege

- Every role should get only the minimum access required.
- Staff must not implicitly inherit full admin capabilities through shared controllers without explicit rule checks.

## 7. Performance Rules

### Eager Loading

- Use eager loading for relational list pages and dashboards.
- Review N+1 risk on every page that shows related users, groups, chapters, classes, homework, or tournament data.

### Pagination

- Paginate large lists by default.
- Avoid loading entire datasets into Blade pages where the user only needs a table.

### Caching

- Cache only data that is safe to cache.
- Prefer caching for:
  - dashboard aggregates
  - reporting summaries
  - low-volatility reference data
- Do not cache correctness-critical live state unless invalidation is explicit and reliable.

### Queues

- Use queues for expensive non-blocking work.
- Keep user-facing state changes fast and predictable.

### Indexes

- Review index strategy whenever a new query pattern is introduced.
- Reporting, scheduler, and tournament queries must be index-aware.

### Avoid N+1 Queries

- Query review is mandatory for dashboards, reports, standings, and list pages.
- Use query profiling when adding complex aggregation pages.

## 8. Testing Rules

Every feature must include testing at the correct level.

### Manual Test Checklist

Every implementation must include a manual test checklist covering:

- happy path
- edge cases
- failure handling
- permission checks
- UI behavior
- mobile or smaller-screen impact where relevant

### Regression Testing

- Existing working behavior must be retested when touching shared modules.
- Any change to groups, live classes, tournaments, homework, or authentication requires regression awareness.

### Role Testing

- Test the feature as admin, staff, coach, and student where relevant.
- Confirm forbidden roles cannot access or mutate restricted workflows.

### Validation Testing

- Test invalid values, missing fields, unauthorized actions, duplicate submissions, and illegal transitions.

### Automated Testing Expectations

- Add or update feature tests for business-critical logic.
- Add unit tests for isolated service behavior when valuable.
- Do not rely on manual testing alone for scheduling, progression, tournament, or permission logic.

## 9. Git Rules

### Commit Strategy

- One feature or one focused fix per commit where practical.
- Keep commits small enough to review and reason about.

### Pull Requests

- Keep pull requests small and focused.
- Avoid mixing unrelated fixes in the same change set.

### Rollback Strategy

- Every risky change must have a rollback plan.
- Schema changes, permission changes, and scheduler changes require extra rollback attention.

### Commit Messages

- Use meaningful commit messages.
- Commit messages should describe intent, not just files touched.

Examples:

- `Fix group schedule regeneration after coach reassignment`
- `Add policy checks for tournament management actions`
- `Refactor homework creation into class completion service`

## 10. Before Every Implementation

Before writing code, Codex must:

1. Read the locked documentation set.
2. Check the existing implementation in the relevant module.
3. Reuse existing code where possible.
4. Identify affected tables, routes, controllers, models, views, and tests.
5. Explain the implementation plan before making changes when the task is substantial.
6. Confirm whether the task is:
   - a bug fix
   - a business rule update
   - a UI adjustment
   - a database change
   - a refactor
7. Avoid starting with code if the architecture or scope is still unclear.

## 11. After Every Implementation

After implementation, Codex must produce a summary containing:

- Files Changed
- Database Changes
- Routes Changed
- Controllers Changed
- Models Changed
- Views Changed
- Tests Added
- Known Issues
- Manual Testing Checklist

If there were no changes in one category, that should be stated clearly.

## 12. Change Management Rules

### Safe Change Order

Preferred order for risky work:

1. understand current implementation
2. document assumptions
3. add or update tests
4. implement the smallest safe backend change
5. verify permissions and validation
6. verify UI behavior
7. verify regression safety

### Refactor Rules

- Never refactor large areas during an urgent bug fix unless necessary.
- Separate behavior changes from cleanup when possible.
- Preserve route names and public behavior unless explicitly approved to change them.

### Backward Compatibility

- Existing working routes, views, and database relationships should remain compatible unless a controlled migration plan exists.
- Production data must be preserved.

## 13. Module-Specific Safety Rules

### Authentication

- Never weaken login or role protection to make development easier.
- Authentication changes require regression testing across all roles.

### Groups and Scheduling

- Group schedule changes must preserve historical records.
- Schedule changes must not silently corrupt future class generation.

### Curriculum

- Chapter progression must remain sequential and backend enforced.
- Future chapters must never become accessible only because of a UI mistake.

### Live Classes

- Class start, join, end, and attendance flows must remain authorization-safe.
- End-class behavior must be idempotent if it affects progression or homework.

### Tournament

- Pairings, clocks, standings, and results are correctness-critical.
- Tournament state transitions must be transaction-safe and test-backed.

### Homework

- Homework creation, submission, and marking must preserve traceability.

### Quiz Sessions

- Quiz responses must remain role-safe, duplicate-safe, and status-safe.

## 14. Documentation Rules

- Every substantial feature change must align with the locked documentation set.
- If implementation reveals a genuine documentation mismatch, update documentation only through an explicit documentation task.
- Do not silently drift away from the official design.

## 15. Final Rule

No feature should be implemented only because it is easy to code.

Every feature must be:

- architecturally aligned
- permission-safe
- regression-safe
- testable
- maintainable
- consistent with the Archer LMS master design

No source-code files were modified while creating this document.

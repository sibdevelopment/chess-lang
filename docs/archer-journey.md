# Archer Journey — Staff Guide

Archer Journey is a gamification and brand layer on top of the existing LMS. It does not replace attendance, homework, puzzles, classes or tournaments. Those existing records remain the source of truth.

## What is active

- Archer Stars, levels, badges, weekly missions, monthly challenges, leaderboards, passport, in-app achievement notifications and tournament ratings are enabled.
- Archer Discovery, Houses and Refer a Friend are built behind disabled feature flags. Do not enable them until Archer is ready to launch those programmes.
- Archer management links are intentionally absent from Admin, Staff and Coach menus. Rewards run automatically from verified LMS activity.
- Parent login and age/birthday features are intentionally not included. Those details remain in the external ERP.

## Automatic LMS connections

| Existing LMS activity | Archer Journey event | Default reward |
| --- | --- | ---: |
| Student attends a completed class | `CLASS_ATTENDED` | 5 Stars |
| Student submits homework | `HOMEWORK_COMPLETED` | 5 Stars + first-completion Homework Hero badge |
| Student completes a Puzzle Bank puzzle | `PUZZLE_COMPLETED` | 3 Stars, maximum 3 rewards per day |
| Student participates in a started tournament | `TOURNAMENT_PARTICIPATED` | 10 Stars |
| Student wins a tournament game | `TOURNAMENT_WON` | 5 Stars |
| Student delivers checkmate in a tournament | `TOURNAMENT_CHECKMATE` | 5 Stars, maximum 3 rewards per day |
| Student's tournament rating improves | `RATING_IMPROVED` | 5 Stars |
| Coach gives an achievement | `ACHIEVEMENT_CREATED` | 5 Stars |
| Student completes a mission/challenge | Completion event | Configured mission/challenge reward |
| Student attends every completed class in a month | `MONTHLY_ATTENDANCE_100` | 15 Stars |

Every event has a unique key. Repeating a request or scheduler run does not issue the same reward again. Every Star change is an immutable audit record. Admin corrections create a reversal or adjustment; they do not delete history.

## Tournament ratings

The LMS maintains separate Classical, Rapid and Blitz ratings. The tournament format decides which rating changes. The engine uses the FIDE expected-score table, a 400-point calculation cap, K factors of 40/20/10, the 700-point rating-period cap and FIDE-style rounding. Age-based junior K-factor logic is intentionally not used because age/birthday data is out of scope.

These are labelled **Archer FIDE-style ratings**. They are internal academy ratings, not official ratings published by FIDE or a national federation.

- An imported ERP starting rating may be entered before a student has a rated LMS game.
- If none is imported, the provisional starting value is 1400.
- A game is rated only after both players have made at least one move (`move_count >= 2`). Byes and unplayed games are excluded.
- Once rated game history exists, the starting value cannot be overwritten.

## Admin workflow

There is no Archer Journey shortcut in the Admin or Staff dashboard. The protected `/admin/archer-journey` maintenance page remains available to an administrator only when configuration or audit work is required.

- **Overview:** engagement totals and feature flags.
- **Stars & Rules:** change Star values, frequency and daily/weekly/monthly limits; create new rules.
- **Levels:** change display names, descriptions, icons and Star thresholds.
- **Badges:** create, edit, archive or manually award badges. Earned badges are archived instead of deleted.
- **Missions:** create and scope missions by dates, country or Archer level.
- **Monthly Challenge:** combine class, puzzle, tournament and homework targets.
- **Ratings:** import an ERP starting rating and inspect current ratings.
- **Houses:** configure the disabled future foundation and assign students.
- **Audit Log:** inspect all transactions, add a documented adjustment or reverse an incorrect transaction.

Use the student search box before a manual badge, rating, House or adjustment action. The selector is limited to 100 matching active students so the page remains efficient with 10,000+ accounts.

## Coach workflow

- Coaches do not calculate Stars or manage Archer Journey from the menu.
- Attendance, homework and tournament activity are read automatically from normal LMS actions.
- The achievement and Discovery foundations remain available for future use, but their menu entries are hidden and Discovery is disabled.

## Student experience

Open `/student/archer-journey`.

The Journey tab shows level, Stars, badges, tournament count, streak, current mission, next milestone, separate ratings, monthly challenge and achievement inbox. Passport is chronological. Leaderboards expose only a privacy-safe first name and surname initial.

## ERP data boundary

Continue transferring core student data to the existing `users` record. Archer Journey reads:

- `users.id` as the permanent student identity
- `users.name`
- `users.country`
- `users.timezone` (IANA value such as `Asia/Kolkata` or `America/New_York`)
- `users.is_active`
- existing batch membership

Do not create a second student record for Archer Journey. Parent accounts, birthday and age remain in the ERP. Timestamps are stored in UTC and events also retain user and event timezones.

## Operations

The normal Laravel scheduler must keep running every minute. `archer:maintain` is scheduled daily at 00:15 UTC. It creates the current recurring weekly missions/monthly challenge and awards previous-month 100% attendance.

Useful commands:

```bash
php artisan archer:maintain
php artisan archer:maintain --backfill
php artisan tournaments:process
php artisan queue:work --queue=archer-journey,default
```

The current local environment uses the synchronous queue, so events are applied immediately. A production deployment may use a database/Redis queue; keep the `archer-journey` worker running.

## Safe rollout

Feature flags support 0–100% deterministic rollout. Disable a feature before a staged change instead of deleting its data. Houses and referrals must remain off until explicitly approved.

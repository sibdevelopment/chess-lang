# ARCHER CURRENT CURRICULUM BEHAVIOUR

This document describes the existing Database/Curriculum module exactly as it works in the current codebase.

It documents the current system only.

- It does not describe the future design.
- It does not assume missing features exist.
- Anything incomplete, legacy, or unclear is explicitly marked.

## 1. Menu and Routes

### Admin Sidebar Menu Label

Current admin sidebar label:
- `Curriculum`

File:
- [resources/views/admin/_sidebar.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/_sidebar.blade.php)

Important note:
- The codebase still uses both `library` and `database` route names internally.
- The visible admin sidebar currently links to `admin.library`, not `admin.database`.

### Admin Routes

Declared in:
- [routes/web.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php)

Admin route group middleware:
- `auth`
- `role:admin`

Admin Curriculum-related routes:

1. `admin.library`
- URL: `/admin/library`
- Method: `GET`
- Controller: `App\Http\Controllers\Admin\LibraryController@index`
- Purpose: main admin PGN library page

2. `admin.library.folder.create`
- URL: `/admin/library/folder`
- Method: `POST`
- Controller: `App\Http\Controllers\Admin\LibraryController@createFolder`
- Purpose: legacy folder stub only

3. `admin.library.upload`
- URL: `/admin/library/upload`
- Method: `POST`
- Controller: `App\Http\Controllers\Admin\LibraryController@uploadPgn`
- Purpose: upload a PGN chapter

4. `admin.library.chapter.download`
- URL: `/admin/library/chapter/{chapter}/download`
- Method: `GET`
- Controller: `App\Http\Controllers\Admin\LibraryController@downloadChapter`
- Purpose: download chapter PGN

5. `admin.library.chapter.update`
- URL: `/admin/library/chapter/{chapter}`
- Method: `PUT`
- Controller: `App\Http\Controllers\Admin\LibraryController@updateChapter`
- Purpose: update metadata and optionally replace/merge PGN

6. `admin.library.chapter.delete`
- URL: `/admin/library/chapter/{chapter}`
- Method: `DELETE`
- Controller: `App\Http\Controllers\Admin\LibraryController@deleteChapter`
- Purpose: delete a chapter

7. `admin.library.folder.delete`
- URL: `/admin/library/folder/{folder}`
- Method: `DELETE`
- Controller: `App\Http\Controllers\Admin\LibraryController@deleteFolder`
- Purpose: legacy folder stub only

8. `admin.database`
- URL: `/admin/database`
- Method: `GET`
- Controller: `App\Http\Controllers\Admin\LibraryController@database`
- Purpose: coach-wise overview of what coaches can see

9. `admin.database.assign`
- URL: `/admin/database/assign`
- Method: `POST`
- Controller: `App\Http\Controllers\Admin\LibraryController@assignDatabase`
- Purpose: legacy manual assignment stub only

10. `admin.database.revoke`
- URL: `/admin/database/{assignment}`
- Method: `DELETE`
- Controller: `App\Http\Controllers\Admin\LibraryController@revokeDatabase`
- Purpose: legacy revoke stub only

### Coach-side Database/Curriculum Routes

Coach route group middleware:
- `auth`
- `role:coach`

Routes:

1. `coach.database`
- URL: `/coach/database`
- Method: `GET`
- Controller: `App\Http\Controllers\Coach\DatabaseController@index`
- Purpose: coach read-only curriculum list

2. `coach.database.chapter`
- URL: `/coach/database/chapter/{chapter}`
- Method: `GET`
- Controller: `App\Http\Controllers\Coach\DatabaseController@viewChapter`
- Purpose: open one chapter in a board viewer

### Staff-side Access

Staff route group middleware:
- `auth`
- `role:staff`

Routes:

1. `staff.database`
- URL: `/staff/database`
- Method: `GET`
- Controller: inline closure returning `view('staff.database')`
- Purpose: informational page only

2. `staff.database.assign`
- URL: `/staff/database/assign`
- Method: `POST`
- Controller: `App\Http\Controllers\Admin\LibraryController@assignDatabase`
- Purpose: legacy assignment stub only

Important note:
- Staff do not have a staff-side chapter listing page.
- Staff do not have chapter upload, edit, download, or delete routes in the current route file.

### Student-side Access

Direct student-side curriculum/database access:
- Not available

Current student access is indirect only through:
- live classes
- homework
- student analysis

No `student.database*` or `student.curriculum*` routes exist.

## 2. Current Database Page

This section describes the current admin-facing page linked from the sidebar.

Current sidebar click target:
- `admin.library`
- View: [resources/views/admin/library/index.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/library/index.blade.php)

### Page Layout

The current admin page contains:
- page header with title `PGN Library`
- top success/info/error alerts
- large upload CTA banner
- four top stats cards
- upload modal
- edit modal
- level filter buttons
- grouped flat chapter tables by level
- empty state if no chapters exist

### Upload Section

Visible UI:
- a CTA card with button `+ Create / Upload PGN`
- clicking the button opens a modal

Upload modal fields currently visible:
- `PGN name`
- `Level`
- `Sequence #`
- `PGN file`

Not visible in the current upload form:
- description field

Important mismatch:
- the upload controller validates `description`
- the current upload Blade does not render a description input
- so description is accepted by backend, but not exposed on the current admin page

### Chapter List

Display behavior:
- chapters are grouped by level
- levels are rendered in this fixed order:
  - `beginner`
  - `intermediate`
  - `advanced`
- within each level block, chapters are rendered in the order returned by the controller

Table columns:
- Seq
- Name
- Moves
- Uploaded by
- Date
- Action

If a chapter has a description:
- the description is shown under the chapter name as truncated muted text

### Search

Current admin library page search:
- Not available

There is no search input and no controller search logic.

### Filters

Visible filter buttons:
- All
- Beginner
- Intermediate
- Advanced

Filter source:
- request query param `level`

Current filter behavior:
- controller filters `Chapter` query by `level` if level is not `all`
- result is then grouped by level for rendering

### Sorting

Current controller sorting:
- `orderBy('sequence_number')`

Current UI grouping:
- grouped after retrieval using `groupBy('level')`

Important note:
- the admin library page does not use `Chapter::orderedForLevel()`
- it sorts only by `sequence_number` before grouping

### Pagination

Current admin library page pagination:
- Not available

The controller uses:
- `get()`

### Buttons

Current admin page buttons/actions:
- `+ Create / Upload PGN`
- level filter buttons
- `Edit`
- `Download`
- `Delete`

### Actions Available Against Each Chapter

For each row:
- Edit
- Download
- Delete

Not available on the admin library page:
- preview
- open on chessboard
- in-page board viewer
- puzzle selector
- move editor
- comment editor
- PGN annotation editor

### Empty States

If no chapters exist:
- card with icon `📚`
- text `No PGNs uploaded yet`
- text `Use the form above to upload your first PGN.`

### Validation Messages

Current messages shown on page:
- `success`
- `info`
- full validation error list if `$errors->any()`

Upload modal auto-open behavior:
- if there are validation errors
- or if `old('name')` exists

## 3. Chapter Data Structure

### Chapter Model

File:
- [app/Models/Chapter.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/Chapter.php)

### Database Table

Table:
- `chapters`

Defined in migration:
- [database/migrations/2024_01_01_000002_create_groups_and_library.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/database/migrations/2024_01_01_000002_create_groups_and_library.php)

### Chapter Columns

Confirmed chapter columns from migration:
- `id`
- `name`
- `pgn`
- `level`
- `sequence_number`
- `folder_id`
- `uploaded_by`
- `file_path`
- `sort_order`
- `description`
- `move_count`
- `created_at`
- `updated_at`

### Fillable Fields

Current `$fillable`:
- `name`
- `pgn`
- `level`
- `sequence_number`
- `folder_id`
- `uploaded_by`
- `file_path`
- `sort_order`
- `description`
- `move_count`

### Casts

Current model casts:
- none defined

### Relationships

Current relationships:
- `folder()` → belongsTo `PgnFolder` by `folder_id`
- `uploader()` → belongsTo `User` by `uploaded_by`
- `groups()` → belongsToMany `Group` via `chapter_group` with pivot `sequence_order`
- `homework()` → hasMany `Homework`
- `liveClasses()` → hasMany `LiveClass`

### Sequence-number Behaviour

Current behavior:
- sequence number is globally unique across all levels
- not unique per level
- upload requires explicit sequence number
- admin UI suggests next sequence using `max(sequence_number) + 1`
- admin edit allows changing sequence number
- `sort_order` is set equal to `sequence_number` during upload and update

### Level Behaviour

Current level values:
- `beginner`
- `intermediate`
- `advanced`

Current behavior:
- level is required on upload
- level is editable in update
- controller filtering and coach display use level
- groups select chapters by matching chapter level against group level

### Description Behaviour

Current backend behavior:
- upload controller accepts optional `description`
- chapter rows display description if present

Current UI behavior:
- current upload form does not include a description field
- current edit form does not include a description field
- current update controller does not validate or update `description`

Current practical result:
- description can exist in database
- description is displayed if present
- current admin web UI does not provide a way to create or edit it

### Move-count Behaviour

Current backend behavior:
- move count is calculated with regex: `preg_match_all('/\d+\./', $pgn, $matches)`
- stored as count of all move-number tokens found in PGN text

Implication:
- this is not a parsed game count
- this is not a puzzle count
- it is a raw count of numeric move markers like `1.`, `2.`, etc.

### File-storage Behaviour

Current storage behavior:
- raw PGN text is stored in `chapters.pgn`
- file path is stored in `chapters.file_path`
- upload saves file under `pgn/{level}` on the `local` disk
- merge writes a generated file path `pgn/{level}/merged_<uniqid>.pgn` to `local`

### Delete Behaviour

Current delete behavior:
- controller attempts to delete the stored file first if `file_path` exists
- then attempts `$chapter->delete()`
- if DB constraints block deletion, the exception is caught and a friendly error is returned

Important note:
- chapter model does not use soft deletes
- delete is a hard delete if allowed

## 4. PGN Upload Behaviour

Controller:
- [app/Http/Controllers/Admin/LibraryController.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/LibraryController.php)

### Accepted File Type

Validated MIME extensions:
- `pgn`
- `txt`

Form `accept` attribute:
- `.pgn,.txt`

### File-size Validation

Current rule:
- `max:4096`

Meaning in Laravel file validation:
- maximum 4096 KB

### Where PGN Files Are Stored

Upload:
- `$file->store("pgn/{$data['level']}", 'local')`

Merge:
- `Storage::disk('local')->put($newPath, $newPgn)`

Current disk:
- `local`

### How File Names Are Generated

Upload:
- generated by Laravel `store()` on the local disk

Merge:
- generated manually as `merged_<uniqid>.pgn`

Replace:
- uses Laravel `store()` again for the uploaded replacement file

### How Chapter Name Is Generated or Entered

Current behavior:
- admin enters `name` manually in upload form
- no automatic name generation

### How Level Is Selected

Current behavior:
- admin selects from dropdown:
  - Beginner
  - Intermediate
  - Advanced

### How Sequence Number Is Handled

Current behavior:
- required input
- suggested by controller as `max(sequence_number) + 1`
- uniqueness enforced globally in `chapters.sequence_number`

### How Description Is Handled

Current backend:
- accepts optional `description`

Current admin UI:
- no description field in upload modal

Result:
- description can be submitted only by non-current UI paths such as seeded data, manual request crafting, or other code paths

### How Move Count Is Calculated

Current behavior:
- regex count of `\d+\.` across the PGN text

### What Happens After Upload

After upload:
- raw PGN text is read into memory
- file is stored on disk
- move count is computed
- `Chapter::create(...)` is called
- browser redirects back with success message

### Duplicate Handling

Current duplicate handling:
- duplicate `sequence_number` is blocked by validation
- duplicate name is allowed
- duplicate PGN content is allowed
- duplicate file upload is not specially detected

### Error Handling

Current error handling:
- validation errors return back with errors
- modal reopens because of `old('name')` / `$errors->any()`
- no custom parser validation of PGN syntax on upload

## 5. PGN Editing Actions

Current edit UI and controller support:

| Action | Status | Notes |
|---|---|---|
| Rename chapter | Working | edit modal has `name`, update controller persists it |
| Change level | Working | edit modal has `level`, update controller persists it |
| Change sequence | Working | edit modal has `sequence_number`, uniqueness validated |
| Edit description | Not available | backend field exists, current edit UI does not expose it, update controller does not update it |
| Keep existing PGN | Working | `pgn_action=keep` |
| Replace PGN | Working | `pgn_action=replace` requires new file |
| Merge PGN | Working | `pgn_action=merge` appends uploaded PGN text |
| Download PGN | Working | admin download route streams stored `pgn` text |
| Delete chapter | Working / constrained | works unless blocked by DB dependencies |
| Preview PGN | Not available | no admin preview action |
| Open PGN on a chessboard | Not available on admin page | only coach-side chapter route opens a board viewer |
| Edit moves | Not available | no move editing UI or save path |
| Edit comments | Not available | no chapter comment editor |
| Edit answers | Not available | no answer field or save path in curriculum module |
| Save PGN changes | Partially working | replace/merge saves the whole PGN text/file; no direct in-browser move/comment editing |

## 6. Multiple Puzzles Inside One PGN

Current parser source:
- [resources/views/partials/_chess-engine.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chess-engine.blade.php)

### Whether Multiple Games Are Detected

- Yes, in the frontend viewer

Current behavior:
- `parsePGNFile(pgn)` splits the PGN into blocks using `(?=^\[Event\s)` in multiline mode
- if no `[Event ...]` boundary is found, it falls back to a single parsed game

### Whether Each Game Is Treated as a Separate Puzzle

- Partially

Current viewer behavior:
- each parsed PGN game becomes one selectable item in the left-side `Positions` list in the chapter viewer
- this is a frontend viewing behavior only

### Whether Games Are Parsed at All

- Yes

Current parser:
- parses headers
- uses optional `FEN`
- parses main line SAN moves
- parses comments
- parses nested variations

### Whether Puzzle Count Is Calculated

- Not as a stored database value

Current state:
- the chapter viewer counts number of parsed positions/games in the browser
- database `move_count` is not a game count

### Whether an Individual Puzzle Can Be Selected

- Yes, in the coach chapter viewer

Current behavior:
- left column allows selecting a parsed position/game

### Whether an Individual Puzzle Can Be Edited

- Not available

### Whether Comments, Annotations or Answers Are Preserved

- Comments and variations are preserved in the frontend parser when reading the PGN
- There is no dedicated “answers” field in the chapter data structure

### Whether Saving One Puzzle Back Into the Chapter Is Currently Possible

- Not available

## 7. Chessboard Behaviour

### Chessboard Library Used

Current board implementation:
- custom in-project JavaScript
- `window.MiniChess`

Source:
- [resources/views/partials/_chess-engine.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chess-engine.blade.php)

### Chess.js or PGN Parser Used

Current behavior:
- no `chess.js` package is used in this module
- no external PGN parser package is used in this module
- parsing and board logic are implemented in the custom `MiniChess` code

### Board Loading

- Working on coach chapter page

Behavior:
- chapter viewer auto-parses the PGN
- first position loads automatically if parsing returns at least one position

### FEN Loading

- Working

Behavior:
- parser reads `[FEN "..."]` if present
- defaults to standard chess starting position if no FEN header exists

### Move Navigation

- Working

Available controls:
- start
- previous
- next
- end
- flip
- clickable move strip
- clickable position list

### Drag-and-drop

- Not available in the coach curriculum chapter viewer

Current viewer config:
- `draggable: false`

### Position Editing

- Not available in the curriculum viewer

Although `_chess-engine.blade.php` contains general board/editor support, the coach curriculum chapter view does not expose an editor workflow.

### Move Editing

- Not available

### Comments

- Parsed and displayed if present
- no UI for editing and saving comments in the curriculum module

### Variations

- Parsed and shown as preview-only variation lines in the move strip
- no save/edit UI

### Arrows

- Engine supports overlay drawing in general
- curriculum coach viewer does not expose draw mode
- current curriculum viewer does not provide arrow authoring UI

### Highlights

- Same as arrows
- engine supports overlay/highlight rendering in general
- curriculum chapter view does not expose highlight editing controls

### Save Behaviour

- No save behavior exists in the current curriculum viewer

If the Database/Curriculum page currently has a chessboard:
- Yes, but only on coach chapter view pages, not on the admin library listing page

## 8. Coach “My Database”

Coach controller:
- [app/Http/Controllers/Coach/DatabaseController.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/DatabaseController.php)

Coach views:
- [resources/views/coach/database/index.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/coach/database/index.blade.php)
- [resources/views/coach/database/chapter.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/coach/database/chapter.blade.php)

### How Uploaded Chapters Reach Coaches

Current behavior:
- automatically
- every active coach sees every uploaded chapter

### Whether All Coaches See All Chapters

- Yes

Confirmed in:
- `Admin\LibraryController@database`
- `Coach\DatabaseController@index`
- both load the full `Chapter` library, grouped by level

### Whether Chapters Are Assigned Manually

- No, not in the active behavior

Legacy assignment routes still exist, but:
- `assignDatabase()` returns info message only
- `revokeDatabase()` returns info message only
- `createFolder()` and `deleteFolder()` are also legacy stubs

### Filters and Levels

Coach page supports:
- All
- Beginner
- Intermediate
- Advanced

### Chapter Opening Behaviour

Coach list page:
- shows level-grouped table
- each row has `View board`

Opening a chapter:
- `coach.database.chapter`
- renders one read-only chapter board page

### PGN Download/View Behaviour

Coach:
- can view chapter on board
- cannot download from current coach routes

Current chapter page explicitly says:
- `View-only`
- `Coaches can study, not download.`

### Chessboard Behaviour

Coach chapter page uses:
- `partials._chapter-viewer`
- custom `MiniChess` board

Available on coach chapter page:
- parse multi-game PGN
- choose a position
- navigate moves
- view annotations/comments
- view notes block from `chapter.description` if present

### Whether Coaches Can Edit

- No

Current coach curriculum routes are read-only.

## 9. Curriculum Integrations

### Groups

Main links:
- `chapter_group.chapter_id`
- `chapter_group.group_id`
- `chapter_group.sequence_order`
- `groups.unlocked_chapter_index`

Current relationships:
- `Chapter::groups()`
- `Group::chapters()`

Admin group create/update behavior:
- group selects a `first_chapter_id`
- `Admin\GroupController` slices the ordered level chapter list from that starting chapter onward
- those chapters are synced to the group with pivot `sequence_order`

### Live Classes

Columns:
- `live_classes.chapter_id`
- `live_classes.chapter_index`

Current links:
- `Chapter::liveClasses()`
- `LiveClass::chapter` relationship is used throughout views/controllers

Current behavior:
- scheduler places the group’s current chapter into generated live classes
- coach start logic revalidates that the scheduled class still matches the current curriculum chapter

### Homework

Columns:
- `homework.chapter_id`

Current behavior:
- homework created on class end copies the live class chapter into homework
- student homework solve page uses `homework->chapter->pgn`

### Coach Classroom

Relevant route/controller:
- `coach.groups.chapters`
- `Coach\ClassController@getChaptersForGroup`

Current behavior:
- returns only chapters up to the group’s current pointer
- future locked chapters are excluded
- returned JSON marks:
  - `done`
  - `current`
  - `locked` always false in the returned filtered list

### Student Classroom

Current behavior:
- student can only join a live class if:
  - assigned to the class group
  - class is live
  - class `chapter_index` matches group `unlocked_chapter_index`
- past ended class chapters are loaded separately for revision history

### Reports

Direct report integration:
- none found

Indirect dependency:
- chapters are present in groups, classes, homework, and dashboards

### Puzzle Library

Current practical state:
- the PGN Library is the current curriculum/puzzle library
- there is no separate puzzle entity

## 10. File Inventory

### Models
- [app/Models/Chapter.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/Chapter.php)
- [app/Models/PgnFolder.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/PgnFolder.php)
- [app/Models/DatabaseAssignment.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/DatabaseAssignment.php)
- [app/Models/Group.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Models/Group.php)

### Controllers
- [app/Http/Controllers/Admin/LibraryController.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/LibraryController.php)
- [app/Http/Controllers/Coach/DatabaseController.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/DatabaseController.php)
- [app/Http/Controllers/Admin/GroupController.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Admin/GroupController.php)
- [app/Http/Controllers/Coach/ClassController.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Coach/ClassController.php)
- [app/Http/Controllers/Student/ClassController.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/ClassController.php)
- [app/Http/Controllers/Student/HomeworkController.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Http/Controllers/Student/HomeworkController.php)

### Routes
- [routes/web.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/routes/web.php)

### Blade Views
- [resources/views/admin/library/index.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/library/index.blade.php)
- [resources/views/admin/library/database.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/library/database.blade.php)
- [resources/views/coach/database/index.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/coach/database/index.blade.php)
- [resources/views/coach/database/chapter.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/coach/database/chapter.blade.php)
- [resources/views/staff/database.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/staff/database.blade.php)
- [resources/views/partials/_chapter-viewer.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chapter-viewer.blade.php)
- [resources/views/partials/_chess-engine.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chess-engine.blade.php)
- [resources/views/partials/_chess-ui.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chess-ui.blade.php)

### JavaScript

Inline in Blade/partials:
- [resources/views/admin/library/index.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/library/index.blade.php)
- [resources/views/admin/library/database.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/library/database.blade.php)
- [resources/views/partials/_chapter-viewer.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chapter-viewer.blade.php)
- [resources/views/partials/_chess-engine.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chess-engine.blade.php)

### CSS

Inline or pushed from Blade:
- [resources/views/admin/library/index.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/library/index.blade.php)
- [resources/views/admin/library/database.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/admin/library/database.blade.php)
- [resources/views/partials/_chapter-viewer.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chapter-viewer.blade.php)
- [resources/views/partials/_chess-engine.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chess-engine.blade.php)
- [resources/views/partials/_chess-ui.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chess-ui.blade.php)

### Migrations
- [database/migrations/2024_01_01_000002_create_groups_and_library.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/database/migrations/2024_01_01_000002_create_groups_and_library.php)
- [database/migrations/2024_01_01_000003_create_classes_and_homework.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/database/migrations/2024_01_01_000003_create_classes_and_homework.php)
- [database/migrations/2026_07_28_000002_harden_group_scheduling_and_progression.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/database/migrations/2026_07_28_000002_harden_group_scheduling_and_progression.php)

### Services / Helpers
- [app/Support/ClassScheduler.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/ClassScheduler.php)
- [app/Support/GroupChapterProgressionService.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/app/Support/GroupChapterProgressionService.php)

### Parsers
- [resources/views/partials/_chess-engine.blade.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/resources/views/partials/_chess-engine.blade.php)

### Tests
- [tests/Feature/PortalActionSafetyTest.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature/PortalActionSafetyTest.php)
- [tests/Feature/GroupSchedulingFlowTest.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature/GroupSchedulingFlowTest.php)
- [tests/Feature/GroupSchedulingManagementTest.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature/GroupSchedulingManagementTest.php)
- [tests/Feature/AuthAuthorizationHardeningTest.php](/Applications/XAMPP/xamppfiles/htdocs/chess-academy/tests/Feature/AuthAuthorizationHardeningTest.php)

## 11. Current Behaviour Flow

### A. Admin uploads a PGN chapter

1. Admin opens `/admin/library`
2. Admin clicks `+ Create / Upload PGN`
3. Modal opens
4. Admin enters:
   - name
   - level
   - sequence number
   - file
5. Form posts to `admin.library.upload`
6. Controller validates:
   - name
   - level
   - sequence number unique
   - file type
   - file size
7. Controller reads PGN text
8. File is stored to `local` disk under `pgn/{level}`
9. Move count is computed using regex
10. Chapter row is created
11. Admin is redirected back with success message

### B. Admin edits or replaces a PGN chapter

1. Admin clicks `Edit`
2. Edit modal opens with:
   - name
   - level
   - sequence number
   - PGN action radio:
     - keep
     - replace
     - merge
3. If `keep`:
   - metadata updates only
4. If `replace`:
   - uploaded file becomes the new whole PGN
   - old file is deleted
5. Controller recalculates move count
6. Chapter row is updated
7. Redirect back with success message

Important current limitation:
- description is not editable in this flow

### C. Admin merges a PGN

1. Admin clicks `Edit`
2. Admin selects `Merge / append PGN`
3. Admin uploads a second PGN/TXT file
4. Controller appends new file text to existing `chapter.pgn`
5. Old stored file is deleted
6. Merged PGN is written to a new generated `.pgn` file on `local` disk
7. Move count is recalculated by regex
8. Chapter row is updated
9. Redirect back with success message

### D. Admin downloads a PGN

1. Admin clicks `Download`
2. `downloadChapter()` streams the raw `chapter.pgn`
3. Filename format:
   - `NNN-safe_name.pgn`

### E. Coach opens My Database

1. Coach opens `/coach/database`
2. Controller loads all chapters, optionally filtered by `level`
3. Controller groups them by level
4. Coach sees grouped read-only list
5. Coach can click `View board`

### F. Coach opens a chapter

1. Coach opens `/coach/database/chapter/{chapter}`
2. Controller loads the chapter
3. View includes `_chapter-viewer`
4. Frontend parser reads the PGN
5. Left column shows parsed positions/games
6. Center shows a read-only board
7. Right side shows move list and annotation
8. Optional chapter description is shown in a notes card if present

### G. Chapter is selected inside a Live Class

1. Group is configured with a curriculum sequence through `chapter_group`
2. `ClassScheduler` creates scheduled live classes using the group’s current chapter
3. `live_classes.chapter_id` and `live_classes.chapter_index` are set
4. Coach start logic revalidates that the live class still matches the group’s current curriculum chapter
5. Coach live class can expose current and completed chapters through `getChaptersForGroup()`

### H. Chapter is used for Homework

1. Coach ends a live class
2. Homework is auto-created from that class
3. Homework row stores `chapter_id`
4. Student opens homework
5. Student solve page uses `homework->chapter->pgn`
6. Chapter content is therefore reused directly for homework solving

## 12. Working, Partial and Missing

### Working Now

- Admin can upload PGN chapters
- Admin can set level
- Admin can set sequence number
- Admin can rename chapters
- Admin can change level
- Admin can change sequence
- Admin can replace PGN file
- Admin can merge PGN file text
- Admin can download PGN
- Admin can delete chapters if no DB dependency blocks deletion
- Coach can see all uploaded chapters
- Coach can filter by level
- Coach can open a read-only board view
- Multi-game PGN files are parsed into selectable viewer positions
- Comments and variations are read and displayed by the frontend parser
- Chapters integrate with groups
- Chapters integrate with live classes
- Chapters integrate with homework

### Partially Working

- Description support
  - backend field exists
  - display exists
  - current upload/edit UI does not expose it
- Legacy folder/database assignment architecture
  - schema and routes still exist
  - active behavior is global-access, not folder-based
- Admin “database” route
  - works as a coach-wise overview
  - not the same page linked by the sidebar
- Save PGN changes
  - replace and merge work
  - no in-browser PGN/chessboard editor exists
- Multi-puzzle handling
  - parsed for viewing
  - not persisted as separate puzzle records

### Not Available

- Admin search on curriculum page
- Admin pagination on curriculum page
- Admin chessboard preview from the library list
- In-browser PGN move editing
- In-browser comment editing
- In-browser variation editing
- In-browser answer editing
- Saving one selected puzzle back into a chapter
- Coach editing of chapters
- Coach download of chapters
- Student direct curriculum route
- Separate puzzle entity/library distinct from chapters
- Lichess Study editor

## 13. Current Gaps and Unclear Areas

Confirmed incomplete or legacy areas:

1. `description` exists in the data model and upload validation, but is not editable from the current admin library UI.

2. The folder system is deprecated in active behavior, but:
- `pgn_folders` table still exists
- `folder_id` still exists on `chapters`
- assignment/revoke routes still exist as stubs

3. The admin sidebar points to `admin.library`, but a second admin curriculum-related page still exists at `admin.database`.

4. The viewer parser is frontend-only. The server does not validate PGN syntax beyond file type and size.

5. `move_count` is a regex count of move-number markers, not a parsed puzzle/game count.

No application source-code files were modified while creating this document.

<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\PuzzleLibraryController as AdminPuzzleLibraryController;
use App\Http\Controllers\ArcherDiscoveryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Coach;
use App\Http\Controllers\Live\ChatController as LiveChat;
use App\Http\Controllers\Live\LeaderboardController as LiveLeaderboard;
use App\Http\Controllers\Live\PresenceController as LivePresence;
use App\Http\Controllers\Quiz\AdminQuizController;
use App\Http\Controllers\Quiz\AdminQuizSessionController;
use App\Http\Controllers\Quiz\CoachQuizSessionController;
use App\Http\Controllers\Quiz\StudentQuizSessionController;
use App\Http\Controllers\Report\StudentPerformanceReportController;
use App\Http\Controllers\Student;
use App\Http\Controllers\Student\PuzzleLibraryController as StudentPuzzleLibraryController;
use App\Http\Controllers\Tournament\AdminTournamentController;
use App\Http\Controllers\Tournament\PlayController as TournamentPlayController;
use App\Http\Controllers\Tournament\SpectateController as TournamentSpectateController;
use App\Http\Controllers\Tournament\StudentTournamentController;
use App\Models\LiveClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('login'));
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Admin ─────────────────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports');
    Route::get('/reports/students', [StudentPerformanceReportController::class, 'index'])->name('reports.students.index');
    Route::get('/reports/students/{student}', [StudentPerformanceReportController::class, 'show'])->name('reports.students.show');
    Route::post('/reports/students/{student}/assessments', [StudentPerformanceReportController::class, 'storeAssessment'])->name('reports.students.assessments.store');
    Route::get('/reports/students/{student}/pdf', [StudentPerformanceReportController::class, 'downloadPdf'])->name('reports.students.pdf');
    Route::get('/reports/students/{student}/saved/{assessment}', [StudentPerformanceReportController::class, 'showSaved'])->name('reports.students.saved.show');
    Route::get('/reports/students/{student}/saved/{assessment}/pdf', [StudentPerformanceReportController::class, 'downloadSavedPdf'])->name('reports.students.saved.pdf');
    Route::view('/settings', 'admin.settings')->name('settings');

    // Archer Journey gamification layer
    Route::get('/archer-journey', [Admin\ArcherJourneyController::class, 'index'])->name('archer-journey.index');
    Route::put('/archer-journey/flags/{featureFlag}', [Admin\ArcherJourneyController::class, 'updateFlag'])->name('archer-journey.flags.update');
    Route::post('/archer-journey/rules', [Admin\ArcherJourneyController::class, 'storeRule'])->name('archer-journey.rules.store');
    Route::put('/archer-journey/rules/{rule}', [Admin\ArcherJourneyController::class, 'updateRule'])->name('archer-journey.rules.update');
    Route::put('/archer-journey/levels/{level}', [Admin\ArcherJourneyController::class, 'updateLevel'])->name('archer-journey.levels.update');
    Route::post('/archer-journey/badges', [Admin\ArcherJourneyController::class, 'storeBadge'])->name('archer-journey.badges.store');
    Route::post('/archer-journey/badges/{badge}/award', [Admin\ArcherJourneyController::class, 'awardBadge'])->name('archer-journey.badges.award');
    Route::put('/archer-journey/badges/{badge}', [Admin\ArcherJourneyController::class, 'updateBadge'])->name('archer-journey.badges.update');
    Route::delete('/archer-journey/badges/{badge}', [Admin\ArcherJourneyController::class, 'destroyBadge'])->name('archer-journey.badges.destroy');
    Route::post('/archer-journey/missions', [Admin\ArcherJourneyController::class, 'storeMission'])->name('archer-journey.missions.store');
    Route::put('/archer-journey/missions/{mission}', [Admin\ArcherJourneyController::class, 'updateMission'])->name('archer-journey.missions.update');
    Route::delete('/archer-journey/missions/{mission}', [Admin\ArcherJourneyController::class, 'destroyMission'])->name('archer-journey.missions.destroy');
    Route::post('/archer-journey/challenges', [Admin\ArcherJourneyController::class, 'storeChallenge'])->name('archer-journey.challenges.store');
    Route::put('/archer-journey/challenges/{challenge}', [Admin\ArcherJourneyController::class, 'updateChallenge'])->name('archer-journey.challenges.update');
    Route::delete('/archer-journey/challenges/{challenge}', [Admin\ArcherJourneyController::class, 'destroyChallenge'])->name('archer-journey.challenges.destroy');
    Route::put('/archer-journey/ratings/{student}', [Admin\ArcherJourneyController::class, 'importRating'])->name('archer-journey.ratings.import');
    Route::post('/archer-journey/stars/{student}', [Admin\ArcherJourneyController::class, 'adjustStars'])->name('archer-journey.stars.adjust');
    Route::post('/archer-journey/transactions/{transaction}/reverse', [Admin\ArcherJourneyController::class, 'reverseTransaction'])->name('archer-journey.transactions.reverse');
    Route::put('/archer-journey/houses/{house}', [Admin\ArcherJourneyController::class, 'updateHouse'])->name('archer-journey.houses.update');
    Route::put('/archer-journey/houses/{house}/students/{student}', [Admin\ArcherJourneyController::class, 'assignHouse'])->name('archer-journey.houses.assign');
    Route::get('/archer-discovery', [ArcherDiscoveryController::class, 'index'])->name('archer-discovery.index');
    Route::post('/archer-discovery', [ArcherDiscoveryController::class, 'store'])->name('archer-discovery.store');
    Route::put('/archer-discovery/{snapshot}', [ArcherDiscoveryController::class, 'update'])->name('archer-discovery.update');

    // Students
    Route::get('/students', [Admin\UserController::class, 'students'])->name('students');
    Route::get('/students/create', [Admin\UserController::class, 'createStudent'])->name('students.create');
    Route::post('/students', [Admin\UserController::class, 'storeStudent'])->name('students.store');
    // Bulk import / export — must come BEFORE the {user} route below
    Route::get('/students/export', [Admin\UserController::class, 'exportStudents'])->name('students.export');
    Route::post('/students/import', [Admin\UserController::class, 'importStudents'])->name('students.import');
    Route::get('/students/{user}/edit', [Admin\UserController::class, 'editStudent'])->name('students.edit');
    Route::put('/students/{user}', [Admin\UserController::class, 'updateStudent'])->name('students.update');
    Route::post('/students/{user}/assign-groups', [Admin\UserController::class, 'assignStudentGroups'])->name('students.assign-groups');
    Route::post('/users/{user}/toggle', [Admin\UserController::class, 'toggleActive'])->name('users.toggle');

    // Staff
    Route::get('/staff', [Admin\UserController::class, 'staff'])->name('staff');
    Route::post('/staff', [Admin\UserController::class, 'storeStaff'])->name('staff.store');
    Route::get('/staff/{user}/edit', [Admin\UserController::class, 'editStaff'])->name('staff.edit');
    Route::put('/staff/{user}', [Admin\UserController::class, 'updateStaff'])->name('staff.update');

    // Coaches
    Route::get('/coaches', [Admin\UserController::class, 'coaches'])->name('coaches');
    Route::post('/coaches', [Admin\UserController::class, 'storeCoach'])->name('coaches.store');
    Route::get('/coaches/{user}/edit', [Admin\UserController::class, 'editCoach'])->name('coaches.edit');
    Route::put('/coaches/{user}', [Admin\UserController::class, 'updateCoach'])->name('coaches.update');
    Route::post('/coaches/{user}/assign-groups', [Admin\UserController::class, 'assignCoachGroups'])->name('coaches.assign-groups');

    // Groups
    Route::get('/groups', [Admin\GroupController::class, 'index'])->name('groups');
    Route::get('/groups/create', [Admin\GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [Admin\GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [Admin\GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group}/edit', [Admin\GroupController::class, 'edit'])->name('groups.edit');
    Route::put('/groups/{group}', [Admin\GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [Admin\GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groups/{group}/toggle-status', [Admin\GroupController::class, 'toggleStatus'])->name('groups.toggle-status');
    Route::post('/groups/{group}/add-student', [Admin\GroupController::class, 'addStudent'])->name('groups.add-student');
    Route::post('/groups/{group}/remove-student', [Admin\GroupController::class, 'removeStudent'])->name('groups.remove-student');
    Route::post('/groups/{group}/assign-students', [Admin\GroupController::class, 'assignStudents'])->name('groups.assign-students');
    Route::post('/groups/{group}/chapters', [Admin\GroupController::class, 'assignChapters'])->name('groups.assign-chapters');

    // PGN Library
    Route::get('/library', [Admin\LibraryController::class, 'index'])->name('library');
    Route::get('/library/chapter/{chapter}', [Admin\LibraryController::class, 'viewChapter'])->name('library.chapter');
    Route::post('/library/folder', [Admin\LibraryController::class, 'createFolder'])->name('library.folder.create');
    Route::post('/library/upload', [Admin\LibraryController::class, 'uploadPgn'])->name('library.upload');
    Route::get('/library/chapter/{chapter}/download', [Admin\LibraryController::class, 'downloadChapter'])->name('library.chapter.download');
    Route::put('/library/chapter/{chapter}/puzzle/{puzzleIndex}', [Admin\LibraryController::class, 'updateChapterPuzzle'])->name('library.chapter.puzzle.update');
    Route::put('/library/chapter/{chapter}', [Admin\LibraryController::class, 'updateChapter'])->name('library.chapter.update');
    Route::delete('/library/chapter/{chapter}', [Admin\LibraryController::class, 'deleteChapter'])->name('library.chapter.delete');
    Route::delete('/library/folder/{folder}', [Admin\LibraryController::class, 'deleteFolder'])->name('library.folder.delete');

    // Database (coach assignment)
    Route::get('/database', [Admin\LibraryController::class, 'database'])->name('database');
    Route::post('/database/assign', [Admin\LibraryController::class, 'assignDatabase'])->name('database.assign');
    Route::delete('/database/{assignment}', [Admin\LibraryController::class, 'revokeDatabase'])->name('database.revoke');

    // Classes
    Route::get('/classes', [Admin\ClassesController::class, 'index'])->name('classes');
    Route::get('/classes/{class}', [Admin\ClassesController::class, 'show'])->name('classes.show');
    Route::post('/classes/{class}/change-coach', [Admin\ClassesController::class, 'changeCoach'])->name('classes.change-coach');
    Route::post('/classes/{class}/end', [Coach\ClassController::class, 'end'])->name('class.end');

    // Homework
    Route::get('/homework', [Admin\HomeworkController::class, 'index'])->name('homework');
    Route::get('/homework/{homework}', [Admin\HomeworkController::class, 'show'])->name('homework.show');

    // Tournaments
    Route::get('/tournaments', [AdminTournamentController::class, 'index'])->name('tournaments.index');
    Route::get('/tournaments/create', [AdminTournamentController::class, 'create'])->name('tournaments.create');
    Route::post('/tournaments', [AdminTournamentController::class, 'store'])->name('tournaments.store');
    Route::get('/tournaments/{tournament}/edit', [AdminTournamentController::class, 'edit'])->name('tournaments.edit');
    Route::put('/tournaments/{tournament}', [AdminTournamentController::class, 'update'])->name('tournaments.update');
    Route::get('/tournaments/{tournament}', [AdminTournamentController::class, 'show'])->name('tournaments.show');
    Route::post('/tournaments/{tournament}/cancel', [AdminTournamentController::class, 'cancel'])->name('tournaments.cancel');
    Route::delete('/tournaments/{tournament}', [AdminTournamentController::class, 'destroy'])->name('tournaments.destroy');

    // Spectator + PGN download (admin can watch and download any pairing)
    Route::get('/tournaments/{tournament}/watch/{pairing}', [TournamentSpectateController::class, 'view'])->name('tournaments.spectate');
    Route::get('/tournaments/{tournament}/watch/{pairing}/poll', [TournamentSpectateController::class, 'poll'])->name('tournaments.spectate-poll');
    Route::get('/tournaments/{tournament}/pairings/{pairing}/pgn', [TournamentSpectateController::class, 'downloadPgn'])->name('tournaments.download-pgn');

    // Quizzes — bank
    Route::get('/quizzes', [AdminQuizController::class, 'index'])->name('quizzes.index');
    Route::get('/quizzes/import', [AdminQuizController::class, 'importForm'])->name('quizzes.import');
    Route::post('/quizzes/import', [AdminQuizController::class, 'importCsv'])->name('quizzes.import.store');
    Route::get('/quizzes/template', [AdminQuizController::class, 'downloadTemplate'])->name('quizzes.template');
    Route::get('/quizzes/create', [AdminQuizController::class, 'create'])->name('quizzes.create');
    Route::post('/quizzes', [AdminQuizController::class, 'store'])->name('quizzes.store');
    Route::get('/quizzes/{quiz}/edit', [AdminQuizController::class, 'edit'])->name('quizzes.edit');
    Route::put('/quizzes/{quiz}', [AdminQuizController::class, 'update'])->name('quizzes.update');
    Route::delete('/quizzes/{quiz}', [AdminQuizController::class, 'destroy'])->name('quizzes.destroy');

    // Quiz sessions
    Route::get('/quiz-sessions', [AdminQuizSessionController::class, 'index'])->name('quiz-sessions.index');
    Route::get('/quiz-sessions/create', [AdminQuizSessionController::class, 'create'])->name('quiz-sessions.create');
    Route::post('/quiz-sessions', [AdminQuizSessionController::class, 'store'])->name('quiz-sessions.store');
    Route::get('/quiz-sessions/{quizSession}/edit', [AdminQuizSessionController::class, 'edit'])->name('quiz-sessions.edit');
    Route::put('/quiz-sessions/{quizSession}', [AdminQuizSessionController::class, 'update'])->name('quiz-sessions.update');
    Route::get('/quiz-sessions/{quizSession}', [AdminQuizSessionController::class, 'show'])->name('quiz-sessions.show');
    Route::post('/quiz-sessions/{quizSession}/cancel', [AdminQuizSessionController::class, 'cancel'])->name('quiz-sessions.cancel');
    Route::delete('/quiz-sessions/{quizSession}', [AdminQuizSessionController::class, 'destroy'])->name('quiz-sessions.destroy');

    // Puzzle library
    Route::get('/puzzle-library', [AdminPuzzleLibraryController::class, 'index'])->name('puzzle-library.index');
    Route::get('/puzzle-library/create', [AdminPuzzleLibraryController::class, 'create'])->name('puzzle-library.create');
    Route::post('/puzzle-library/preview', [AdminPuzzleLibraryController::class, 'preview'])->name('puzzle-library.preview');
    Route::post('/puzzle-library', [AdminPuzzleLibraryController::class, 'store'])->name('puzzle-library.store');
    Route::put('/puzzle-library/{puzzleSet}/import', [AdminPuzzleLibraryController::class, 'importPgn'])->name('puzzle-library.import');
    Route::put('/puzzle-library/{puzzleSet}/puzzles/{puzzleIndex}', [AdminPuzzleLibraryController::class, 'updatePuzzleAnswer'])->name('puzzle-library.puzzle.update');
    Route::get('/puzzle-library/{puzzleSet}', [AdminPuzzleLibraryController::class, 'show'])->name('puzzle-library.show');
    Route::get('/puzzle-library/{puzzleSet}/edit', [AdminPuzzleLibraryController::class, 'edit'])->name('puzzle-library.edit');
    Route::put('/puzzle-library/{puzzleSet}', [AdminPuzzleLibraryController::class, 'update'])->name('puzzle-library.update');
    Route::post('/puzzle-library/{puzzleSet}/toggle-status', [AdminPuzzleLibraryController::class, 'toggleStatus'])->name('puzzle-library.toggle-status');
});

// ── Staff ─────────────────────────────────────────────────────────────────────
Route::prefix('staff')->name('staff.')->middleware(['auth', 'role:staff'])->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::view('/settings', 'admin.settings')->name('settings');

    Route::get('/reports', [StudentPerformanceReportController::class, 'index'])->name('reports');
    Route::get('/reports/students', [StudentPerformanceReportController::class, 'index'])->name('reports.students.index');
    Route::get('/reports/students/{student}', [StudentPerformanceReportController::class, 'show'])->name('reports.students.show');
    Route::get('/reports/students/{student}/pdf', [StudentPerformanceReportController::class, 'downloadPdf'])->name('reports.students.pdf');
    Route::get('/reports/students/{student}/saved/{assessment}', [StudentPerformanceReportController::class, 'showSaved'])->name('reports.students.saved.show');
    Route::get('/reports/students/{student}/saved/{assessment}/pdf', [StudentPerformanceReportController::class, 'downloadSavedPdf'])->name('reports.students.saved.pdf');
    Route::get('/students', [Admin\UserController::class, 'students'])->name('students');
    Route::get('/students/create', [Admin\UserController::class, 'createStudent'])->name('students.create');
    Route::post('/students', [Admin\UserController::class, 'storeStudent'])->name('students.store');
    Route::get('/students/export', [Admin\UserController::class, 'exportStudents'])->name('students.export');
    Route::post('/students/import', [Admin\UserController::class, 'importStudents'])->name('students.import');
    Route::post('/students/{user}/assign-groups', [Admin\UserController::class, 'assignStudentGroups'])->name('students.assign-groups');
    Route::post('/users/{user}/toggle', [Admin\UserController::class, 'toggleActive'])->name('users.toggle');
    Route::get('/coaches', [Admin\UserController::class, 'coaches'])->name('coaches');
    Route::post('/coaches', [Admin\UserController::class, 'storeCoach'])->name('coaches.store');
    Route::post('/coaches/{user}/assign-groups', [Admin\UserController::class, 'assignCoachGroups'])->name('coaches.assign-groups');
    Route::get('/groups', [Admin\GroupController::class, 'index'])->name('groups');
    Route::get('/groups/create', [Admin\GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [Admin\GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [Admin\GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group}/edit', [Admin\GroupController::class, 'edit'])->name('groups.edit');
    Route::put('/groups/{group}', [Admin\GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [Admin\GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groups/{group}/toggle-status', [Admin\GroupController::class, 'toggleStatus'])->name('groups.toggle-status');
    Route::post('/groups/{group}/add-student', [Admin\GroupController::class, 'addStudent'])->name('groups.add-student');
    Route::post('/groups/{group}/remove-student', [Admin\GroupController::class, 'removeStudent'])->name('groups.remove-student');
    Route::post('/groups/{group}/assign-students', [Admin\GroupController::class, 'assignStudents'])->name('groups.assign-students');
    Route::post('/groups/{group}/chapters', [Admin\GroupController::class, 'assignChapters'])->name('groups.assign-chapters');
    Route::get('/classes', [Admin\ClassesController::class, 'index'])->name('classes');
    Route::get('/classes/{class}', [Admin\ClassesController::class, 'show'])->name('classes.show');
    Route::post('/classes/{class}/change-coach', [Admin\ClassesController::class, 'changeCoach'])->name('classes.change-coach');
    Route::post('/classes/{class}/end', [Coach\ClassController::class, 'end'])->name('class.end');

    // Homework progress and coach reviews are view-only for staff.
    Route::get('/homework', [Admin\HomeworkController::class, 'index'])->name('homework');
    Route::get('/homework/{homework}', [Admin\HomeworkController::class, 'show'])->name('homework.show');

    // Database is strictly read-only for staff.
    Route::get('/database', fn () => redirect()->route('staff.library'))->name('database');
    Route::get('/library', [Admin\LibraryController::class, 'index'])->name('library');
    Route::get('/library/chapter/{chapter}', [Admin\LibraryController::class, 'viewChapter'])->name('library.chapter');
    Route::get('/quizzes', [AdminQuizController::class, 'index'])->name('quizzes.index');
    Route::get('/puzzle-library', [AdminPuzzleLibraryController::class, 'index'])->name('puzzle-library.index');
    Route::get('/puzzle-library/{puzzleSet}', [AdminPuzzleLibraryController::class, 'show'])->name('puzzle-library.show');

    // Tournaments
    Route::get('/tournaments', [AdminTournamentController::class, 'index'])->name('tournaments.index');
    Route::get('/tournaments/create', [AdminTournamentController::class, 'create'])->name('tournaments.create');
    Route::post('/tournaments', [AdminTournamentController::class, 'store'])->name('tournaments.store');
    Route::get('/tournaments/{tournament}/edit', [AdminTournamentController::class, 'edit'])->name('tournaments.edit');
    Route::put('/tournaments/{tournament}', [AdminTournamentController::class, 'update'])->name('tournaments.update');
    Route::get('/tournaments/{tournament}', [AdminTournamentController::class, 'show'])->name('tournaments.show');
    Route::post('/tournaments/{tournament}/cancel', [AdminTournamentController::class, 'cancel'])->name('tournaments.cancel');
    Route::delete('/tournaments/{tournament}', [AdminTournamentController::class, 'destroy'])->name('tournaments.destroy');

    // Spectator + PGN download
    Route::get('/tournaments/{tournament}/watch/{pairing}', [TournamentSpectateController::class, 'view'])->name('tournaments.spectate');
    Route::get('/tournaments/{tournament}/watch/{pairing}/poll', [TournamentSpectateController::class, 'poll'])->name('tournaments.spectate-poll');
    Route::get('/tournaments/{tournament}/pairings/{pairing}/pgn', [TournamentSpectateController::class, 'downloadPgn'])->name('tournaments.download-pgn');

    // Quiz sessions (staff can schedule sessions, but ONLY admin manages the bank)
    Route::get('/quiz-sessions', [AdminQuizSessionController::class, 'index'])->name('quiz-sessions.index');
    Route::get('/quiz-sessions/create', [AdminQuizSessionController::class, 'create'])->name('quiz-sessions.create');
    Route::post('/quiz-sessions', [AdminQuizSessionController::class, 'store'])->name('quiz-sessions.store');
    Route::get('/quiz-sessions/{quizSession}/edit', [AdminQuizSessionController::class, 'edit'])->name('quiz-sessions.edit');
    Route::put('/quiz-sessions/{quizSession}', [AdminQuizSessionController::class, 'update'])->name('quiz-sessions.update');
    Route::get('/quiz-sessions/{quizSession}', [AdminQuizSessionController::class, 'show'])->name('quiz-sessions.show');
    Route::post('/quiz-sessions/{quizSession}/cancel', [AdminQuizSessionController::class, 'cancel'])->name('quiz-sessions.cancel');
    Route::delete('/quiz-sessions/{quizSession}', [AdminQuizSessionController::class, 'destroy'])->name('quiz-sessions.destroy');
});

// ── Coach ─────────────────────────────────────────────────────────────────────
Route::prefix('coach')->name('coach.')->middleware(['auth', 'role:coach'])->group(function () {
    Route::get('/reports', [StudentPerformanceReportController::class, 'index'])->name('reports');
    Route::get('/reports/students', [StudentPerformanceReportController::class, 'index'])->name('reports.students.index');
    Route::get('/reports/students/{student}', [StudentPerformanceReportController::class, 'show'])->name('reports.students.show');
    Route::post('/reports/students/{student}/assessments', [StudentPerformanceReportController::class, 'storeAssessment'])->name('reports.students.assessments.store');
    Route::get('/reports/students/{student}/pdf', [StudentPerformanceReportController::class, 'downloadPdf'])->name('reports.students.pdf');
    Route::get('/reports/students/{student}/saved/{assessment}', [StudentPerformanceReportController::class, 'showSaved'])->name('reports.students.saved.show');
    Route::get('/reports/students/{student}/saved/{assessment}/pdf', [StudentPerformanceReportController::class, 'downloadSavedPdf'])->name('reports.students.saved.pdf');
    Route::get('/achievements', [Coach\ArcherAchievementController::class, 'index'])->name('achievements.index');
    Route::post('/achievements', [Coach\ArcherAchievementController::class, 'store'])->name('achievements.store');
    Route::get('/archer-discovery', [ArcherDiscoveryController::class, 'index'])->name('archer-discovery.index');
    Route::post('/archer-discovery', [ArcherDiscoveryController::class, 'store'])->name('archer-discovery.store');
    Route::put('/archer-discovery/{snapshot}', [ArcherDiscoveryController::class, 'update'])->name('archer-discovery.update');

    // Classes
    Route::get('/classes', [Coach\ClassController::class, 'index'])->name('classes');
    Route::get('/classes/create', [Coach\ClassController::class, 'create'])->name('classes.create');
    Route::post('/classes', [Coach\ClassController::class, 'store'])->name('classes.store');
    Route::post('/classes/{class}/start', [Coach\ClassController::class, 'start'])->name('class.start');
    Route::get('/classes/{class}/live', [Coach\ClassController::class, 'live'])->name('class.live');
    Route::post('/classes/{class}/end', [Coach\ClassController::class, 'end'])->name('class.end');
    Route::post('/classes/{class}/board', [Coach\ClassController::class, 'updateBoard'])->name('class.board');
    Route::get('/groups/{group}/chapters', [Coach\ClassController::class, 'getChaptersForGroup'])->name('groups.chapters');

    // Presence + chat (coach side)
    Route::get('/classes/{class}/roster', [LivePresence::class, 'roster'])->name('class.roster');
    Route::get('/classes/{class}/chat', fn (LiveClass $class, Request $r) => app(LiveChat::class)->list($r, $class, 'coach'))->name('class.chat.list');
    Route::post('/classes/{class}/chat', fn (LiveClass $class, Request $r) => app(LiveChat::class)->send($r, $class, 'coach'))->name('class.chat.send');

    // Cumulative class leaderboard (always-on, totals across every question)
    Route::get('/classes/{class}/leaderboard', fn (LiveClass $class) => app(LiveLeaderboard::class)->show($class, 'coach'))->name('class.leaderboard');

    // Live-class questions / puzzles
    Route::post('/classes/{class}/questions', [Coach\QuestionController::class, 'store'])->name('class.question.store');
    Route::post('/classes/{class}/questions/end', [Coach\QuestionController::class, 'end'])->name('class.question.end');
    Route::get('/classes/{class}/questions/active', [Coach\QuestionController::class, 'activeWithAttempts'])->name('class.question.active');

    // Homework
    Route::get('/homework', [Coach\HomeworkController::class, 'index'])->name('homework');
    Route::get('/homework/{homework}', [Coach\HomeworkController::class, 'show'])->name('homework.show');
    Route::post('/homework/submission/{submission}/mark', [Coach\HomeworkController::class, 'markSubmission'])->name('homework.mark');

    // Database (view-only, no download)
    Route::get('/database', [Coach\DatabaseController::class, 'index'])->name('database');
    Route::get('/database/chapter/{chapter}', [Coach\DatabaseController::class, 'viewChapter'])->name('database.chapter');

    // Tournaments
    Route::get('/tournaments', [AdminTournamentController::class, 'index'])->name('tournaments.index');
    Route::get('/tournaments/create', [AdminTournamentController::class, 'create'])->name('tournaments.create');
    Route::post('/tournaments', [AdminTournamentController::class, 'store'])->name('tournaments.store');
    Route::get('/tournaments/{tournament}/edit', [AdminTournamentController::class, 'edit'])->name('tournaments.edit');
    Route::put('/tournaments/{tournament}', [AdminTournamentController::class, 'update'])->name('tournaments.update');
    Route::get('/tournaments/{tournament}', [AdminTournamentController::class, 'show'])->name('tournaments.show');
    Route::post('/tournaments/{tournament}/cancel', [AdminTournamentController::class, 'cancel'])->name('tournaments.cancel');
    Route::delete('/tournaments/{tournament}', [AdminTournamentController::class, 'destroy'])->name('tournaments.destroy');

    // Spectator + PGN download (coach can watch and download any pairing)
    Route::get('/tournaments/{tournament}/watch/{pairing}', [TournamentSpectateController::class, 'view'])->name('tournaments.spectate');
    Route::get('/tournaments/{tournament}/watch/{pairing}/poll', [TournamentSpectateController::class, 'poll'])->name('tournaments.spectate-poll');
    Route::get('/tournaments/{tournament}/pairings/{pairing}/pgn', [TournamentSpectateController::class, 'downloadPgn'])->name('tournaments.download-pgn');

    // Quiz sessions — coach lobby + live broadcast
    Route::get('/quiz-sessions', [CoachQuizSessionController::class, 'index'])->name('quiz-sessions.index');
    Route::get('/quiz-sessions/{quizSession}', [CoachQuizSessionController::class, 'show'])->name('quiz-sessions.show');
    Route::post('/quiz-sessions/{quizSession}/start', [CoachQuizSessionController::class, 'start'])->name('quiz-sessions.start');
    Route::post('/quiz-sessions/{quizSession}/broadcast', [CoachQuizSessionController::class, 'broadcast'])->name('quiz-sessions.broadcast');
    Route::post('/quiz-sessions/{quizSession}/end-current', [CoachQuizSessionController::class, 'endCurrent'])->name('quiz-sessions.end-current');
    Route::post('/quiz-sessions/{quizSession}/end', [CoachQuizSessionController::class, 'endSession'])->name('quiz-sessions.end');
    Route::get('/quiz-sessions/{quizSession}/poll', [CoachQuizSessionController::class, 'pollCoach'])->name('quiz-sessions.poll');
});

// ── Student ───────────────────────────────────────────────────────────────────
Route::prefix('student')->name('student.')->middleware(['auth', 'role:student', 'active_tournament_game'])->group(function () {
    Route::get('/archer-journey', [Student\ArcherJourneyController::class, 'show'])->name('archer-journey');
    Route::post('/archer-journey/notifications/read', [Student\ArcherJourneyController::class, 'readNotifications'])->name('archer-journey.notifications.read');
    Route::put('/archer-journey/notifications/preferences', [Student\ArcherJourneyController::class, 'updateNotificationPreferences'])->name('archer-journey.notifications.preferences');
    Route::get('/reports', [StudentPerformanceReportController::class, 'index'])->name('reports');
    Route::get('/reports/performance', [StudentPerformanceReportController::class, 'showOwn'])->name('reports.performance');
    Route::get('/reports/performance/pdf', [StudentPerformanceReportController::class, 'downloadOwnPdf'])->name('reports.performance.pdf');
    Route::get('/reports/saved/{assessment}', [StudentPerformanceReportController::class, 'showOwnSaved'])->name('reports.saved.show');
    Route::get('/reports/saved/{assessment}/pdf', [StudentPerformanceReportController::class, 'downloadOwnSavedPdf'])->name('reports.saved.pdf');
    Route::get('/classes', [Student\ClassController::class, 'index'])->name('classes');
    Route::get('/classes/{class}/join', [Student\ClassController::class, 'join'])->name('class.join');
    Route::get('/classes/{class}/poll', [Student\ClassController::class, 'pollBoard'])->name('class.poll');

    // Heartbeat + chat (student side)
    Route::post('/classes/{class}/heartbeat', [LivePresence::class, 'heartbeat'])->name('class.heartbeat');
    Route::get('/classes/{class}/chat', fn (LiveClass $class, Request $r) => app(LiveChat::class)->list($r, $class, 'student'))->name('class.chat.list');
    Route::post('/classes/{class}/chat', fn (LiveClass $class, Request $r) => app(LiveChat::class)->send($r, $class, 'student'))->name('class.chat.send');

    // Cumulative class leaderboard (same totals as the coach sees)
    Route::get('/classes/{class}/leaderboard', fn (LiveClass $class) => app(LiveLeaderboard::class)->show($class, 'student'))->name('class.leaderboard');

    Route::get('/homework', [Student\HomeworkController::class, 'index'])->name('homework');
    Route::get('/homework/{homework}/solve', [Student\HomeworkController::class, 'solve'])->name('homework.solve');
    Route::post('/homework/{homework}/submit', [Student\HomeworkController::class, 'submit'])->name('homework.submit');

    // Analyse — student uploads a PGN, opens it as a saved study, gets engine + AI annotations
    Route::get('/analyse', [Student\AnalyseController::class, 'index'])->name('analyse');
    Route::post('/analyse', [Student\AnalyseController::class, 'store'])->name('analyse.store');
    Route::get('/analyse/{analysis}', [Student\AnalyseController::class, 'show'])->name('analyse.show');
    Route::put('/analyse/{analysis}/reviews', [Student\AnalyseController::class, 'updateReview'])->name('analyse.reviews');
    Route::post('/analyse/{analysis}/commentary', [Student\AnalyseController::class, 'commentary'])->name('analyse.commentary');
    Route::put('/analyse/{analysis}/tree', [Student\AnalyseController::class, 'saveTree'])->name('analyse.tree');
    Route::delete('/analyse/{analysis}', [Student\AnalyseController::class, 'destroy'])->name('analyse.destroy');

    // Live-class question polling + move submission
    Route::get('/classes/{class}/question/active', [Student\QuestionController::class, 'active'])->name('class.question.active');
    Route::post('/classes/{class}/question/{question}/move', [Student\QuestionController::class, 'attemptMove'])->name('class.question.move');

    // Tournaments — view list / standings / play
    Route::get('/tournaments', [StudentTournamentController::class, 'index'])->name('tournaments.index');
    Route::get('/active-game-check', [StudentTournamentController::class, 'activeGameCheck'])->name('active-game-check');
    Route::get('/tournaments/{tournament}', [StudentTournamentController::class, 'show'])->name('tournaments.show');
    Route::post('/tournaments/{tournament}/join', [StudentTournamentController::class, 'join'])->name('tournaments.join');
    Route::post('/tournaments/{tournament}/leave', [StudentTournamentController::class, 'leave'])->name('tournaments.leave');
    Route::get('/tournaments/{tournament}/play/{pairing}', [StudentTournamentController::class, 'play'])->name('tournaments.play');

    // Tournament real-time game endpoints
    Route::get('/tournaments/{tournament}/pairings/{pairing}/poll', [TournamentPlayController::class, 'poll'])->name('tournaments.poll');
    Route::post('/tournaments/{tournament}/pairings/{pairing}/move', [TournamentPlayController::class, 'move'])->name('tournaments.move');
    Route::post('/tournaments/{tournament}/pairings/{pairing}/resign', [TournamentPlayController::class, 'resign'])->name('tournaments.resign');
    Route::post('/tournaments/{tournament}/pairings/{pairing}/draw', [TournamentPlayController::class, 'drawOffer'])->name('tournaments.draw');

    // Spectate (only when student has no live game — middleware enforces that)
    Route::get('/tournaments/{tournament}/watch/{pairing}', [TournamentSpectateController::class, 'view'])->name('tournaments.spectate');
    Route::get('/tournaments/{tournament}/watch/{pairing}/poll', [TournamentSpectateController::class, 'poll'])->name('tournaments.spectate-poll');
    // PGN download — own games only (controller enforces)
    Route::get('/tournaments/{tournament}/pairings/{pairing}/pgn', [TournamentSpectateController::class, 'downloadPgn'])->name('tournaments.download-pgn');
    // Export own finished tournament game directly into the Analyse tab
    Route::post('/tournaments/{tournament}/pairings/{pairing}/export-analyse', [TournamentSpectateController::class, 'exportToAnalyse'])->name('tournaments.export-analyse');

    // Quiz sessions — student lobby + live participation
    Route::get('/quiz-sessions', [StudentQuizSessionController::class, 'index'])->name('quiz-sessions.index');
    Route::get('/quiz-sessions/{quizSession}', [StudentQuizSessionController::class, 'show'])->name('quiz-sessions.show');
    Route::post('/quiz-sessions/{quizSession}/join', [StudentQuizSessionController::class, 'join'])->name('quiz-sessions.join');
    Route::get('/quiz-sessions/{quizSession}/poll', [StudentQuizSessionController::class, 'poll'])->name('quiz-sessions.poll');
    Route::post('/quiz-sessions/{quizSession}/answer', [StudentQuizSessionController::class, 'answer'])->name('quiz-sessions.answer');

    // Puzzle library
    Route::get('/puzzle-library', [StudentPuzzleLibraryController::class, 'index'])->name('puzzle-library.index');
    Route::get('/puzzle-library/{puzzleSet}', [StudentPuzzleLibraryController::class, 'show'])->name('puzzle-library.show');
    Route::get('/puzzle-library/{puzzleSet}/puzzles/{puzzle}', [StudentPuzzleLibraryController::class, 'solve'])->name('puzzle-library.solve');
    Route::post('/puzzle-library/{puzzleSet}/puzzles/{puzzle}/move', [StudentPuzzleLibraryController::class, 'attemptMove'])->name('puzzle-library.move');
    Route::post('/puzzle-library/{puzzleSet}/puzzles/{puzzle}/hint', [StudentPuzzleLibraryController::class, 'hint'])->name('puzzle-library.hint');
});

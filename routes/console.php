<?php

use App\Models\Group;
use App\Models\LiveClass;
use App\Support\ClassScheduler;
use App\Support\TournamentScheduler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
|--------------------------------------------------------------------------
| Auto-cancel "no-show" classes every minute
|--------------------------------------------------------------------------
| If a coach hasn't started a scheduled class within 30 minutes of its
| scheduled_for time, mark it cancelled and re-sync the group so future
| scheduled rows keep pointing at the current chapter.
|
| Requires the system cron to be invoking:
|   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('classes:auto-cancel-late')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('classes:auto-end-live')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('classes:generate-schedule')
    ->hourly()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| tournaments:process
|--------------------------------------------------------------------------
| Primary exact-time tournament cron. This is the mechanism that closes
| registration, starts due tournaments, advances rounds, and completes
| events without relying on page traffic.
*/
Artisan::command('tournaments:process', function () {
    TournamentScheduler::tick();
    $this->info('Due tournaments processed.');
})->purpose('Process due tournaments, starts, timeouts, and round advancement.');

Schedule::command('tournaments:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('archer:maintain')
    ->dailyAt('00:15')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| classes:resync
|--------------------------------------------------------------------------
| Wipes every "scheduled" live_class row and rebuilds them from each
| group's weekly schedule. Use this after a timezone / connection-TZ fix
| so any wrongly-stored scheduled_for values disappear and the scheduler
| regenerates them with the correct IST wall-clock time.
|
| Run:  php artisan classes:resync
*/
Artisan::command('classes:resync', function () {
    $deleted = LiveClass::where('status', 'scheduled')->delete();
    $this->info("Deleted {$deleted} scheduled class row(s).");

    Group::with(['coach', 'chapters', 'schedules'])->get()->each(function (Group $g) {
        ClassScheduler::syncForGroup($g);
        $this->line("  · regenerated for {$g->name}");
    });

    $this->info('Resync complete. Coaches will see the next class on its real day/time.');
})->purpose('Wipe & rebuild all scheduled live_classes from group weekly schedules.');

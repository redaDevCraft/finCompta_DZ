<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
|
| The Laravel scheduler dispatches these tasks when driven by the cron:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
| Guidelines:
|   - withoutOverlapping() so a slow previous run never doubles up with
|     the next tick. Required on any task that writes.
|   - runInBackground() on long-running sweeps so the scheduler tick
|     itself returns fast and keeps its tight-schedule promise for other
|     tasks.
|   - onFailure() logging so silent scheduler drift (bad cron config,
|     storage driver down) surfaces somewhere.
*/

/*
 * Daily retention GC for completed report artifacts + their DB rows.
 *
 * Runs at 03:10 in the scheduler's timezone to stay off the on-the-hour
 * cron rush. 500-row cap per run bounds DB lock time on a large backlog;
 * if the backlog is bigger the next daily tick picks up the rest.
 */
Schedule::command('reports:sweep --limit=500')
    ->dailyAt('03:10')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->onFailure(fn () => logger()->error('Scheduled reports:sweep failed'));

/*
 * Every 15 minutes: reap any `running` ReportRun that's been running
 * longer than the job's own timeout (600s + generous slack → 60 min).
 * This catches hard worker crashes (SIGKILL, OOM, disk full) that bypass
 * the framework's failed() hook. At 15-min cadence the worst case for a
 * stuck row is ~75 min before the user sees "failed — retry".
 */
Schedule::command('reports:reap-stuck --older-than=60 --limit=200')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onFailure(fn () => logger()->error('Scheduled reports:reap-stuck failed'));

Schedule::command('subscriptions:apply-scheduled-changes --limit=500')
    ->dailyAt('02:30')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->onFailure(fn () => logger()->error('Scheduled subscriptions:apply-scheduled-changes failed'));

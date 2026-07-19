<?php

use App\Models\ApiIdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Not a cleanup job — this is how Thawani payments settle. There is no webhook, so the
 * browser return is the only push we get, and it only happens if the guardian actually comes
 * back. Without this schedule, anyone who pays and then closes the tab stays unpaid with
 * their money taken.
 *
 * withoutOverlapping so a slow provider cannot stack runs on top of each other.
 */
Schedule::command('payments:reconcile')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Prune expired API idempotency records (abandoned reservations and lapsed completed replays)
 * once a day. The store is a supplement to the domain-level payment idempotency, so pruning it
 * is pure housekeeping and never affects an in-flight window.
 */
Schedule::command('model:prune', ['--model' => [ApiIdempotencyKey::class]])
    ->daily();

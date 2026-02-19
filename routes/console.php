<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Broadcasts ────────────────────────────────────────────────────────────────
// Check for scheduled broadcasts every minute and dispatch them to the queue
Schedule::command('broadcasts:process-scheduled')->everyMinute();

// ── Events ────────────────────────────────────────────────────────────────────
// Update event statuses (draft → active → completed) based on start/end dates
Schedule::command('events:update-status')->everyFiveMinutes();

// ── Tickets ───────────────────────────────────────────────────────────────────
// Delete cancelled/expired tickets (and their files) older than 7 days
Schedule::command('tickets:delete-old')->daily();

// ── Logs ──────────────────────────────────────────────────────────────────────
// Clean email broadcast logs, audit logs, and webhook logs older than 14 days
Schedule::command('logs:clean --days=14')->daily();

// ── Articles ──────────────────────────────────────────────────────────────────
Schedule::command('articles:warm-cache')->everyThirtyMinutes();
Schedule::command('articles:update-trending-scores')->everyFifteenMinutes();

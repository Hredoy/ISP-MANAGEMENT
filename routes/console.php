<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drives Demo Mode's "live router" illusion: uptime/traffic ticks, random session
// connect/disconnect, and log entries for every router effectively in Demo Mode, across every
// tenant. Requires the server's cron to invoke `php artisan schedule:run` every minute (standard
// Laravel requirement) - not something this repo can configure on its own.
Schedule::command('tenants:run mikrotik:demo:tick')->everyMinute()->withoutOverlapping();

// Daily billing ladder (expiry reminders, throttle, auto-suspend, escalation) for every
// tenant - see App\Console\Commands\ProcessBillingExpirations for the full step breakdown.
Schedule::command('tenants:run billing:process-expirations')->dailyAt('08:00')->withoutOverlapping();

// Auto-escalates support tickets that missed their SLA deadline (urgent: 2h, normal: 24h).
Schedule::command('tenants:run tickets:escalate-overdue')->hourly()->withoutOverlapping();

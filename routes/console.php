<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily database backup at 2:00 AM (UTC)
Schedule::command('db:backup')->daily()->at('02:00');

// Prune expired rows from the database cache store. The store only evicts expired
// rows lazily when the same key is read again, so short-lived rate-limiter keys
// (login:, login-ip:, password-email:, password-reset:) from rotating IPs would
// otherwise accumulate forever in the app's SQLite DB — and get copied into every
// daily backup. Runs after the backup so the next backup sees the pruned DB.
Schedule::command('cache:prune-expired')->daily()->at('02:30');

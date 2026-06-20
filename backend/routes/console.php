<?php

use App\Jobs\CleanupExpiredInvitationCodesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new CleanupExpiredInvitationCodesJob())
    ->dailyAt('03:00')
    ->name('cleanup-expired-invitation-codes')
    ->withoutOverlapping()
    ->onOneServer();

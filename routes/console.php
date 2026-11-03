<?php

use App\Jobs\EscalateOverdueWorkItemsToSupervisors;
use App\Jobs\SendWorkItemDueSoonNotifications;
use App\Jobs\SendWorkItemOverdueNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendWorkItemDueSoonNotifications)->dailyAt('07:30');
Schedule::job(new SendWorkItemOverdueNotifications)->dailyAt('08:00');
Schedule::job(new EscalateOverdueWorkItemsToSupervisors)->dailyAt('08:30');

Artisan::command('workhub:notify-due-soon', function () {
    dispatch_sync(new SendWorkItemDueSoonNotifications);
    $this->info('Due-soon notifications dispatched.');
})->purpose('Send due-soon work item notifications');

Artisan::command('workhub:notify-overdue', function () {
    dispatch_sync(new SendWorkItemOverdueNotifications);
    $this->info('Overdue notifications dispatched.');
})->purpose('Send overdue work item notifications');

Artisan::command('workhub:escalate-overdue', function () {
    dispatch_sync(new EscalateOverdueWorkItemsToSupervisors);
    $this->info('Overdue escalations dispatched.');
})->purpose('Escalate overdue items to department supervisors');

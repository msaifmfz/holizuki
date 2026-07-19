<?php

use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AuthorActivityEvent;
use App\Domain\Analytics\Models\AuthorProductEvent;
use App\Domain\Reading\Models\PostView;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('posts:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('model:prune', ['--model' => [PostView::class]])
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('posts:prune-media')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('community:prune')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('analytics:sync-recent')
    ->everyFourHours()
    ->withoutOverlapping(90)
    ->onOneServer();

Schedule::command('analytics:sync-daily')
    ->dailyAt('02:15')
    ->withoutOverlapping(90)
    ->onOneServer();

Schedule::command('analytics:generate-insights')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('analytics:evaluate-milestones')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function (): void {
    AnalyticsSyncRun::query()
        ->where('completed_at', '<', now()->subDays(180))
        ->delete();
    AuthorActivityEvent::query()
        ->where('expires_at', '<=', now())
        ->delete();
    AuthorProductEvent::query()
        ->where('expires_at', '<=', now())
        ->delete();
})->name('analytics:prune-operational-data')->daily()->withoutOverlapping()->onOneServer();

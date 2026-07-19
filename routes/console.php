<?php

use App\Models\PostView;
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

<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

it('serializes every scheduled task across rolling application pods', function (): void {
    $events = resolve(Schedule::class)->events();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event->onOneServer)->toBeTrue()
            ->and($event->withoutOverlapping)->toBeTrue();
    }
});

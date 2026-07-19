<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Jobs;

use App\Domain\Analytics\Actions\SyncAnalyticsRange;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SyncAnalyticsMonth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 80;

    public function __construct(
        public readonly string $startsOn,
        public readonly string $endsOn,
        public readonly string $command = 'analytics:backfill',
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60 + random_int(0, 15), 120 + random_int(0, 30), 240 + random_int(0, 60), 480 + random_int(0, 120)];
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping('analytics-property-sync')->shared()->releaseAfter(30)->expireAfter(90)];
    }

    public function handle(SyncAnalyticsRange $sync): void
    {
        $timezone = config()->string('app.timezone');
        $sync->handle(
            CarbonImmutable::parse($this->startsOn, $timezone)->startOfDay(),
            CarbonImmutable::parse($this->endsOn, $timezone)->startOfDay(),
            $this->command,
            $this->attempts(),
        );
    }
}

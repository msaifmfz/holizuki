<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Console;

use App\Domain\Analytics\Actions\SyncAnalyticsRange;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:sync-daily')]
#[Description('Refresh Google Analytics data for today and the previous seven days')]
class SyncDailyAnalytics extends Command
{
    public function handle(SyncAnalyticsRange $sync): int
    {
        $today = CarbonImmutable::today(config()->string('app.timezone'));
        $run = $sync->handle($today->subDays(7), $today, 'analytics:sync-daily');
        $this->components->info("Analytics sync {$run->run_id} completed with {$run->row_count} rows.");

        return self::SUCCESS;
    }
}

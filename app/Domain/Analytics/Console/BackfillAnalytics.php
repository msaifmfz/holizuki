<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Console;

use App\Domain\Analytics\Jobs\SyncAnalyticsMonth;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

#[Signature('analytics:backfill {--days=365 : Number of inclusive days to backfill} {--from= : Inclusive ISO start date} {--to= : Inclusive ISO end date}')]
#[Description('Queue calendar-month Google Analytics backfill jobs')]
class BackfillAnalytics extends Command
{
    public function handle(): int
    {
        try {
            [$startsOn, $endsOn] = $this->range();
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $jobs = [];
        $cursor = $startsOn;

        while ($cursor->lessThanOrEqualTo($endsOn)) {
            $monthEnd = $cursor->endOfMonth()->min($endsOn);
            $jobs[] = new SyncAnalyticsMonth($cursor->toDateString(), $monthEnd->toDateString());
            $cursor = $monthEnd->addDay();
        }

        Bus::chain($jobs)->dispatch();

        $jobCount = count($jobs);
        $this->components->info("Queued {$jobCount} monthly analytics backfill job(s).");

        return self::SUCCESS;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(): array
    {
        $timezone = config()->string('app.timezone');
        $from = $this->option('from');
        $to = $this->option('to');

        if (is_string($from) || is_string($to)) {
            if (! is_string($from) || ! is_string($to)) {
                throw new InvalidArgumentException('Both --from and --to are required for a custom range.');
            }

            $startsOn = CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone);
            $endsOn = CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone);
            if (! $startsOn instanceof CarbonImmutable || ! $endsOn instanceof CarbonImmutable) {
                throw new InvalidArgumentException('Custom dates must use the YYYY-MM-DD format.');
            }
        } else {
            $days = $this->daysOption();
            if ($days === null || $days < 1 || $days > 366) {
                throw new InvalidArgumentException('--days must be between 1 and 366.');
            }

            $endsOn = CarbonImmutable::today($timezone);
            $startsOn = $endsOn->subDays($days - 1);
        }

        if ($startsOn->isAfter($endsOn) || $startsOn->diffInDays($endsOn) + 1 > 366) {
            throw new InvalidArgumentException('The inclusive backfill range must contain between 1 and 366 days.');
        }

        return [$startsOn, $endsOn];
    }

    private function daysOption(): ?int
    {
        $value = $this->option('days');

        if (! is_string($value) || $value === '' || (string) (int) $value !== $value) {
            return null;
        }

        return (int) $value;
    }
}

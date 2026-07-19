<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class DashboardPeriod
{
    private function __construct(
        public string $key,
        public CarbonImmutable $startsOn,
        public CarbonImmutable $endsOn,
    ) {}

    public static function fromInput(string $period, ?string $from = null, ?string $to = null): self
    {
        $timezone = config()->string('app.timezone');
        $today = CarbonImmutable::today($timezone);

        return match ($period) {
            '7d' => new self('7d', $today->subDays(6), $today),
            '28d' => new self('28d', $today->subDays(27), $today),
            '90d' => new self('90d', $today->subDays(89), $today),
            'year' => new self('year', $today->startOfYear(), $today),
            'custom' => self::custom($from, $to, $timezone),
            default => throw new InvalidArgumentException('The dashboard period is not supported.'),
        };
    }

    public function days(): int
    {
        return (int) $this->startsOn->diffInDays($this->endsOn) + 1;
    }

    private static function custom(?string $from, ?string $to, string $timezone): self
    {
        if ($from === null || $to === null) {
            throw new InvalidArgumentException('Custom dashboard periods require both dates.');
        }

        $startsOn = CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone);
        $endsOn = CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone);

        if (! $startsOn instanceof CarbonImmutable || ! $endsOn instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Custom dashboard dates must use the YYYY-MM-DD format.');
        }

        if ($startsOn->isAfter($endsOn) || $startsOn->diffInDays($endsOn) + 1 > 366) {
            throw new InvalidArgumentException('Custom dashboard periods may contain at most 366 inclusive days.');
        }

        return new self('custom', $startsOn, $endsOn);
    }
}

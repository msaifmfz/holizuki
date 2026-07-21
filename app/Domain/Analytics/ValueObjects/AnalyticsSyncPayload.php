<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

final readonly class AnalyticsSyncPayload
{
    /**
     * @param  list<array<string, mixed>>  $dailySite
     * @param  list<array<string, mixed>>  $dailyPosts
     * @param  list<array<string, mixed>>  $dailyChannels
     * @param  list<array<string, mixed>>  $weeklySite
     * @param  list<array<string, mixed>>  $weeklyPosts
     * @param  list<array<string, mixed>>  $snapshots
     * @param  list<array<string, mixed>>  $dimensionPeriods
     * @param  list<array<string, mixed>>  $unmappedPaths
     * @param  array<string, mixed>|null  $quota
     */
    public function __construct(
        public array $dailySite,
        public array $dailyPosts,
        public array $dailyChannels,
        public array $weeklySite,
        public array $weeklyPosts,
        public array $snapshots,
        public array $dimensionPeriods,
        public array $unmappedPaths,
        public int $requestCount,
        public int $pageCount,
        public int $rowCount,
        public ?array $quota,
    ) {}
}

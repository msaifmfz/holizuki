<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

final readonly class AnalyticsReportRequest
{
    /**
     * @param  list<string>  $dimensions
     * @param  list<string>  $metrics
     * @param  array<string, string|list<string>>  $dimensionFilters
     */
    public function __construct(
        public string $startsOn,
        public string $endsOn,
        public array $dimensions,
        public array $metrics,
        public array $dimensionFilters = [],
        public int $offset = 0,
        public int $limit = 10000,
    ) {}
}

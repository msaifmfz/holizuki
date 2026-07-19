<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

final readonly class AnalyticsReportRow
{
    /**
     * @param  array<string, string>  $dimensions
     * @param  array<string, int|float>  $metrics
     */
    public function __construct(
        public array $dimensions,
        public array $metrics,
    ) {}
}

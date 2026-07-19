<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

final readonly class AnalyticsHealthResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  list<string>  $manualCorrections
     */
    public function __construct(
        public bool $healthy,
        public array $errors,
        public array $warnings,
        public array $manualCorrections,
        public ?AnalyticsPropertyState $property = null,
    ) {}
}

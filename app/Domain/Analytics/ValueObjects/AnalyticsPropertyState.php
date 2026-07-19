<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

final readonly class AnalyticsPropertyState
{
    /**
     * @param  list<string>  $customDimensions
     * @param  list<string>  $keyEvents
     * @param  list<string>  $manualCorrections
     */
    public function __construct(
        public string $propertyId,
        public string $streamId,
        public string $measurementId,
        public string $timezone,
        public array $customDimensions,
        public array $keyEvents,
        public int $retentionMonths,
        public array $manualCorrections = [],
        public bool $googleSignalsDisabled = true,
        public bool $enhancedMeasurementStreamEnabled = false,
        /** @var list<string> */
        public array $enabledEnhancedMeasurements = [],
    ) {}
}

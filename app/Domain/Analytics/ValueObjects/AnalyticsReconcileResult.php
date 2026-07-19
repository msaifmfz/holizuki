<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

final readonly class AnalyticsReconcileResult
{
    /** @param list<string> $createdDimensions */
    public function __construct(
        public array $createdDimensions,
        public bool $keyEventCreated,
        public bool $retentionUpdated,
    ) {}
}

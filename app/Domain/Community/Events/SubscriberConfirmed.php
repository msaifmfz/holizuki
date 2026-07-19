<?php

declare(strict_types=1);

namespace App\Domain\Community\Events;

final readonly class SubscriberConfirmed
{
    public function __construct(
        public int $subscriberId,
        public ?string $sourceContentKey,
        public int $activeSubscriberCount,
    ) {}
}

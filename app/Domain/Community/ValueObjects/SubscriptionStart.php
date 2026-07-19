<?php

declare(strict_types=1);

namespace App\Domain\Community\ValueObjects;

use App\Domain\Community\Models\NewsletterSubscriber;

final readonly class SubscriptionStart
{
    public function __construct(
        public NewsletterSubscriber $subscriber,
        public bool $confirmationQueued,
    ) {}
}

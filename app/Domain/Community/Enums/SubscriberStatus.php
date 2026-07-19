<?php

declare(strict_types=1);

namespace App\Domain\Community\Enums;

enum SubscriberStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';
}

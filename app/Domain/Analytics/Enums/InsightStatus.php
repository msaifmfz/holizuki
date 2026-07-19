<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

enum InsightStatus: string
{
    case Active = 'active';
    case Dismissed = 'dismissed';
    case Snoozed = 'snoozed';
    case Completed = 'completed';
}

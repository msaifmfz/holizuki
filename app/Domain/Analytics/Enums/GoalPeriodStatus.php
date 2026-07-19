<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

enum GoalPeriodStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Met = 'met';
    case Missed = 'missed';
    case Paused = 'paused';
}

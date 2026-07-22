<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AssistantTurnStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}

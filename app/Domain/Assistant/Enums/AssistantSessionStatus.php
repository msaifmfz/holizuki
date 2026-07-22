<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AssistantSessionStatus: string
{
    case Idle = 'idle';
    case Running = 'running';
}

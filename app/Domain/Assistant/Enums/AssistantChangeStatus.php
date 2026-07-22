<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AssistantChangeStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Stale = 'stale';
}

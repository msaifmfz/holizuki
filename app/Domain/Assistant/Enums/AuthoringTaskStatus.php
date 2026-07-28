<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AuthoringTaskStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}

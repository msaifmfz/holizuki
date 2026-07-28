<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AuthoringImageCompositionUsage: string
{
    case Include = 'include';
    case Exclude = 'exclude';
}

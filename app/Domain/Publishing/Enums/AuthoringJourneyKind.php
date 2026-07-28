<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum AuthoringJourneyKind: string
{
    case Creation = 'creation';
    case Improvement = 'improvement';
}

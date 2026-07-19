<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

enum InsightConfidence: string
{
    case Exploratory = 'exploratory';
    case Medium = 'medium';
    case High = 'high';
}

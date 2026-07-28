<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostMediaRole: string
{
    case Unassigned = 'unassigned';
    case Hero = 'hero';
    case Illustration = 'illustration';
    case Supporting = 'supporting';
    case Decorative = 'decorative';
}

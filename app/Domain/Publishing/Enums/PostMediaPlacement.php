<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostMediaPlacement: string
{
    case Unassigned = 'unassigned';
    case Featured = 'featured';
    case Introduction = 'introduction';
    case Body = 'body';
    case Conclusion = 'conclusion';
}

<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum AuthoringSurface: string
{
    case Guided = 'guided';
    case Advanced = 'advanced';
}

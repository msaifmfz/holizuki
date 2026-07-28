<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostSourceVisibility: string
{
    case Private = 'private';
    case Public = 'public';
}

<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostMediaStoryPriority: string
{
    case Required = 'required';
    case Optional = 'optional';
}

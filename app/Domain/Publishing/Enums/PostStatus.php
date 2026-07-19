<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

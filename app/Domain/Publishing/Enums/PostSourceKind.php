<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostSourceKind: string
{
    case Note = 'note';
    case Url = 'url';
    case Document = 'document';
}

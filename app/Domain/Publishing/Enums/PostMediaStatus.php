<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostMediaStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}

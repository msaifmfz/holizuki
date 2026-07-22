<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use RuntimeException;

class AssistantBusyException extends RuntimeException
{
    public static function forPost(): self
    {
        return new self('The assistant is already working on this post.');
    }
}

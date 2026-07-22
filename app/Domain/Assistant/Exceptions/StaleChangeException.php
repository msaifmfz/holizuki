<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use RuntimeException;

class StaleChangeException extends RuntimeException
{
    public static function becauseContentMoved(): self
    {
        return new self('The draft changed since this suggestion was made. Ask the assistant again for an up-to-date suggestion.');
    }
}

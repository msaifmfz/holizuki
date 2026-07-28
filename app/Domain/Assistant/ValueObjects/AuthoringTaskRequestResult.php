<?php

declare(strict_types=1);

namespace App\Domain\Assistant\ValueObjects;

use App\Domain\Assistant\Models\AuthoringTask;

final readonly class AuthoringTaskRequestResult
{
    public function __construct(
        public AuthoringTask $task,
        public bool $shouldDispatch,
        public bool $cached,
        public bool $resumed,
    ) {}
}

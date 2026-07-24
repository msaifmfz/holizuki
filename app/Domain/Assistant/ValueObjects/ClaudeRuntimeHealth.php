<?php

declare(strict_types=1);

namespace App\Domain\Assistant\ValueObjects;

final readonly class ClaudeRuntimeHealth
{
    /** @param list<string> $errors */
    public function __construct(
        public bool $healthy,
        public ?string $version,
        public array $errors,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\ValueObjects;

use App\Domain\Assistant\Enums\AuthoringArtifactType;

/**
 * One artifact a normalizer decided to store, before it is written.
 */
final readonly class AuthoringArtifactPayload
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public AuthoringArtifactType $type,
        public ?int $parentId,
        public int $position,
        public array $payload,
    ) {}
}

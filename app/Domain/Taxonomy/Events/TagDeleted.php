<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Events;

use App\Domain\Taxonomy\Models\Tag;

final readonly class TagDeleted
{
    /** @param list<int> $affectedPostIds */
    public function __construct(
        public Tag $tag,
        public array $affectedPostIds,
    ) {}
}

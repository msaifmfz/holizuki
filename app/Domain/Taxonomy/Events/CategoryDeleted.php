<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Events;

use App\Domain\Taxonomy\Models\Category;

final readonly class CategoryDeleted
{
    /** @param list<int> $affectedPostIds */
    public function __construct(
        public Category $category,
        public array $affectedPostIds,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Events;

use App\Domain\Taxonomy\Models\Category;

final readonly class CategorySaved
{
    public function __construct(public Category $category) {}
}

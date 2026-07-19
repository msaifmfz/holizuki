<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Events;

use App\Domain\Taxonomy\Models\Tag;

final readonly class TagSaved
{
    public function __construct(public Tag $tag) {}
}

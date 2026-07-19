<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Events;

use App\Domain\Publishing\Models\Post;

final readonly class PostFeatured
{
    public function __construct(public Post $post) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Events;

use App\Domain\Publishing\Models\Post;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PostContentUpdated implements ShouldDispatchAfterCommit
{
    public function __construct(public Post $post) {}
}

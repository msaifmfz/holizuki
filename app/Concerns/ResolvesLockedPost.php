<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Exceptions\PostEditConflictException;
use App\Models\Post;

trait ResolvesLockedPost
{
    /**
     * Re-fetch the post inside the current transaction with a row lock,
     * enforcing the optimistic lock when an expected version is given.
     *
     * @throws PostEditConflictException
     */
    protected function lockedPost(Post $post, ?int $expectedVersion = null): Post
    {
        $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

        if ($expectedVersion !== null && $current->lock_version !== $expectedVersion) {
            throw new PostEditConflictException($current->load('lastEditor:id,name'));
        }

        return $current;
    }
}

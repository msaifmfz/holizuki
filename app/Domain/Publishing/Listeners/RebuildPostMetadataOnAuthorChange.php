<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Listeners;

use App\Domain\Identity\Events\AuthorProfileUpdated;
use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Publishing\Actions\RebuildPostMetadata;
use App\Domain\Publishing\Models\Post;

class RebuildPostMetadataOnAuthorChange
{
    public function __construct(private readonly RebuildPostMetadata $rebuildPostMetadata) {}

    public function handle(AuthorProfileUpdated|UserDeleted $event): void
    {
        $affectedPosts = $event instanceof AuthorProfileUpdated
            ? Post::query()->where('author_id', $event->user->id)
            : Post::query()->whereKey($event->authoredPostIds);

        $this->rebuildPostMetadata->handleQuery($affectedPosts);
    }
}

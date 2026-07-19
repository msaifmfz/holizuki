<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesLockedPost;
use App\Enums\PostRevisionEvent;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Support\PublicCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PublishPost
{
    use ResolvesLockedPost;

    public function __construct(
        private readonly CreatePostRevision $createRevision,
        private readonly RebuildPostMetadata $rebuildPostMetadata,
    ) {}

    public function handle(Post $post, ?User $editor, ?int $expectedVersion = null, ?CarbonInterface $publishedAt = null): Post
    {
        $published = DB::transaction(function () use ($post, $editor, $expectedVersion, $publishedAt): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            // Scheduler path (no expected version): skip posts that were
            // unpublished or already published since the batch was queried.
            if ($expectedVersion === null && ! $current->isScheduled()) {
                return $current;
            }

            $current->status = PostStatus::Published;
            $current->scheduled_at = null;
            $current->published_at ??= $publishedAt ?? now();
            $current->slug_locked_at ??= now();
            $current->content_updated_at ??= now();
            $current->updated_by_id = $editor?->id;
            $current->lock_version++;
            $current->save();

            $this->rebuildPostMetadata->handle($current);
            $this->createRevision->handle($current, $editor, PostRevisionEvent::Published);

            return $current->refresh();
        });

        PublicCache::flush();

        return $published;
    }
}

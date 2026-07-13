<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Enums\PostRevisionEvent;
use App\Enums\PostStatus;
use App\Exceptions\PostEditConflictException;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PublishPost
{
    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, ?User $editor, ?int $expectedVersion = null, ?CarbonInterface $publishedAt = null): Post
    {
        return DB::transaction(function () use ($post, $editor, $expectedVersion, $publishedAt): Post {
            $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $current->lock_version !== $expectedVersion) {
                throw new PostEditConflictException($current->load('lastEditor:id,name'));
            }

            // Scheduler path (no expected version): skip posts that were
            // unpublished or already published since the batch was queried.
            if ($expectedVersion === null && ! $current->isScheduled()) {
                return $current;
            }

            $current->status = PostStatus::Published;
            $current->scheduled_at = null;
            $current->published_at = $publishedAt ?? now();
            $current->slug_locked_at ??= now();
            $current->updated_by_id = $editor?->id;
            $current->lock_version++;
            $current->save();

            $this->createRevision->handle($current, $editor, PostRevisionEvent::Published);

            return $current->refresh();
        });
    }
}

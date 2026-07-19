<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Events\PostPublished;
use App\Domain\Publishing\Models\Post;
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
        $transitioned = false;

        $published = DB::transaction(function () use ($post, $editor, $expectedVersion, $publishedAt, &$transitioned): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            // Scheduler path (no expected version): skip posts that were
            // unpublished or already published since the batch was queried.
            if ($expectedVersion === null && ! $current->isScheduled()) {
                return $current;
            }

            $current->publish($editor, $publishedAt);

            $this->rebuildPostMetadata->handle($current);
            $this->createRevision->handle($current, $editor, PostRevisionEvent::Published);

            $transitioned = true;

            return $current->refresh();
        });

        if ($transitioned) {
            event(new PostPublished($published));
        }

        return $published;
    }
}

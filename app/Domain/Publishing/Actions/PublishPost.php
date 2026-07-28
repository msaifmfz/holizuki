<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Events\PostPublished;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\ValueObjects\PostRevisionSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Promotes editable state onto the published Post. The two entry points differ
 * only in which snapshot wins: an author publishing now ships the working
 * draft, while the scheduler ships the revision frozen at schedule time.
 */
class PublishPost
{
    use ResolvesLockedPost;

    public function __construct(
        private readonly CreatePostRevision $createRevision,
        private readonly RebuildPostMetadata $rebuildPostMetadata,
        private readonly RecordSlugChange $recordSlugChange,
        private readonly SyncPostTags $syncTags,
    ) {}

    /**
     * Publish the current working draft on an author's request.
     */
    public function handle(
        Post $post,
        ?User $editor,
        int $expectedVersion,
        ?CarbonInterface $publishedAt = null,
    ): Post {
        return $this->publishFrom(
            $post,
            $editor,
            $expectedVersion,
            $publishedAt,
            static fn (Post $current): PostRevisionSnapshot => $current->draft()->firstOrFail()->snapshot(),
        );
    }

    /**
     * Publish the revision frozen when the post was scheduled. Posts that were
     * unpublished or already published since the batch was queried are skipped.
     */
    public function publishScheduled(
        Post $post,
        ?User $editor,
        ?CarbonInterface $publishedAt = null,
    ): Post {
        return $this->publishFrom(
            $post,
            $editor,
            null,
            $publishedAt,
            static fn (Post $current): ?PostRevisionSnapshot => $current->isScheduled()
                ? $current->scheduledRevision()->firstOrFail()->snapshot
                : null,
        );
    }

    /**
     * @param  callable(Post): ?PostRevisionSnapshot  $resolveSnapshot  Returning null skips the transition.
     */
    private function publishFrom(
        Post $post,
        ?User $editor,
        ?int $expectedVersion,
        ?CarbonInterface $publishedAt,
        callable $resolveSnapshot,
    ): Post {
        $transitioned = false;

        $published = DB::transaction(function () use (
            $post,
            $editor,
            $expectedVersion,
            $publishedAt,
            $resolveSnapshot,
            &$transitioned,
        ): Post {
            $current = $this->lockedPost($post, $expectedVersion);
            $snapshot = $resolveSnapshot($current);

            if (! $snapshot instanceof PostRevisionSnapshot) {
                return $current;
            }

            $previousSlug = $current->slug;
            $slugWasLocked = $current->slug_locked_at !== null;

            $current->fill($snapshot->attributes());

            if ($current->isDirty(Post::CONTENT_FIELDS)) {
                $current->content_updated_at = now();
            }

            $current->publish($editor, $publishedAt);

            $this->syncTags->handle($current, $snapshot->tags);
            $this->rebuildPostMetadata->handle($current);

            if ($slugWasLocked) {
                $this->recordSlugChange->handle($current, $previousSlug);
            }

            $this->createRevision->handle(
                $current,
                $editor,
                PostRevisionEvent::Published,
                $snapshot,
            );

            $transitioned = true;

            return $current->refresh()->load('draft', 'tags:id,name');
        });

        if ($transitioned) {
            event(new PostPublished($published));
        }

        return $published;
    }
}

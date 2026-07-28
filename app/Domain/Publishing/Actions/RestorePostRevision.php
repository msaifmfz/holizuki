<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostRevision;
use Illuminate\Support\Facades\DB;

class RestorePostRevision
{
    use ResolvesLockedPost;

    public function __construct(
        private readonly CreatePostRevision $createRevision,
        private readonly SyncPostTags $syncTags,
    ) {}

    public function handle(Post $post, PostRevision $revision, User $editor, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $revision, $editor, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);
            $revision = $current->revisions()->whereKey($revision->id)->firstOrFail();
            $snapshot = $revision->snapshot;
            $draft = $current->draft()->firstOrFail();

            $draft->fill($snapshot->attributes());
            $draft->save();
            $this->syncTags->handle($draft, $snapshot->tags);
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->createRevision->handle(
                $current,
                $editor,
                PostRevisionEvent::Restored,
                $snapshot,
            );

            return $current->refresh()->load('draft.tags:id,name');
        });
    }
}

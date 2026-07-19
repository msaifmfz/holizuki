<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesLockedPost;
use App\Enums\PostRevisionEvent;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Support\PublicCache;
use Illuminate\Support\Facades\DB;

class UnpublishPost
{
    use ResolvesLockedPost;

    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor, int $expectedVersion): Post
    {
        $unpublished = DB::transaction(function () use ($post, $editor, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            $current->status = PostStatus::Draft;
            $current->scheduled_at = null;
            $current->featured_at = null;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->createRevision->handle($current, $editor, PostRevisionEvent::Unpublished);

            return $current->refresh();
        });

        PublicCache::flush();

        return $unpublished;
    }
}

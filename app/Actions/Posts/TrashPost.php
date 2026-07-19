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

class TrashPost
{
    use ResolvesLockedPost;

    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor): void
    {
        DB::transaction(function () use ($post, $editor): void {
            $current = $this->lockedPost($post);
            $this->createRevision->handle($current, $editor, PostRevisionEvent::Deleted);

            $current->status = PostStatus::Draft;
            $current->scheduled_at = null;
            $current->featured_at = null;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();
            $current->delete();
        });

        PublicCache::flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Enums\PostRevisionEvent;
use App\Enums\PostStatus;
use App\Exceptions\PostEditConflictException;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnpublishPost
{
    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $expectedVersion): Post {
            $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($current->lock_version !== $expectedVersion) {
                throw new PostEditConflictException($current->load('lastEditor:id,name'));
            }

            $current->status = PostStatus::Draft;
            $current->scheduled_at = null;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->createRevision->handle($current, $editor, PostRevisionEvent::Unpublished);

            return $current->refresh();
        });
    }
}

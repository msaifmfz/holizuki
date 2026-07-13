<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Enums\PostRevisionEvent;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TrashPost
{
    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor): void
    {
        DB::transaction(function () use ($post, $editor): void {
            $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $this->createRevision->handle($current, $editor, PostRevisionEvent::Deleted);

            $current->status = PostStatus::Draft;
            $current->scheduled_at = null;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();
            $current->delete();
        });
    }
}

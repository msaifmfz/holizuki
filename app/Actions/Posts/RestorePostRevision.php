<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Enums\PostRevisionEvent;
use App\Exceptions\PostEditConflictException;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestorePostRevision
{
    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, PostRevision $revision, User $editor, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $revision, $editor, $expectedVersion): Post {
            $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($current->lock_version !== $expectedVersion) {
                throw new PostEditConflictException($current->load('lastEditor:id,name'));
            }

            $current->fill([
                'title' => $revision->title,
                'slug' => $revision->slug,
                'excerpt' => $revision->excerpt,
                'body' => $revision->body,
                'featured_image_path' => $revision->featured_image_path,
                'featured_image_alt' => $revision->featured_image_alt,
            ]);
            $current->slug_is_manual = true;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->createRevision->handle($current, $editor, PostRevisionEvent::Restored);

            return $current->refresh();
        });
    }
}

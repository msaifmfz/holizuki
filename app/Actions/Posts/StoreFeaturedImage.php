<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Enums\PostRevisionEvent;
use App\Exceptions\PostEditConflictException;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreFeaturedImage
{
    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor, UploadedFile $image, string $altText, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $image, $altText, $expectedVersion): Post {
            $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($current->lock_version !== $expectedVersion) {
                throw new PostEditConflictException($current->load('lastEditor:id,name'));
            }

            if ($current->featured_image_path !== null) {
                $this->createRevision->handle($current, $editor, PostRevisionEvent::ImageChanged);
            }

            $path = $image->store('posts/'.$current->id, 'public');

            if ($path === false) {
                throw new RuntimeException('The featured image could not be stored.');
            }

            try {
                $current->featured_image_path = $path;
                $current->featured_image_alt = trim($altText);
                $current->updated_by_id = $editor->id;
                $current->lock_version++;
                $current->save();
            } catch (Throwable $exception) {
                Storage::disk('public')->delete($path);

                throw $exception;
            }

            return $current->refresh();
        });
    }

    public function remove(Post $post, User $editor, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $expectedVersion): Post {
            $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($current->lock_version !== $expectedVersion) {
                throw new PostEditConflictException($current->load('lastEditor:id,name'));
            }

            if ($current->featured_image_path !== null) {
                $this->createRevision->handle($current, $editor, PostRevisionEvent::ImageChanged);
            }

            $current->featured_image_path = null;
            $current->featured_image_alt = null;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            return $current->refresh();
        });
    }
}

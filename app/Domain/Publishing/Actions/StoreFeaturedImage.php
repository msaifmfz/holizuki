<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreFeaturedImage
{
    use ResolvesLockedPost;

    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor, UploadedFile $image, string $altText, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $image, $altText, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);

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
                $current->content_updated_at = now();
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
            $current = $this->lockedPost($post, $expectedVersion);

            if ($current->status === PostStatus::Published || $current->isScheduled()) {
                throw ValidationException::withMessages([
                    'image' => __('Unpublish or cancel the schedule before removing the required featured image.'),
                ]);
            }

            if ($current->featured_image_path !== null) {
                $this->createRevision->handle($current, $editor, PostRevisionEvent::ImageChanged);
            }

            $current->featured_image_path = null;
            $current->featured_image_alt = null;
            $current->featured_image_caption = null;
            $current->content_updated_at = now();
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            return $current->refresh();
        });
    }
}

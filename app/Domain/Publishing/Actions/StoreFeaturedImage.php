<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
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
            $draft = $current->draft()->firstOrFail();

            if ($draft->featured_image_path !== null) {
                $this->createRevision->handle(
                    $current,
                    $editor,
                    PostRevisionEvent::ImageChanged,
                    $draft->snapshot(),
                );
                $this->demoteFeaturedMedia($current, $draft->featured_image_path);
            }

            $path = $image->store('posts/'.$current->id, 'public');

            if ($path === false) {
                throw new RuntimeException('The featured image could not be stored.');
            }

            try {
                $draft->featured_image_path = $path;
                $draft->featured_image_alt = trim($altText);
                $draft->save();
                $current->updated_by_id = $editor->id;
                $current->lock_version++;
                $current->save();
            } catch (Throwable $exception) {
                Storage::disk('public')->delete($path);

                throw $exception;
            }

            return $current->refresh()->load('draft');
        });
    }

    public function remove(Post $post, User $editor, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);
            $draft = $current->draft()->firstOrFail();

            if ($draft->featured_image_path !== null) {
                $this->createRevision->handle(
                    $current,
                    $editor,
                    PostRevisionEvent::ImageChanged,
                    $draft->snapshot(),
                );
                $this->demoteFeaturedMedia($current, $draft->featured_image_path);
            }

            $draft->featured_image_path = null;
            $draft->featured_image_alt = null;
            $draft->featured_image_caption = null;
            $draft->save();
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            return $current->refresh()->load('draft');
        });
    }

    public function useMedia(
        Post $post,
        PostMedia $media,
        User $editor,
        int $expectedVersion,
    ): Post {
        return DB::transaction(function () use ($post, $media, $editor, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            if ($media->post_id !== $current->id) {
                throw new LogicException('The selected image does not belong to this post.');
            }

            $draft = $current->draft()->firstOrFail();

            if ($draft->featured_image_path !== null) {
                $this->createRevision->handle(
                    $current,
                    $editor,
                    PostRevisionEvent::ImageChanged,
                    $draft->snapshot(),
                );

                if ($draft->featured_image_path !== $media->path) {
                    $this->demoteFeaturedMedia($current, $draft->featured_image_path);
                }
            }

            $draft->featured_image_path = $media->path;
            $draft->featured_image_alt = $media->alt_text;
            $draft->featured_image_caption = $media->caption;
            $draft->save();

            $media->role = PostMediaRole::Hero;
            $media->placement = PostMediaPlacement::Featured;
            $media->save();

            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            return $current->refresh()->load('draft');
        });
    }

    private function demoteFeaturedMedia(Post $post, string $path): void
    {
        PostMedia::query()
            ->whereBelongsTo($post)
            ->where('path', $path)
            ->where(function (Builder $query): void {
                $query
                    ->where('role', PostMediaRole::Hero)
                    ->orWhere('placement', PostMediaPlacement::Featured);
            })
            ->update([
                'role' => PostMediaRole::Unassigned->value,
                'placement' => PostMediaPlacement::Unassigned->value,
            ]);
    }
}

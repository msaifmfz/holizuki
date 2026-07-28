<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeletePostMedia
{
    use ResolvesLockedPost;

    public function handle(Post $post, PostMedia $media): void
    {
        DB::transaction(function () use ($post, $media): void {
            $currentPost = $this->lockedPost($post);
            $currentMedia = PostMedia::query()
                ->whereBelongsTo($currentPost)
                ->whereKey($media->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->isReferenced($currentPost, $currentMedia)) {
                throw ValidationException::withMessages([
                    'media' => __('Remove this image from the draft and its retained revisions before deleting it.'),
                ]);
            }

            $path = $currentMedia->path;
            $currentMedia->delete();
            DB::afterCommit(static fn (): bool => Storage::disk('public')->delete($path));
        });
    }

    private function isReferenced(Post $post, PostMedia $media): bool
    {
        $draft = $post->draft()->first();

        if (in_array($media->id, $post->body?->referencedMediaIds() ?? [], true)
            || in_array($media->id, $draft?->body?->referencedMediaIds() ?? [], true)
            || $post->featured_image_path === $media->path
            || $post->og_image_path === $media->path
            || $draft?->featured_image_path === $media->path
            || $draft?->og_image_path === $media->path) {
            return true;
        }

        return $post->revisions()
            ->where(fn (Builder $query): Builder => $query
                ->referencingMedia($media->id)
                ->orWhere(fn (Builder $paths): Builder => $paths->referencingPath($media->path)))
            ->exists();
    }
}

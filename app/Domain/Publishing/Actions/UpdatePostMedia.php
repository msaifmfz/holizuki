<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdatePostMedia
{
    /**
     * @param array{
     *     display_name?: string,
     *     alt_text?: string|null,
     *     caption?: string|null,
     *     role?: string,
     *     placement?: string,
     *     story_priority?: string,
     *     story_notes?: string|null
     * } $attributes
     */
    public function handle(PostMedia $media, array $attributes, User $editor): PostMedia
    {
        return DB::transaction(function () use ($media, $attributes, $editor): PostMedia {
            $post = Post::query()
                ->whereKey($media->post_id)
                ->lockForUpdate()
                ->firstOrFail();
            $currentMedia = PostMedia::query()
                ->whereBelongsTo($post)
                ->whereKey($media->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureFeaturedStateIsNotTransitioned($currentMedia, $attributes);

            if (array_key_exists('display_name', $attributes)) {
                $currentMedia->display_name = trim($attributes['display_name']);
            }

            if (array_key_exists('alt_text', $attributes)) {
                $currentMedia->alt_text = is_string($attributes['alt_text'])
                    ? trim($attributes['alt_text'])
                    : null;
            }

            if (array_key_exists('caption', $attributes)) {
                $currentMedia->caption = is_string($attributes['caption'])
                    ? trim($attributes['caption'])
                    : null;
            }

            if (array_key_exists('role', $attributes)) {
                $currentMedia->role = PostMediaRole::from($attributes['role']);
            }

            if (array_key_exists('placement', $attributes)) {
                $currentMedia->placement = PostMediaPlacement::from($attributes['placement']);
            }

            if (array_key_exists('story_priority', $attributes)) {
                $currentMedia->story_priority = PostMediaStoryPriority::from($attributes['story_priority']);
            }

            if (array_key_exists('story_notes', $attributes)) {
                $currentMedia->story_notes = is_string($attributes['story_notes'])
                    ? trim($attributes['story_notes'])
                    : null;
            }

            $currentMedia->save();

            // Rewriting the draft body behind an open editor would be silently
            // reverted by its next autosave, so bump the lock version and let
            // the client reconcile through the usual conflict path.
            if ($this->synchronizeWorkingDraftMetadata($post, $currentMedia, $attributes)) {
                $post->updated_by_id = $editor->id;
                $post->lock_version++;
                $post->save();
            }

            return $currentMedia->refresh();
        });
    }

    /**
     * @param array{
     *     alt_text?: string|null,
     *     caption?: string|null
     * } $attributes
     * @return bool Whether the draft body actually changed.
     */
    private function synchronizeWorkingDraftMetadata(
        Post $post,
        PostMedia $media,
        array $attributes,
    ): bool {
        if (! array_key_exists('alt_text', $attributes) && ! array_key_exists('caption', $attributes)) {
            return false;
        }

        $draft = $post->draft()->lockForUpdate()->firstOrFail();
        $body = $draft->body;

        if ($body === null || ! in_array($media->id, $body->referencedMediaIds(), true)) {
            return false;
        }

        /** @var array{alt?: string, caption?: string|null} $metadata */
        $metadata = [];

        if (array_key_exists('alt_text', $attributes)) {
            $metadata['alt'] = $media->alt_text ?? '';
        }

        if (array_key_exists('caption', $attributes)) {
            $metadata['caption'] = $media->caption;
        }

        $updatedBody = $body->withMediaMetadata($media->id, $metadata);

        if ($updatedBody->toArray() === $body->toArray()) {
            return false;
        }

        $draft->body = $updatedBody;
        $draft->save();

        return true;
    }

    /**
     * @param array{
     *     role?: string,
     *     placement?: string
     * } $attributes
     */
    private function ensureFeaturedStateIsNotTransitioned(
        PostMedia $media,
        array $attributes,
    ): void {
        if (array_key_exists('role', $attributes)) {
            $role = PostMediaRole::from($attributes['role']);

            if (
                $role !== $media->role
                && ($role === PostMediaRole::Hero || $media->role === PostMediaRole::Hero)
            ) {
                throw ValidationException::withMessages([
                    'role' => __('Use the featured image control to choose or remove the hero image.'),
                ]);
            }
        }

        if (array_key_exists('placement', $attributes)) {
            $placement = PostMediaPlacement::from($attributes['placement']);

            if (
                $placement !== $media->placement
                && (
                    $placement === PostMediaPlacement::Featured
                    || $media->placement === PostMediaPlacement::Featured
                )
            ) {
                throw ValidationException::withMessages([
                    'placement' => __('Use the featured image control to choose or remove the featured image.'),
                ]);
            }
        }
    }
}

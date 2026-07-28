<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostMediaStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Collection;

final class AuthoringStoryPhotoEligibility
{
    /** @return Collection<int, PostMedia> */
    public function forPost(Post $post): Collection
    {
        return PostMedia::query()
            ->whereBelongsTo($post)
            ->where('status', PostMediaStatus::Ready)
            ->where('role', '!=', PostMediaRole::Hero)
            ->where('placement', '!=', PostMediaPlacement::Featured)
            ->oldest('id')
            ->get();
    }

    public function compositionIsEligible(
        Post $post,
        AuthoringImageComposition $composition,
    ): bool {
        $composition->loadMissing('media');

        return $composition->post_id === $post->id
            && $composition->media->isNotEmpty()
            && $composition->media->every(
                fn (PostMedia $media): bool => $media->post_id === $post->id
                    && $media->status === PostMediaStatus::Ready
                    && $media->role !== PostMediaRole::Hero
                    && $media->placement !== PostMediaPlacement::Featured,
            );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Enums\AuthoringJourneyKind;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use Illuminate\Support\Facades\DB;

class SavePostAuthoringJourney
{
    /**
     * The journey as it would be, without creating one. Posts started from the
     * editor already have a journey; this covers the rest without turning a
     * read of the guided flow into a write.
     *
     * @param  array{personality?: string, custom_instructions?: string|null}  $defaults
     */
    public function preview(Post $post, array $defaults = []): PostAuthoringJourney
    {
        $journey = PostAuthoringJourney::query()->whereBelongsTo($post)->first();

        if ($journey instanceof PostAuthoringJourney) {
            return $journey;
        }

        return new PostAuthoringJourney([
            'post_id' => $post->id,
            'kind' => $post->published_at === null
                ? AuthoringJourneyKind::Creation
                : AuthoringJourneyKind::Improvement,
            ...$defaults,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{personality?: string, custom_instructions?: string|null}  $defaults
     */
    public function handle(
        Post $post,
        array $attributes = [],
        array $defaults = [],
    ): PostAuthoringJourney {
        return DB::transaction(function () use ($post, $attributes, $defaults): PostAuthoringJourney {
            $currentPost = Post::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $journey = PostAuthoringJourney::query()->firstOrCreate(
                ['post_id' => $currentPost->id],
                [
                    'kind' => $currentPost->published_at === null
                        ? AuthoringJourneyKind::Creation
                        : AuthoringJourneyKind::Improvement,
                    ...$defaults,
                ],
            );

            if ($attributes !== []) {
                $journey->update($attributes);
            }

            return $journey->refresh();
        });
    }
}

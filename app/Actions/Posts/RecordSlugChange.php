<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\PostRedirect;

class RecordSlugChange
{
    /**
     * Preserve the old URL of a slug-locked post by pointing a permanent
     * redirect at the post. Storing the post id (not the new slug) collapses
     * chains automatically: every redirect resolves to the current slug.
     */
    public function handle(Post $post, string $previousSlug): void
    {
        if ($post->slug_locked_at === null || $previousSlug === $post->slug) {
            return;
        }

        PostRedirect::query()->updateOrCreate(
            ['old_slug' => $previousSlug],
            ['post_id' => $post->id],
        );

        PostRedirect::query()->where('old_slug', $post->slug)->delete();
    }
}

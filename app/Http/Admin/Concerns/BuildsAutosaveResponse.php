<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Publishing\Models\Post;

trait BuildsAutosaveResponse
{
    /** @return array<string, mixed> */
    protected function autosavePayload(Post $post): array
    {
        $post->loadMissing('draft');
        $draftSlug = $post->draft?->slug;

        return [
            'lock_version' => $post->lock_version,
            'slug' => $draftSlug ?? $post->slug,
            'updated_at' => (string) $post->updated_at?->toISOString(),
            'last_editor' => $post->lastEditor?->name,
        ];
    }
}

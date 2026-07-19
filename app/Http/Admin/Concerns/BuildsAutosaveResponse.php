<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Publishing\Models\Post;

trait BuildsAutosaveResponse
{
    /** @return array<string, mixed> */
    protected function autosavePayload(Post $post): array
    {
        return [
            'lock_version' => $post->lock_version,
            'slug' => $post->slug,
            'updated_at' => (string) $post->updated_at?->toISOString(),
            'last_editor' => $post->lastEditor?->name,
        ];
    }
}

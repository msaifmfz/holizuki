<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Enums\PostSourceVisibility;
use App\Domain\Publishing\Models\PostSource;

final class UpdatePostSource
{
    /** @param array{title?: string, approved?: bool, visibility?: string} $attributes */
    public function handle(PostSource $source, array $attributes): PostSource
    {
        if (array_key_exists('title', $attributes)) {
            $source->title = trim($attributes['title']);
        }

        if (array_key_exists('approved', $attributes)) {
            $source->approved_at = $attributes['approved']
                ? ($source->approved_at ?? now())
                : null;
        }

        if (array_key_exists('visibility', $attributes)) {
            $source->visibility = PostSourceVisibility::from($attributes['visibility']);
        }

        $source->save();

        return $source->refresh();
    }
}

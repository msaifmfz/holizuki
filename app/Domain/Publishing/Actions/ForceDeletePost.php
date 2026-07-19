<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\Storage;

class ForceDeletePost
{
    public function handle(Post $post): void
    {
        $paths = $post->revisions()
            ->pluck('featured_image_path')
            ->merge($post->revisions()->pluck('og_image_path'))
            ->merge($post->media()->pluck('path'))
            ->push($post->featured_image_path, $post->og_image_path)
            ->filter()
            ->unique();

        $post->forceDelete();

        Storage::disk('public')->delete($paths->all());
    }
}

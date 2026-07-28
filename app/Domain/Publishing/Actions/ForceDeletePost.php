<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use App\Domain\Publishing\Models\PostRevision;
use App\Domain\Publishing\Models\PostSource;
use Illuminate\Support\Facades\Storage;

class ForceDeletePost
{
    public function handle(Post $post): void
    {
        $sourcePaths = PostSource::query()
            ->whereBelongsTo($post)
            ->whereNotNull('path')
            ->get(['path'])
            ->map(fn (PostSource $source): string => (string) $source->path)
            ->all();
        $revisionPaths = $post->revisions()
            ->get(['id', 'referenced_paths'])
            ->flatMap(fn (PostRevision $revision): array => $revision->referenced_paths)
            ->all();
        $mediaPaths = $post->media()
            ->get(['path'])
            ->map(fn (PostMedia $media): string => $media->path)
            ->all();
        $draft = $post->draft()->first();
        $paths = array_values(array_unique(array_filter([
            ...$revisionPaths,
            ...$mediaPaths,
            $post->featured_image_path,
            $post->og_image_path,
            $draft?->featured_image_path,
            $draft?->og_image_path,
        ], is_string(...))));

        $post->forceDelete();

        Storage::disk('public')->delete($paths);
        Storage::disk('local')->delete($sourcePaths);
    }
}

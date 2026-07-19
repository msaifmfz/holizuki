<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Queries;

use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;

class PostListing
{
    /** @return array<string, mixed> */
    public function summary(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title ?? 'Untitled post',
            'slug' => $post->slug,
            'status' => $post->isScheduled() ? 'scheduled' : $post->status->value,
            'author' => $post->author?->name,
            'last_editor' => $post->lastEditor?->name,
            'scheduled_at' => $post->scheduled_at?->toISOString(),
            'published_at' => $post->published_at?->toISOString(),
            'featured_at' => $post->featured_at?->toISOString(),
            'updated_at' => $post->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        return [
            'all' => Post::count(),
            'draft' => Post::where('status', PostStatus::Draft)->whereNull('scheduled_at')->count(),
            'scheduled' => Post::scheduled()->count(),
            'published' => Post::where('status', PostStatus::Published)->count(),
            'trash' => Post::onlyTrashed()->count(),
        ];
    }
}

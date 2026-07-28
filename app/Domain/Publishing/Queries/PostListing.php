<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Queries;

use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;

class PostListing
{
    /**
     * Authors browse and search their working drafts, so a listing shows draft
     * values and falls back to the published post only for a post whose draft
     * has not diverged. Loading the relation here rather than trusting callers
     * to eager-load it keeps a forgotten `with('draft')` from silently showing
     * stale published titles.
     *
     * @return array<string, mixed>
     */
    public function summary(Post $post): array
    {
        $post->loadMissing(['draft.author:id,name']);
        $draft = $post->draft;
        $title = $post->title ?? 'Untitled post';
        $slug = $post->slug;
        $authorName = $post->author?->name;

        if ($draft !== null) {
            $title = $draft->title ?? $title;
            $slug = $draft->slug;
            $draftAuthor = $draft->author;

            if ($draftAuthor !== null) {
                $authorName = $draftAuthor->name;
            }
        }

        return [
            'id' => $post->id,
            'title' => $title,
            'slug' => $slug,
            'status' => $post->isScheduled() ? 'scheduled' : $post->status->value,
            'author' => $authorName,
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

<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

trait BuildsPublicPostCards
{
    /** @return Builder<Post> */
    protected function publicPostQuery(): Builder
    {
        return Post::query()
            ->published()
            ->select(['id', 'category_id', 'author_id', 'title', 'slug', 'excerpt', 'featured_image_path', 'featured_image_alt', 'published_at'])
            ->with(['category:id,name,slug', 'author:id,name,author_slug,avatar_path'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    protected function postCard(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'featured_image_url' => $post->featured_image_path === null ? null : Storage::disk('public')->url($post->featured_image_path),
            'featured_image_alt' => $post->featured_image_alt,
            'published_at' => $post->published_at?->toISOString(),
            'category' => $post->category === null ? null : [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
            ],
            'author' => $post->author === null ? null : [
                'name' => $post->author->name,
                'slug' => $post->author->author_slug,
                'avatar_url' => $post->author->avatar_url,
            ],
        ];
    }
}

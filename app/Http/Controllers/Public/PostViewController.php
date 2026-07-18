<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PostViewController extends Controller
{
    use BuildsPublicPostCards;

    public function show(string $slug): Response
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'category:id,name,slug',
                'author:id,name,author_slug,avatar_path,bio,social_links',
                'tags:id,name,slug',
            ])
            ->firstOrFail();

        $related = $post->category_id === null
            ? collect()
            : $this->publicPostQuery()
                ->where('category_id', $post->category_id)
                ->whereKeyNot($post->id)
                ->limit(3)
                ->get()
                ->map(fn (Post $relatedPost): array => $this->postCard($relatedPost));

        $imageUrl = $post->featured_image_path === null
            ? null
            : Storage::disk('public')->url($post->featured_image_path);

        return Inertia::render('public/posts/show', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'featured_image_url' => $imageUrl,
                'featured_image_alt' => $post->featured_image_alt,
                'published_at' => $post->published_at?->toISOString(),
                'updated_at' => $post->updated_at?->toISOString(),
                'category' => $post->category === null ? null : [
                    'name' => $post->category->name,
                    'slug' => $post->category->slug,
                ],
                'tags' => $post->tags->map(fn (Tag $tag): array => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])->all(),
                'author' => $post->author === null ? null : $this->authorProfile($post->author),
            ],
            'related' => $related,
            'seo' => Seo::make(
                title: (string) $post->title,
                description: $post->excerpt,
                canonical: route('public.posts.show', $post->slug),
                image: $imageUrl,
                type: 'article',
                publishedTime: $post->published_at?->toISOString(),
                modifiedTime: $post->updated_at?->toISOString(),
                author: $post->author?->name,
                jsonLd: $this->articleJsonLd($post, $imageUrl),
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function authorProfile(User $author): array
    {
        return [
            'name' => $author->name,
            'slug' => $author->author_slug,
            'avatar_url' => $author->avatar_url,
            'bio' => $author->bio,
            'social_links' => $author->social_links,
        ];
    }

    /** @return array<string, mixed> */
    private function articleJsonLd(Post $post, ?string $imageUrl): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt,
            'image' => $imageUrl,
            'datePublished' => $post->published_at?->toISOString(),
            'dateModified' => $post->updated_at?->toISOString(),
            'mainEntityOfPage' => route('public.posts.show', $post->slug),
            'author' => $post->author === null ? null : array_filter([
                '@type' => 'Person',
                'name' => $post->author->name,
                'url' => $post->author->author_slug === null
                    ? null
                    : route('public.authors.show', $post->author->author_slug),
            ], static fn (mixed $value): bool => $value !== null),
            'publisher' => [
                '@type' => 'Organization',
                'name' => Seo::siteName(),
            ],
        ], static fn (mixed $value): bool => $value !== null);
    }
}

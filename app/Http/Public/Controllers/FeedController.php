<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Support\PublicCache;
use App\Domain\Reading\Support\Seo;
use App\Http\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public function __invoke(): Response
    {
        /** @var list<array{title: string, url: string, excerpt: string|null, author: string|null, published_at: string}> $items */
        $items = Cache::remember(PublicCache::FEED_KEY, 600, fn (): array => Post::query()
            ->published()
            ->with('author:id,name')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get(['id', 'author_id', 'title', 'slug', 'excerpt', 'published_at'])
            ->map(fn (Post $post): array => [
                'title' => (string) $post->title,
                'url' => route('public.posts.show', $post->slug),
                'excerpt' => $post->excerpt,
                'author' => $post->author?->name,
                'published_at' => (string) $post->published_at?->toRssString(),
            ])
            ->all());

        return response()
            ->view('feed', [
                'items' => $items,
                'description' => Seo::DEFAULT_DESCRIPTION,
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}

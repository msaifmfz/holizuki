<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Support\PublicCache;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
use App\Http\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        /** @var array<int, array{loc: string, lastmod?: string}> $urls */
        $urls = Cache::remember(PublicCache::SITEMAP_KEY, 600, fn (): array => $this->buildUrls());

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    /** @return array<int, array{loc: string, lastmod?: string}> */
    private function buildUrls(): array
    {
        $urls = collect([
            ['loc' => route('home')],
            ['loc' => route('public.topics')],
            ['loc' => route('public.archive')],
            ['loc' => route('public.about')],
            ['loc' => route('public.contact.create')],
            ['loc' => route('public.privacy')],
            ['loc' => route('public.terms')],
        ]);

        $posts = Post::query()
            ->published()
            ->where('noindex', false)
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'content_updated_at']);

        $urls = $urls->merge($posts->map(fn (Post $post): array => [
            'loc' => route('public.posts.show', $post->slug),
            'lastmod' => (string) ($post->content_updated_at ?? $post->published_at)?->toISOString(),
        ]));

        $urls = $urls->merge(
            $posts
                ->filter(fn (Post $post): bool => $post->published_at !== null)
                ->flatMap(fn (Post $post): array => [
                    ['loc' => route('public.archive', ['year' => $post->published_at?->format('Y')])],
                    ['loc' => route('public.archive', [
                        'year' => $post->published_at?->format('Y'),
                        'month' => $post->published_at?->format('m'),
                    ])],
                ])
                ->unique('loc'),
        );

        $urls = $urls->merge(
            Category::query()
                ->whereHas('posts', fn (Builder $query) => $query->where('status', PostStatus::Published))
                ->orderBy('name')
                ->get(['slug'])
                ->map(fn (Category $category): array => [
                    'loc' => route('public.categories.show', $category->slug),
                ]),
        );

        $urls = $urls->merge(
            Tag::query()
                ->whereHas('posts', fn (Builder $query) => $query->where('status', PostStatus::Published))
                ->orderBy('name')
                ->get(['slug'])
                ->map(fn (Tag $tag): array => [
                    'loc' => route('public.tags.show', $tag->slug),
                ]),
        );

        $urls = $urls->merge(
            User::query()
                ->whereNotNull('author_slug')
                ->whereIn('id', Post::query()->where('status', PostStatus::Published)->select('author_id'))
                ->orderBy('name')
                ->get(['author_slug'])
                ->map(fn (User $user): array => [
                    'loc' => route('public.authors.show', (string) $user->author_slug),
                ]),
        );

        return $urls->values()->all();
    }
}

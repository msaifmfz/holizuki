<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\PublicCache;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use BuildsPublicPostCards;

    public function index(Request $request): Response
    {
        $featured = $this->publicPostQuery()
            ->whereNotNull('featured_at')
            ->addSelect('featured_at')
            ->reorder()
            ->latest('featured_at')
            ->orderByDesc('id')
            ->limit(config()->integer('blog.maximum_featured_posts', 3))
            ->get();

        if ($featured->isEmpty()) {
            $newest = $this->publicPostQuery()->first();

            if ($newest !== null) {
                $featured->push($newest);
            }
        }

        $featuredIds = [];

        foreach ($featured as $featuredPost) {
            $featuredIds[] = $featuredPost->id;
        }

        $postsQuery = $this->publicPostQuery();

        if ($featuredIds !== []) {
            $postsQuery->whereKeyNot($featuredIds);
        }

        $posts = $postsQuery
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCard($post));

        $page = max(1, $request->integer('page', 1));

        return Inertia::render('public/home', [
            'featured' => $featured->map(fn (Post $post): array => $this->postCard($post))->values(),
            'popular' => $this->popularPosts($featuredIds),
            'posts' => $posts,
            'seo' => Seo::make(
                title: Seo::siteName().' — Blog',
                canonical: $page > 1 ? route('home', ['page' => $page]) : route('home'),
                prevUrl: $posts->previousPageUrl(),
                nextUrl: $posts->nextPageUrl(),
                jsonLd: Seo::websiteJsonLd(),
            ),
        ]);
    }

    /**
     * The cached list ignores the exclusions so the single cache entry stays
     * valid for any caller; the featured posts are filtered out afterwards.
     * Over-fetch by the maximum number of featured posts so the filtered
     * list still fills up.
     *
     * @param  list<int>  $excludedPostIds
     * @return list<array<string, mixed>>
     */
    private function popularPosts(array $excludedPostIds): array
    {
        $limit = 6;

        /** @var list<array<string, mixed>> $popularPosts */
        $popularPosts = Cache::remember(PublicCache::POPULAR_POSTS_KEY, now()->addMinutes(5), function () use ($limit): array {
            $windowDays = max(1, config()->integer('blog.popular_window_days', 30));
            $startDate = today()->subDays($windowDays - 1);
            $recentViews = static fn (Builder $query): Builder => $query->where('viewed_on', '>=', $startDate);
            $overfetch = max(1, config()->integer('blog.maximum_featured_posts', 3));

            return $this->publicPostQuery()
                ->whereHas('views', $recentViews)
                ->withCount(['views as recent_views_count' => $recentViews])
                ->reorder()
                ->orderByDesc('recent_views_count')
                ->latest('published_at')
                ->orderByDesc('id')
                ->limit($limit + $overfetch)
                ->get()
                ->map(fn (Post $post): array => $this->popularPostCard($post))
                ->all();
        });

        return array_values(collect($popularPosts)
            ->reject(fn (array $card): bool => in_array($card['id'], $excludedPostIds, true))
            ->take($limit)
            ->all());
    }

    /** @return array<string, mixed> */
    private function popularPostCard(Post $post): array
    {
        $viewsCount = $post->getAttribute('recent_views_count');

        return [
            ...$this->postCard($post),
            'views_count' => is_numeric($viewsCount) ? (int) $viewsCount : 0,
        ];
    }
}

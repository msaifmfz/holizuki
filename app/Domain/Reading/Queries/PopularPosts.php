<?php

declare(strict_types=1);

namespace App\Domain\Reading\Queries;

use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Models\PostView;
use App\Domain\Reading\Support\PublicCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class PopularPosts
{
    public function __construct(private readonly PublicPostCards $postCards) {}

    /**
     * The cached list ignores the exclusions so the single cache entry stays
     * valid for any caller; the excluded posts are filtered out afterwards.
     * Over-fetch by the maximum number of featured posts so the filtered
     * list still fills up.
     *
     * @param  list<int>  $excludedPostIds
     * @return list<array<string, mixed>>
     */
    public function list(array $excludedPostIds): array
    {
        $limit = 6;

        /** @var list<array<string, mixed>> $popularPosts */
        $popularPosts = Cache::remember(PublicCache::POPULAR_POSTS_KEY, now()->addMinutes(5), function () use ($limit): array {
            $windowDays = max(1, config()->integer('blog.popular_window_days', 30));
            $startDate = today()->subDays($windowDays - 1);
            $recentViews = static fn (Builder $query): Builder => $query->where('viewed_on', '>=', $startDate);
            $overfetch = max(1, config()->integer('blog.maximum_featured_posts', 3));

            return $this->postCards->query()
                ->whereIn('posts.id', $recentViews(PostView::query())->select('post_id'))
                ->selectSub(
                    $recentViews(PostView::query())->whereColumn('post_views.post_id', 'posts.id')->selectRaw('count(*)'),
                    'recent_views_count',
                )
                ->reorder()
                ->orderByDesc('recent_views_count')
                ->latest('published_at')
                ->orderByDesc('id')
                ->limit($limit + $overfetch)
                ->get()
                ->map(fn (Post $post): array => $this->card($post))
                ->all();
        });

        return array_values(collect($popularPosts)
            ->reject(fn (array $card): bool => in_array($card['id'], $excludedPostIds, true))
            ->take($limit)
            ->all());
    }

    /** @return array<string, mixed> */
    private function card(Post $post): array
    {
        $viewsCount = $post->getAttribute('recent_views_count');

        return [
            ...$this->postCards->card($post),
            'views_count' => is_numeric($viewsCount) ? (int) $viewsCount : 0,
        ];
    }
}

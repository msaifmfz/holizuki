<?php

declare(strict_types=1);

namespace App\Domain\Reading\Support;

use App\Domain\Taxonomy\Models\Category;
use Illuminate\Support\Facades\Cache;

class PublicCache
{
    public const string SITEMAP_KEY = 'public.sitemap';

    public const string FEED_KEY = 'public.feed';

    public const string POPULAR_POSTS_KEY = 'public.popular-posts';

    /**
     * Forget every cached public listing that depends on published posts.
     */
    public static function flush(): void
    {
        Cache::forget(self::SITEMAP_KEY);
        Cache::forget(self::FEED_KEY);
        Cache::forget(self::POPULAR_POSTS_KEY);
        Cache::forget(Category::FOOTER_CACHE_KEY);
    }
}

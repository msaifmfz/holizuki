<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class PublicCache
{
    public const string SITEMAP_KEY = 'public.sitemap';

    public const string FEED_KEY = 'public.feed';

    /**
     * Forget every cached public listing that depends on published posts.
     */
    public static function flush(): void
    {
        Cache::forget(self::SITEMAP_KEY);
        Cache::forget(self::FEED_KEY);
        Cache::forget(Category::FOOTER_CACHE_KEY);
    }
}

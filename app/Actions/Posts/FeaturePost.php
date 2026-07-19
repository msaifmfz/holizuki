<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesLockedPost;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Support\PublicCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeaturePost
{
    use ResolvesLockedPost;

    public function handle(Post $post): Post
    {
        $featured = DB::transaction(function () use ($post): Post {
            $current = $this->lockedPost($post);

            if ($current->status !== PostStatus::Published) {
                throw ValidationException::withMessages([
                    'post' => __('Only published posts can be featured.'),
                ]);
            }

            if ($current->featured_at !== null) {
                return $current;
            }

            $maximum = max(1, config()->integer('blog.maximum_featured_posts', 3));
            $featuredPosts = Post::query()
                ->published()
                ->whereNotNull('featured_at')
                ->orderBy('featured_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $featuredPosts
                ->take(max(0, $featuredPosts->count() - $maximum + 1))
                ->each(function (Post $featuredPost): void {
                    $featuredPost->featured_at = null;
                    Post::withoutTimestamps(fn (): bool => $featuredPost->save());
                });

            $current->featured_at = now();
            Post::withoutTimestamps(fn (): bool => $current->save());

            return $current->refresh();
        }, attempts: 3);

        PublicCache::flush();

        return $featured;
    }
}

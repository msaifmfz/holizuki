<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Events\PostFeatured;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;

class FeaturePost
{
    use ResolvesLockedPost;

    public function handle(Post $post): Post
    {
        $featured = DB::transaction(function () use ($post): Post {
            $current = $this->lockedPost($post);

            if ($current->featured_at !== null && $current->isPublished()) {
                return $current;
            }

            $maximum = max(1, config()->integer('blog.maximum_featured_posts', 3));
            $featuredPosts = Post::query()
                ->published()
                ->whereNotNull('featured_at')
                ->whereKeyNot($current->id)
                ->orderBy('featured_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $featuredPosts
                ->take(max(0, $featuredPosts->count() - $maximum + 1))
                ->each(function (Post $featuredPost): void {
                    $featuredPost->unfeature();
                });

            $current->feature();

            return $current->refresh();
        }, attempts: 3);

        event(new PostFeatured($featured));

        return $featured;
    }
}

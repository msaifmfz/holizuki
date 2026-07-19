<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesLockedPost;
use App\Models\Post;
use App\Support\PublicCache;
use Illuminate\Support\Facades\DB;

class UnfeaturePost
{
    use ResolvesLockedPost;

    public function handle(Post $post): Post
    {
        $unfeatured = DB::transaction(function () use ($post): Post {
            $current = $this->lockedPost($post);

            if ($current->featured_at !== null) {
                $current->featured_at = null;
                Post::withoutTimestamps(fn (): bool => $current->save());
            }

            return $current->refresh();
        });

        PublicCache::flush();

        return $unfeatured;
    }
}

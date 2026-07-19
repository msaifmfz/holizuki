<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Events\PostUnfeatured;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;

class UnfeaturePost
{
    use ResolvesLockedPost;

    public function handle(Post $post): Post
    {
        $unfeatured = DB::transaction(function () use ($post): Post {
            $current = $this->lockedPost($post);

            if ($current->featured_at !== null) {
                $current->unfeature();
            }

            return $current->refresh();
        });

        event(new PostUnfeatured($unfeatured));

        return $unfeatured;
    }
}

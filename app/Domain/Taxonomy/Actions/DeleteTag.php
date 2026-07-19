<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Events\TagDeleted;
use App\Domain\Taxonomy\Models\Tag;

class DeleteTag
{
    public function handle(Tag $tag): void
    {
        /** @var list<int> $affectedPostIds */
        $affectedPostIds = $tag->posts()->pluck((new Post)->qualifyColumn('id'))->all();

        $tag->delete();

        event(new TagDeleted($tag, $affectedPostIds));
    }
}

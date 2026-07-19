<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Events\CategoryDeleted;
use App\Domain\Taxonomy\Models\Category;

class DeleteCategory
{
    public function handle(Category $category): void
    {
        /** @var list<int> $affectedPostIds */
        $affectedPostIds = Post::query()->where('category_id', $category->id)->pluck('id')->all();

        $category->delete();

        event(new CategoryDeleted($category, $affectedPostIds));
    }
}

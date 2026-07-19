<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Listeners;

use App\Domain\Publishing\Actions\RebuildPostMetadata;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Events\CategoryDeleted;
use App\Domain\Taxonomy\Events\CategorySaved;
use App\Domain\Taxonomy\Events\TagDeleted;
use App\Domain\Taxonomy\Events\TagSaved;
use Illuminate\Database\Eloquent\Builder;

class RebuildPostMetadataOnTaxonomyChange
{
    public function __construct(private readonly RebuildPostMetadata $rebuildPostMetadata) {}

    public function handle(CategorySaved|CategoryDeleted|TagSaved|TagDeleted $event): void
    {
        $affectedPosts = match (true) {
            $event instanceof CategorySaved => Post::query()->where('category_id', $event->category->id),
            $event instanceof CategoryDeleted => Post::query()->whereKey($event->affectedPostIds),
            $event instanceof TagSaved => Post::query()->whereHas(
                'tags',
                fn (Builder $query): Builder => $query->whereKey($event->tag->id),
            ),
            $event instanceof TagDeleted => Post::query()->whereKey($event->affectedPostIds),
        };

        $this->rebuildPostMetadata->handleQuery($affectedPosts);
    }
}

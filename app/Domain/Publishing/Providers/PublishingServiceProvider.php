<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Providers;

use App\Domain\Identity\Events\AuthorProfileUpdated;
use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Publishing\Listeners\RebuildPostMetadataOnAuthorChange;
use App\Domain\Publishing\Listeners\RebuildPostMetadataOnTaxonomyChange;
use App\Domain\Taxonomy\Events\CategoryDeleted;
use App\Domain\Taxonomy\Events\CategorySaved;
use App\Domain\Taxonomy\Events\TagDeleted;
use App\Domain\Taxonomy\Events\TagSaved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PublishingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen([
            CategorySaved::class,
            CategoryDeleted::class,
            TagSaved::class,
            TagDeleted::class,
        ], RebuildPostMetadataOnTaxonomyChange::class);

        Event::listen([
            AuthorProfileUpdated::class,
            UserDeleted::class,
        ], RebuildPostMetadataOnAuthorChange::class);
    }
}

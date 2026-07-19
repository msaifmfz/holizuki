<?php

declare(strict_types=1);

namespace App\Domain\Reading\Providers;

use App\Domain\Identity\Events\AuthorProfileUpdated;
use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Publishing\Events\PostContentUpdated;
use App\Domain\Publishing\Events\PostFeatured;
use App\Domain\Publishing\Events\PostPublished;
use App\Domain\Publishing\Events\PostScheduled;
use App\Domain\Publishing\Events\PostTrashed;
use App\Domain\Publishing\Events\PostUnfeatured;
use App\Domain\Publishing\Events\PostUnpublished;
use App\Domain\Reading\Listeners\FlushPublicCache;
use App\Domain\Taxonomy\Events\CategoryDeleted;
use App\Domain\Taxonomy\Events\CategorySaved;
use App\Domain\Taxonomy\Events\TagDeleted;
use App\Domain\Taxonomy\Events\TagSaved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ReadingServiceProvider extends ServiceProvider
{
    /**
     * Any change that can alter what the public site renders invalidates the
     * shared public cache.
     */
    public function boot(): void
    {
        Event::listen([
            PostPublished::class,
            PostUnpublished::class,
            PostScheduled::class,
            PostTrashed::class,
            PostFeatured::class,
            PostUnfeatured::class,
            PostContentUpdated::class,
            CategorySaved::class,
            CategoryDeleted::class,
            TagSaved::class,
            TagDeleted::class,
            AuthorProfileUpdated::class,
            UserDeleted::class,
        ], FlushPublicCache::class);
    }
}

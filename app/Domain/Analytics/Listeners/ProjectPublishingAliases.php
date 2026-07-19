<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Listeners;

use App\Domain\Analytics\Actions\ProjectAnalyticsUrlAliases;
use App\Domain\Publishing\Events\PostContentUpdated;
use App\Domain\Publishing\Events\PostPublished;
use App\Domain\Publishing\Events\PostTrashed;
use App\Domain\Publishing\Events\PostUnpublished;

class ProjectPublishingAliases
{
    public function __construct(private readonly ProjectAnalyticsUrlAliases $project) {}

    public function handle(PostContentUpdated|PostPublished|PostTrashed|PostUnpublished $event): void
    {
        $this->project->handle($event->post);
    }
}

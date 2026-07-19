<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Actions\EvaluateCommunityMilestones;
use App\Domain\Analytics\Contracts\AnalyticsAdminGateway;
use App\Domain\Analytics\Contracts\AnalyticsReportingGateway;
use App\Domain\Analytics\Gateways\GoogleAnalyticsAdminGateway;
use App\Domain\Analytics\Gateways\GoogleAnalyticsReportingGateway;
use App\Domain\Analytics\Listeners\ProjectPublishingAliases;
use App\Domain\Analytics\Listeners\RecordPublishingActivity;
use App\Domain\Community\Events\CommentApproved;
use App\Domain\Community\Events\SubscriberConfirmed;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Events\PostContentUpdated;
use App\Domain\Publishing\Events\PostPublished;
use App\Domain\Publishing\Events\PostTrashed;
use App\Domain\Publishing\Events\PostUnpublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Override;

class AnalyticsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bind(AnalyticsReportingGateway::class, GoogleAnalyticsReportingGateway::class);
        $this->app->bind(AnalyticsAdminGateway::class, GoogleAnalyticsAdminGateway::class);
    }

    public function boot(): void
    {
        Event::listen([
            PostContentUpdated::class,
            PostPublished::class,
            PostTrashed::class,
            PostUnpublished::class,
        ], ProjectPublishingAliases::class);

        Event::listen([
            PostContentUpdated::class,
            PostPublished::class,
        ], RecordPublishingActivity::class);

        Event::listen(CommentApproved::class, function (CommentApproved $event): void {
            $evaluate = resolve(EvaluateCommunityMilestones::class);
            User::query()
                ->where('role', UserRole::Administrator)
                ->whereNotNull('email_verified_at')
                ->each(static fn (User $user) => $evaluate->handle($user, $event));
        });

        Event::listen(SubscriberConfirmed::class, function (SubscriberConfirmed $event): void {
            $evaluate = resolve(EvaluateCommunityMilestones::class);
            User::query()
                ->where('role', UserRole::Administrator)
                ->whereNotNull('email_verified_at')
                ->each(static fn (User $user) => $evaluate->handle($user, $event));
        });
    }
}

<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\EvaluateCommunityMilestones;
use App\Domain\Analytics\Actions\EvaluateMilestones;
use App\Domain\Analytics\Models\AnalyticsMilestone;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AuthorPublication;
use App\Domain\Community\Events\CommentApproved;
use App\Domain\Community\Events\SubscriberConfirmed;
use App\Domain\Community\Models\Comment;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
});

it('records publishing audience engagement community and longevity milestones exactly once', function (): void {
    $user = User::factory()->create();
    $publicationDates = [
        '2026-01-10', '2026-02-01', '2026-02-15', '2026-03-01', '2026-03-15',
    ];
    foreach ($publicationDates as $date) {
        AuthorPublication::factory()->create([
            'author_id' => $user,
            'first_published_at' => $date,
        ]);
    }

    AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'site',
        'scope_key' => 'site',
        'starts_on' => '2026-06-22',
        'ends_on' => '2026-07-19',
        'readers' => 1000,
        'meaningful_readers' => 100,
        'shares' => 1,
    ]);
    AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'site',
        'scope_key' => 'site',
        'period_key' => 'custom',
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-21',
        'readers' => 50,
        'meaningful_readers' => 25,
    ]);

    $comment = Comment::factory()->approved()->create();
    $subscribers = NewsletterSubscriber::factory()->confirmed()->count(100)->create();

    $oldPost = Post::factory()->published()->create([
        'published_at' => now()->subDays(366),
    ]);
    AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'post',
        'scope_key' => 'post:'.$oldPost->id,
        'period_key' => 'lifetime',
        'starts_on' => '2025-07-18',
        'ends_on' => '2026-07-19',
        'readers' => 1,
    ]);

    $evaluator = resolve(EvaluateMilestones::class);
    $evaluator->handle($user);
    $communityEvaluator = resolve(EvaluateCommunityMilestones::class);
    $communityEvaluator->handle($user, new CommentApproved($comment->id, $comment->post_id));
    $communityEvaluator->handle($user, new SubscriberConfirmed(
        $subscribers->firstOrFail()->id,
        null,
        $subscribers->count(),
    ));
    $firstCount = AnalyticsMilestone::query()->count();
    $firstAchievedAt = AnalyticsMilestone::query()
        ->where('code', 'published_5_posts')
        ->sole()
        ->achieved_at;
    $evaluator->handle($user);
    $communityEvaluator->handle($user, new CommentApproved($comment->id, $comment->post_id));
    $communityEvaluator->handle($user, new SubscriberConfirmed(
        $subscribers->firstOrFail()->id,
        null,
        $subscribers->count(),
    ));

    expect(AnalyticsMilestone::query()->count())->toBe($firstCount)
        ->and(AnalyticsMilestone::query()->where('code', 'published_1_posts')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'published_5_posts')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'measured_readers_1000')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'meaningful_readers_100')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'first_50_percent_meaningful_rate')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'first_approved_comment')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'first_measured_share')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'first_confirmed_subscriber')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'confirmed_subscribers_100')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'readers_after_90_days')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()->where('code', 'readers_after_365_days')->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()
            ->where('code', 'new_monthly_post_record')
            ->where('scope_key', 'month:2026-02')
            ->exists())->toBeTrue()
        ->and(AnalyticsMilestone::query()
            ->where('code', 'published_5_posts')
            ->sole()
            ->achieved_at
            ->equalTo($firstAchievedAt))->toBeTrue();
});

it('does not award milestones before their thresholds', function (): void {
    $user = User::factory()->create();
    AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'site',
        'scope_key' => 'site',
        'readers' => 0,
        'meaningful_readers' => 0,
        'shares' => 0,
    ]);

    resolve(EvaluateMilestones::class)->handle($user);

    expect(AnalyticsMilestone::query()->exists())->toBeFalse();
});

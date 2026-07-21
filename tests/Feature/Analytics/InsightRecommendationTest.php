<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\DismissInsight;
use App\Domain\Analytics\Actions\GenerateInsights;
use App\Domain\Analytics\Enums\InsightStatus;
use App\Domain\Analytics\Models\AnalyticsInsight;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AuthorPublication;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set([
        'app.timezone' => 'UTC',
        'analytics.material_gap_points' => 15,
    ]);
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
});

it('nudges publishing again only after fourteen quiet days when a draft exists', function (): void {
    $user = User::factory()->create();
    AuthorPublication::factory()->create([
        'author_id' => $user,
        'first_published_at' => '2026-06-29 09:00:00',
    ]);
    $draft = Post::factory()->create(['author_id' => $user, 'updated_at' => now()]);

    resolve(GenerateInsights::class)->handle($user);

    $insight = AnalyticsInsight::query()->where('rule_id', 'publish_next')->sole();
    expect($insight->post_id)->toBe($draft->id)
        ->and($insight->evidence['days_since_last_publication'])->toBe(20)
        ->and($insight->observation)->toContain('20 days since your last publication');

    AnalyticsInsight::query()->delete();
    AuthorPublication::factory()->create([
        'author_id' => $user,
        'first_published_at' => '2026-07-10 09:00:00',
    ]);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()->where('rule_id', 'publish_next')->exists())->toBeFalse();
});

it('recommends a related article at the exact reader and material-gap boundaries', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 100,
        'meaningful_readers' => 65,
    ]);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()
        ->where('rule_id', 'related_article')
        ->where('post_id', $post->id)
        ->exists())->toBeTrue();
});

it('recommends an introduction improvement at exact age reader and progression boundaries', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 100,
        'article_progress_25' => 65,
    ], ageDays: 7);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()
        ->where('rule_id', 'improve_introduction')
        ->where('post_id', $post->id)
        ->exists())->toBeTrue();
});

it('recommends structural improvement at its exact middle ending and word-count boundaries', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 100,
        'article_progress_50' => 55,
        'article_progress_90' => 25,
    ], wordCount: 1001);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()
        ->where('rule_id', 'improve_structure')
        ->where('post_id', $post->id)
        ->exists())->toBeTrue();
});

it('recommends a stronger call to action at the exact comparable-rate gap', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 100,
        'meaningful_readers' => 50,
        'article_progress_90' => 40,
        'actioning_readers' => 0,
    ]);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()
        ->where('rule_id', 'strengthen_cta')
        ->where('post_id', $post->id)
        ->exists())->toBeTrue();
});

it('recommends refreshing an older article at exact age decline and readership boundaries', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 100,
        'meaningful_readers' => 70,
        'previous_meaningful_readers' => 100,
    ], ageDays: 180);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()
        ->where('rule_id', 'refresh_older_article')
        ->where('post_id', $post->id)
        ->exists())->toBeTrue();
});

it('recommends a rising topic with fifty readers twenty-five meaningful readers and fourteen days of data', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 50,
        'meaningful_readers' => 25,
    ], ageDays: 1, dataDays: 14);
    insightSnapshot($post, [
        'period_key' => '7d',
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-07-19',
        'readers' => 50,
        'meaningful_readers' => 30,
        'previous_meaningful_readers' => 20,
    ]);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()
        ->where('rule_id', 'expand_rising_topic')
        ->where('post_id', $post->id)
        ->exists())->toBeTrue();
});

it('recommends internal links at the exact continuation gap when a related candidate exists', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 100,
        'select_content' => 5,
    ]);
    Post::factory()->published()->create(['category_id' => $post->category_id]);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()
        ->where('rule_id', 'improve_internal_links')
        ->where('post_id', $post->id)
        ->exists())->toBeTrue();
});

it('does not issue negative optimization advice below one hundred readers', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 99,
        'article_progress_25' => 40,
    ], ageDays: 30);

    resolve(GenerateInsights::class)->handle($user);

    expect(AnalyticsInsight::query()
        ->where('rule_id', 'improve_introduction')
        ->where('post_id', $post->id)
        ->exists())->toBeFalse();
});

it('uses the configured dismissal and snooze recurrence windows', function (
    string $reason,
    InsightStatus $status,
    ?int $days,
): void {
    $insight = AnalyticsInsight::factory()->create();

    resolve(DismissInsight::class)->handle($insight, $reason);

    expect($insight->refresh()->status)->toBe($status);
    if ($days === null) {
        expect($insight->dismissed_until)->toBeNull()
            ->and($insight->completed_at)->not->toBeNull();
    } else {
        expect(now()->diffInDays($insight->dismissed_until))->toBe((float) $days);
    }
})->with([
    'not relevant' => ['not_relevant', InsightStatus::Dismissed, 90],
    'intentional' => ['intentionally_designed', InsightStatus::Dismissed, 90],
    'insufficient context' => ['insufficient_context', InsightStatus::Dismissed, 28],
    'incorrect data' => ['data_incorrect', InsightStatus::Dismissed, 28],
    'snooze' => ['snooze', InsightStatus::Snoozed, 7],
    'completed' => ['already_completed', InsightStatus::Completed, null],
]);

it('reactivates expired dismissals but never reactivates completed recommendations', function (): void {
    [$user, $post] = insightScenarioPost([
        'readers' => 100,
        'article_progress_25' => 65,
    ], ageDays: 7);
    $generator = resolve(GenerateInsights::class);
    $generator->handle($user);
    $insight = AnalyticsInsight::query()
        ->where('rule_id', 'improve_introduction')
        ->where('post_id', $post->id)
        ->sole();

    resolve(DismissInsight::class)->handle($insight, 'not_relevant');
    $insight->update(['dismissed_until' => now()->subSecond()]);
    $generator->handle($user);
    expect($insight->refresh()->status)->toBe(InsightStatus::Active);

    resolve(DismissInsight::class)->handle($insight, 'already_completed');
    $generator->handle($user);
    expect($insight->refresh()->status)->toBe(InsightStatus::Completed);
});

/**
 * @param  array<string, int>  $metrics
 * @return array{0: User, 1: Post}
 */
function insightScenarioPost(
    array $metrics,
    int $ageDays = 30,
    int $wordCount = 1000,
    int $dataDays = 30,
): array {
    $user = User::factory()->create();
    AnalyticsSyncRun::factory()->create([
        'starts_on' => now()->subDays($dataDays - 1),
        'ends_on' => now(),
        'completed_at' => now(),
    ]);
    AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'site',
        'scope_key' => 'site',
        'period_key' => '28d',
        'starts_on' => '2026-06-22',
        'ends_on' => '2026-07-19',
        'readers' => 500,
    ]);

    foreach (range(1, 3) as $_) {
        $baseline = insightPost($user, 30, 1000);
        insightSnapshot($baseline);
    }

    $post = insightPost($user, $ageDays, $wordCount);
    insightSnapshot($post, $metrics);

    return [$user, $post];
}

function insightPost(User $user, int $ageDays, int $wordCount): Post
{
    $post = Post::factory()->published()->create([
        'author_id' => $user->id,
        'published_at' => now()->subDays($ageDays),
    ]);
    $post->forceFill(['word_count' => $wordCount])->saveQuietly();

    return $post;
}

/** @param array<string, int|string> $overrides */
function insightSnapshot(Post $post, array $overrides = []): AnalyticsPeriodSnapshot
{
    return AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'post',
        'scope_key' => 'post:'.$post->id,
        'period_key' => '28d',
        'starts_on' => '2026-06-22',
        'ends_on' => '2026-07-19',
        'readers' => 200,
        'meaningful_readers' => 100,
        'actioning_readers' => 30,
        'article_progress_25' => 160,
        'article_progress_50' => 120,
        'article_progress_90' => 80,
        'select_content' => 40,
        'previous_readers' => 200,
        'previous_meaningful_readers' => 100,
        ...$overrides,
    ]);
}

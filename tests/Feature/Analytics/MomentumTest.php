<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\CalculateMomentum;
use App\Domain\Analytics\Enums\FreshnessState;
use App\Domain\Analytics\Enums\MomentumLevel;
use App\Domain\Analytics\Models\AnalyticsMomentumSnapshot;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AuthorActivityEvent;
use App\Domain\Analytics\Models\AuthorPublication;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
});

it('maps audience trends at the specified boundaries', function (): void {
    $momentum = resolve(CalculateMomentum::class);

    expect($momentum->trendFactor(75, 100))->toBe(0.0)
        ->and($momentum->trendFactor(100, 100))->toBe(0.5)
        ->and($momentum->trendFactor(125, 100))->toBe(1.0)
        ->and($momentum->trendFactor(1, 0))->toBe(1.0)
        ->and($momentum->trendFactor(0, 0))->toBe(0.5)
        ->and($momentum->trendFactor(0, 1))->toBe(0.0);
});

it('uses exact freshness boundaries', function (
    string $lastSuccess,
    FreshnessState $expected,
): void {
    expect(FreshnessState::forLastSuccess(CarbonImmutable::parse($lastSuccess)))->toBe($expected);
})->with([
    'eight hours' => ['2026-07-19 04:00:00', FreshnessState::Fresh],
    'after eight hours' => ['2026-07-19 03:59:59', FreshnessState::Delayed],
    'twenty four hours' => ['2026-07-18 12:00:00', FreshnessState::Delayed],
    'after twenty four hours' => ['2026-07-18 11:59:59', FreshnessState::Stale],
]);

it('uses stable momentum level boundaries', function (
    int $score,
    MomentumLevel $expected,
): void {
    expect(MomentumLevel::forScore($score))->toBe($expected);
})->with([
    [0, MomentumLevel::Starting],
    [24, MomentumLevel::Starting],
    [25, MomentumLevel::Building],
    [49, MomentumLevel::Building],
    [50, MomentumLevel::Growing],
    [74, MomentumLevel::Growing],
    [75, MomentumLevel::Compounding],
    [100, MomentumLevel::Compounding],
]);

it('always scores momentum and gathers publishing consistency for brand-new authors', function (): void {
    $user = User::factory()->create();
    AnalyticsSyncRun::factory()->create(['completed_at' => now()]);

    $snapshot = resolve(CalculateMomentum::class)->handle($user);

    expect($snapshot->score)->toBe(0)
        ->and($snapshot->level)->toBe(MomentumLevel::Starting)
        ->and($snapshot->components['consistency']['status'])->toBe('gathering_data')
        ->and($snapshot->components['meaningful_reader_trend']['status'])->toBe('hidden_for_new_blog')
        ->and($snapshot->components)->not->toHaveKey('goal_progress');
});

it('blends weekly publishing consistency with reader trends and maintenance', function (): void {
    $user = User::factory()->create();
    AnalyticsSyncRun::factory()->create([
        'completed_at' => now(),
        'starts_on' => '2026-06-01',
    ]);
    AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'site',
        'scope_key' => 'site',
        'period_key' => '7d',
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-07-19',
        'readers' => 100,
        'previous_readers' => 100,
        'meaningful_readers' => 120,
        'previous_meaningful_readers' => 100,
        'actioning_readers' => 30,
        'previous_actioning_readers' => 20,
    ]);
    foreach (['2026-05-20', '2026-06-03', '2026-06-17', '2026-07-01'] as $publishedOn) {
        AuthorPublication::factory()->create([
            'author_id' => $user,
            'first_published_at' => $publishedOn.' 09:00:00',
        ]);
    }
    AuthorActivityEvent::factory()->create([
        'user_id' => $user,
        'post_id' => Post::factory()->published()->create()->id,
        'event_id' => 'post_maintained',
        'occurred_at' => '2026-07-15 09:00:00',
    ]);

    $snapshot = resolve(CalculateMomentum::class)->handle($user);

    expect($snapshot->score)->toBe(85)
        ->and($snapshot->level)->toBe(MomentumLevel::Compounding)
        ->and($snapshot->components['consistency']['value'])->toBe(0.5)
        ->and($snapshot->components['meaningful_reader_trend']['value'])->toBe(0.9)
        ->and($snapshot->components['reader_action_rate_trend']['value'])->toEqual(1)
        ->and($snapshot->components['content_maintenance']['value'])->toBe(1);
});

it('gathers consistency until two full weeks pass after the first publication', function (): void {
    $user = User::factory()->create();
    AnalyticsSyncRun::factory()->create(['completed_at' => now()]);
    AuthorPublication::factory()->create([
        'author_id' => $user,
        'first_published_at' => '2026-07-10 09:00:00',
    ]);

    $snapshot = resolve(CalculateMomentum::class)->handle($user);

    expect($snapshot->components['consistency']['status'])->toBe('gathering_data');
});

it('retains the last momentum score while marking stale source data honestly', function (): void {
    $user = User::factory()->create();
    $first = AnalyticsMomentumSnapshot::factory()->create([
        'user_id' => $user,
        'scored_on' => '2026-07-19',
        'score' => 74,
        'components' => ['meaningful_reader_trend' => ['score' => 30]],
        'freshness' => FreshnessState::Fresh,
        'data_freshness_at' => now(),
        'calculated_at' => now(),
    ]);
    AnalyticsSyncRun::factory()->create([
        'completed_at' => now(),
    ]);
    CarbonImmutable::setTestNow('2026-07-20 14:00:00');

    $retained = resolve(CalculateMomentum::class)->handle($user);

    expect($retained->score)->toBe(74)
        ->and($retained->components)->toBe($first->components)
        ->and($retained->freshness)->toBe(FreshnessState::Stale)
        ->and($retained->calculated_at->equalTo($first->calculated_at))->toBeTrue();
});

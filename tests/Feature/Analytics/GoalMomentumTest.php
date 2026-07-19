<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\CalculateMomentum;
use App\Domain\Analytics\Actions\GoalStreak;
use App\Domain\Analytics\Actions\PauseGoalPeriod;
use App\Domain\Analytics\Actions\RecordFirstPublication;
use App\Domain\Analytics\Actions\ResumeGoalPeriod;
use App\Domain\Analytics\Actions\SetPublishingGoal;
use App\Domain\Analytics\Enums\FreshnessState;
use App\Domain\Analytics\Enums\GoalCadence;
use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Enums\MomentumLevel;
use App\Domain\Analytics\Models\AnalyticsMomentumSnapshot;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AuthorGoal;
use App\Domain\Analytics\Models\AuthorGoalPause;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use App\Domain\Analytics\Models\AuthorPublication;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
});

it('makes goal changes effective at the next period boundary', function (): void {
    $user = User::factory()->create();

    $goal = resolve(SetPublishingGoal::class)->handle($user, GoalCadence::Weekly, 3);

    expect($goal->effective_from->toDateString())->toBe('2026-07-20')
        ->and($goal->target)->toBe(3)
        ->and($goal->periods()->firstOrFail()->starts_on->toDateString())->toBe('2026-07-20');
});

it('updates an unstarted goal choice without leaving stale scheduled periods', function (): void {
    $user = User::factory()->create();
    $setGoal = resolve(SetPublishingGoal::class);

    $first = $setGoal->handle($user, GoalCadence::Weekly, 1);
    $second = $setGoal->handle($user, GoalCadence::Weekly, 3);

    expect($second->id)->toBe($first->id)
        ->and(AuthorGoal::query()->count())->toBe(1)
        ->and(AuthorGoalPeriod::query()->count())->toBe(1)
        ->and(AuthorGoalPeriod::query()->sole()->target)->toBe(3)
        ->and(AuthorGoalPeriod::query()->sole()->published_count)->toBe(0);
});

it('permanently counts only the first publication transition', function (): void {
    $user = User::factory()->create();
    $goal = AuthorGoal::factory()->create([
        'user_id' => $user,
        'effective_from' => '2026-07-13',
        'target' => 2,
    ]);
    $period = AuthorGoalPeriod::factory()->create([
        'goal_id' => $goal,
        'user_id' => $user,
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-07-19',
        'target' => 2,
        'published_count' => 0,
    ]);
    $post = Post::factory()->published()->create(['author_id' => $user, 'published_at' => now()]);

    resolve(RecordFirstPublication::class)->handle($post);
    resolve(RecordFirstPublication::class)->handle($post);

    expect(AuthorPublication::query()->where('post_id', $post->id)->count())->toBe(1)
        ->and($period->refresh()->published_count)->toBe(1);
});

it('allows only complete current or future pauses and pauses do not break streaks', function (): void {
    $user = User::factory()->create();
    $goal = AuthorGoal::factory()->create(['user_id' => $user, 'effective_from' => '2026-06-01']);
    AuthorGoalPeriod::factory()->create(['goal_id' => $goal, 'user_id' => $user, 'starts_on' => '2026-06-29', 'ends_on' => '2026-07-05', 'status' => GoalPeriodStatus::Met]);
    AuthorGoalPeriod::factory()->create(['goal_id' => $goal, 'user_id' => $user, 'starts_on' => '2026-07-06', 'ends_on' => '2026-07-12', 'status' => GoalPeriodStatus::Paused]);
    AuthorGoalPeriod::factory()->create(['goal_id' => $goal, 'user_id' => $user, 'starts_on' => '2026-07-13', 'ends_on' => '2026-07-19', 'status' => GoalPeriodStatus::Met]);
    $future = AuthorGoalPeriod::factory()->create(['goal_id' => $goal, 'user_id' => $user, 'starts_on' => '2026-07-20', 'ends_on' => '2026-07-26', 'status' => GoalPeriodStatus::Scheduled]);

    resolve(PauseGoalPeriod::class)->handle($user, $future);

    expect($future->refresh()->status)->toBe(GoalPeriodStatus::Paused)
        ->and(resolve(GoalStreak::class)->handle($user))->toBe(2);
});

it('finalizes an already met period as met instead of pausing it', function (): void {
    $user = User::factory()->create();
    $goal = AuthorGoal::factory()->create(['user_id' => $user, 'effective_from' => '2026-07-13', 'target' => 2]);
    $period = AuthorGoalPeriod::factory()->create([
        'goal_id' => $goal,
        'user_id' => $user,
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-07-19',
        'target' => 2,
        'published_count' => 2,
        'status' => GoalPeriodStatus::Active,
    ]);

    resolve(PauseGoalPeriod::class)->handle($user, $period);

    expect($period->refresh()->status)->toBe(GoalPeriodStatus::Met)
        ->and(AuthorGoalPause::query()->count())->toBe(0)
        ->and(resolve(GoalStreak::class)->handle($user))->toBe(1);
});

it('resumes a paused period and removes the pause window', function (): void {
    $user = User::factory()->create();
    $goal = AuthorGoal::factory()->create(['user_id' => $user, 'effective_from' => '2026-07-13']);
    $period = AuthorGoalPeriod::factory()->create([
        'goal_id' => $goal,
        'user_id' => $user,
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-07-19',
        'target' => 1,
        'published_count' => 0,
        'status' => GoalPeriodStatus::Active,
    ]);
    resolve(PauseGoalPeriod::class)->handle($user, $period);

    $resumed = resolve(ResumeGoalPeriod::class)->handle($user, $period->refresh());

    expect($resumed->status)->toBe(GoalPeriodStatus::Active)
        ->and($resumed->finalized_at)->toBeNull()
        ->and(AuthorGoalPause::query()->count())->toBe(0);
});

it('rejects pausing or resuming another administrator goal period', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $goal = AuthorGoal::factory()->create(['user_id' => $owner, 'effective_from' => '2026-07-13']);
    $period = AuthorGoalPeriod::factory()->create([
        'goal_id' => $goal,
        'user_id' => $owner,
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-07-19',
        'target' => 1,
        'published_count' => 0,
        'status' => GoalPeriodStatus::Active,
    ]);

    $this->actingAs($other)->post(route('dashboard.goals.pause', $period))->assertForbidden();
    $this->actingAs($other)->post(route('dashboard.goals.resume', $period))->assertForbidden();
    expect($period->refresh()->status)->toBe(GoalPeriodStatus::Active);
});

it('resumes a paused goal period over http', function (): void {
    $user = User::factory()->create();
    $goal = AuthorGoal::factory()->create(['user_id' => $user, 'effective_from' => '2026-07-13']);
    $period = AuthorGoalPeriod::factory()->create([
        'goal_id' => $goal,
        'user_id' => $user,
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-07-19',
        'target' => 1,
        'published_count' => 0,
        'status' => GoalPeriodStatus::Active,
    ]);
    resolve(PauseGoalPeriod::class)->handle($user, $period);

    $this->actingAs($user)->post(route('dashboard.goals.resume', $period))->assertRedirect();

    expect($period->refresh()->status)->toBe(GoalPeriodStatus::Active);
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

it('hides the score when no publishing goal is active', function (): void {
    $user = User::factory()->create();
    AnalyticsSyncRun::factory()->create(['completed_at' => now()]);

    $snapshot = resolve(CalculateMomentum::class)->handle($user);

    expect($snapshot->score)->toBeNull()
        ->and($snapshot->level)->toBeNull()
        ->and($snapshot->components['goal']['status'])->toBe('not_set');
});

it('retains the last momentum score while marking stale source data honestly', function (): void {
    $user = User::factory()->create();
    $first = AnalyticsMomentumSnapshot::factory()->create([
        'user_id' => $user,
        'scored_on' => '2026-07-19',
        'score' => 74,
        'components' => ['goal_progress' => ['score' => 30]],
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

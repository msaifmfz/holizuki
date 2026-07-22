<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\CheckAnalyticsHealth;
use App\Domain\Analytics\Actions\EvaluateMilestones;
use App\Domain\Analytics\Actions\GenerateInsights;
use App\Domain\Analytics\Actions\ProjectAnalyticsUrlAliases;
use App\Domain\Analytics\Actions\SyncAnalyticsRange;
use App\Domain\Analytics\Contracts\AnalyticsAdminGateway;
use App\Domain\Analytics\Jobs\PrepareCustomSnapshot;
use App\Domain\Analytics\Jobs\SyncAnalyticsMonth;
use App\Domain\Analytics\Models\AnalyticsInsight;
use App\Domain\Analytics\Models\AnalyticsMilestone;
use App\Domain\Analytics\Models\AnalyticsSnapshotPreparation;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\ValueObjects\AnalyticsHealthResult;
use App\Domain\Analytics\ValueObjects\AnalyticsReconcileResult;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Mockery\MockInterface;

beforeEach(function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow('2026-07-22 12:00:00');
});

it('reports healthy and unhealthy analytics configuration from the console', function (): void {
    /** @var CheckAnalyticsHealth&MockInterface $health */
    $health = Mockery::mock(CheckAnalyticsHealth::class);
    $health->shouldReceive('handle')->twice()->andReturn(
        new AnalyticsHealthResult(false, ['Invalid property.'], ['Check retention.'], []),
        new AnalyticsHealthResult(true, [], [], []),
    );
    app()->instance(CheckAnalyticsHealth::class, $health);

    $this->artisan('analytics:health')
        ->expectsOutputToContain('Invalid property.')
        ->expectsOutputToContain('Check retention.')
        ->assertFailed();

    $this->artisan('analytics:health')
        ->expectsOutputToContain('Google Analytics configuration is healthy.')
        ->assertSuccessful();
});

it('runs insight and milestone evaluation for administrators only', function (): void {
    $administrator = User::factory()->create();
    User::factory()->reader()->create();

    /** @var EvaluateMilestones&MockInterface $evaluate */
    $evaluate = Mockery::mock(EvaluateMilestones::class);
    $evaluate->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $user): bool => $user->is($administrator))
        ->andReturn(collect([new AnalyticsMilestone, new AnalyticsMilestone]));
    app()->instance(EvaluateMilestones::class, $evaluate);

    /** @var GenerateInsights&MockInterface $generate */
    $generate = Mockery::mock(GenerateInsights::class);
    $generate->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $user): bool => $user->is($administrator))
        ->andReturn(collect([new AnalyticsInsight]));
    app()->instance(GenerateInsights::class, $generate);

    $this->artisan('analytics:evaluate-milestones')
        ->expectsOutputToContain('Evaluated 2 achieved milestone(s).')
        ->assertSuccessful();
    $this->artisan('analytics:generate-insights')
        ->expectsOutputToContain('Generated 1 evidence-backed insight(s).')
        ->assertSuccessful();
});

it('reconciles analytics configuration and rejects invalid dimensions', function (): void {
    config()->set('analytics.custom_dimensions', ['content_key', 'author_id']);

    /** @var AnalyticsAdminGateway&MockInterface $admin */
    $admin = Mockery::mock(AnalyticsAdminGateway::class);
    $admin->shouldReceive('reconcile')
        ->once()
        ->with(['content_key', 'author_id'])
        ->andReturn(new AnalyticsReconcileResult(['author_id'], true, true));
    app()->instance(AnalyticsAdminGateway::class, $admin);

    /** @var ProjectAnalyticsUrlAliases&MockInterface $aliases */
    $aliases = Mockery::mock(ProjectAnalyticsUrlAliases::class);
    $aliases->shouldReceive('reconcile')->once()->andReturn(2);
    app()->instance(ProjectAnalyticsUrlAliases::class, $aliases);

    $this->artisan('analytics:reconcile')
        ->expectsOutputToContain('Reconciled 1 dimensions, key event: created, retention: updated, and 2 post aliases.')
        ->assertSuccessful();

    config()->set('analytics.custom_dimensions', 'invalid');

    $this->artisan('analytics:reconcile')
        ->expectsOutputToContain('Analytics custom dimensions are invalid.')
        ->assertFailed();
});

it('syncs the expected daily and recent ranges', function (): void {
    $commands = [];
    $run = AnalyticsSyncRun::factory()->make([
        'run_id' => '01JTESTSYNC0000000000000000',
        'row_count' => 12,
    ]);

    /** @var SyncAnalyticsRange&MockInterface $sync */
    $sync = Mockery::mock(SyncAnalyticsRange::class);
    $sync->shouldReceive('handle')
        ->twice()
        ->withArgs(function (CarbonImmutable $startsOn, CarbonImmutable $endsOn, string $command) use (&$commands): bool {
            $commands[] = [$startsOn->toDateString(), $endsOn->toDateString(), $command];

            return true;
        })
        ->andReturn($run);
    app()->instance(SyncAnalyticsRange::class, $sync);

    $this->artisan('analytics:sync-daily')
        ->expectsOutputToContain('completed with 12 rows')
        ->assertSuccessful();
    $this->artisan('analytics:sync-recent')
        ->expectsOutputToContain('completed with 12 rows')
        ->assertSuccessful();

    expect($commands)->toBe([
        ['2026-07-15', '2026-07-22', 'analytics:sync-daily'],
        ['2026-07-19', '2026-07-22', 'analytics:sync-recent'],
    ]);
});

it('validates backfill options and queues a relative-day range', function (): void {
    Bus::fake();

    $this->artisan('analytics:backfill', ['--from' => '2026-07-01'])
        ->expectsOutputToContain('Both --from and --to are required')
        ->assertFailed();
    $this->artisan('analytics:backfill', ['--days' => 'invalid'])
        ->expectsOutputToContain('--days must be between 1 and 366')
        ->assertFailed();
    $this->artisan('analytics:backfill', ['--from' => '2026-07-22', '--to' => '2026-07-21'])
        ->expectsOutputToContain('inclusive backfill range')
        ->assertFailed();
    $this->artisan('analytics:backfill', ['--days' => '2'])->assertSuccessful();

    Bus::assertChained([
        new SyncAnalyticsMonth('2026-07-21', '2026-07-22'),
    ]);
});

it('executes analytics month jobs with overlap protection', function (): void {
    $calls = [];

    /** @var SyncAnalyticsRange&MockInterface $sync */
    $sync = Mockery::mock(SyncAnalyticsRange::class);
    $sync->shouldReceive('handle')
        ->once()
        ->withArgs(function (CarbonImmutable $startsOn, CarbonImmutable $endsOn, string $command, int $attempt) use (&$calls): bool {
            $calls[] = [$startsOn->toDateString(), $endsOn->toDateString(), $command, $attempt];

            return true;
        });

    $job = new SyncAnalyticsMonth('2026-06-01', '2026-06-30');
    $job->handle($sync);

    expect($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($calls)->toBe([['2026-06-01', '2026-06-30', 'analytics:backfill', 1]]);
});

it('marks a prepared custom snapshot as ready', function (): void {
    $preparation = AnalyticsSnapshotPreparation::factory()->create([
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-30',
    ]);

    /** @var SyncAnalyticsRange&MockInterface $sync */
    $sync = Mockery::mock(SyncAnalyticsRange::class);
    $sync->shouldReceive('handle')->once()->withArgs(
        fn (CarbonImmutable $startsOn, CarbonImmutable $endsOn, string $command, int $attempt, bool $includeCustomPeriod): bool => $startsOn->toDateString() === '2026-06-01'
            && $endsOn->toDateString() === '2026-06-30'
            && $command === 'custom-snapshot'
            && $attempt === 1
            && $includeCustomPeriod,
    );

    $job = new PrepareCustomSnapshot($preparation->id);
    $job->handle($sync);

    expect($job->uniqueId())->toBe((string) $preparation->id)
        ->and($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($preparation->refresh()->status)->toBe('ready')
        ->and($preparation->completed_at)->not->toBeNull();
});

it('records a safe error when custom snapshot preparation fails', function (): void {
    $preparation = AnalyticsSnapshotPreparation::factory()->create();

    /** @var SyncAnalyticsRange&MockInterface $sync */
    $sync = Mockery::mock(SyncAnalyticsRange::class);
    $sync->shouldReceive('handle')->once()->andThrow(new RuntimeException('Sensitive provider failure.'));

    $job = new PrepareCustomSnapshot($preparation->id);

    expect(fn () => $job->handle($sync))->toThrow(RuntimeException::class, 'Sensitive provider failure.');
    expect($preparation->refresh()->status)->toBe('failed')
        ->and($preparation->sanitized_error)->toBe('The custom analytics range could not be prepared.');
});

<?php

use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Exceptions\AssistantUnavailableException;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->runtimeRoot = sys_get_temp_dir().'/holizuki-runtime-'.uniqid();
    config()->set('assistant.enabled', true);
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-doctor'));
    config()->set('assistant.claude_version', '2.1.218');
    config()->set('assistant.oauth_token', 'test-oauth-token');
    config()->set('assistant.home', $this->runtimeRoot.'/home');
    config()->set('assistant.workspaces', $this->runtimeRoot.'/workspaces');
    config()->set('assistant.availability.cache_key', 'assistant:test-health-'.uniqid());
    Cache::clear();
});

afterEach(function (): void {
    Date::setTestNow();
    File::deleteDirectory($this->runtimeRoot);
});

test('assistant doctor verifies the pinned binary without clearing a provider cooldown', function (): void {
    Date::setTestNow('2026-07-24 12:00:00');
    $availability = resolve(AssistantAvailability::class);
    $availability->record(AgentEvent::rateLimit('rejected', now()->addMinutes(5)->toIso8601String()));

    $this->artisan('assistant:doctor')
        ->expectsOutput('Claude Code 2.1.218 is ready with subscription OAuth.')
        ->assertSuccessful();

    expect($availability->state())->toMatchArray([
        'available' => false,
        'status' => 'cooldown',
        'code' => AgentFailureCode::RateLimit->value,
        'retry_at' => '2026-07-24T12:05:00+00:00',
    ]);
});

test('assistant doctor clears a latched permanent failure once the runtime is healthy', function (): void {
    $availability = resolve(AssistantAvailability::class);
    $availability->record(AgentEvent::error(
        AgentFailureCode::AuthenticationFailed->message(),
        AgentFailureCode::AuthenticationFailed->value,
    ));

    expect($availability->state())->toMatchArray([
        'available' => false,
        'status' => 'unavailable',
        'code' => AgentFailureCode::AuthenticationFailed->value,
    ]);

    $this->artisan('assistant:doctor')
        ->expectsOutput('Claude Code 2.1.218 is ready with subscription OAuth.')
        ->assertSuccessful();

    expect($availability->state())->toMatchArray(['available' => true, 'status' => 'available']);
});

test('assistant doctor fails safely without overwriting shared availability state', function (): void {
    $cacheKey = config()->string('assistant.availability.cache_key');
    $cachedState = ['sentinel' => 'unchanged'];
    Cache::put($cacheKey, $cachedState, now()->addMinute());
    config()->set('assistant.oauth_token');

    $this->artisan('assistant:doctor')
        ->expectsOutput('CLAUDE_CODE_OAUTH_TOKEN is not configured.')
        ->assertFailed();

    expect(Cache::get($cacheKey))->toBe($cachedState);
});

test('a rejected rate limit enters a shared cooldown and renders retry metadata', function (): void {
    Date::setTestNow('2026-07-24 12:00:00');
    $availability = resolve(AssistantAvailability::class);
    $availability->record(AgentEvent::rateLimit('rejected', now()->addMinutes(5)->toIso8601String()));

    $state = $availability->state();

    expect($state)->toMatchArray([
        'available' => false,
        'status' => 'cooldown',
        'code' => AgentFailureCode::RateLimit->value,
        'retry_at' => '2026-07-24T12:05:00+00:00',
    ]);

    try {
        $availability->ensureCanStart();
        $this->fail('Expected the cooldown to reject a new assistant turn.');
    } catch (AssistantUnavailableException $exception) {
        $response = $exception->render(Request::create('/assistant', 'POST'));

        expect($response->getStatusCode())->toBe(429)
            ->and($response->headers->get('Retry-After'))->toBe('300')
            ->and($response->getData(true))->toMatchArray([
                'code' => AgentFailureCode::RateLimit->value,
                'retry_at' => '2026-07-24T12:05:00+00:00',
            ]);
    }

    Date::setTestNow('2026-07-24 12:05:01');

    expect($availability->state())
        ->toMatchArray(['available' => true, 'status' => 'available']);
});

test('an invalid provider retry timestamp uses the configured fallback cooldown', function (): void {
    Date::setTestNow('2026-07-24 12:00:00');
    config()->set('assistant.availability.rate_limit_fallback_seconds', 120);
    $availability = resolve(AssistantAvailability::class);

    $availability->record(AgentEvent::rateLimit('rejected', 'not-a-timestamp'));

    expect($availability->state())->toMatchArray([
        'available' => false,
        'status' => 'cooldown',
        'code' => AgentFailureCode::RateLimit->value,
        'retry_at' => '2026-07-24T12:02:00+00:00',
    ]);
});

test('provider authentication failures remain unavailable until cleared', function (): void {
    $availability = resolve(AssistantAvailability::class);
    $availability->record(AgentEvent::error(
        AgentFailureCode::AuthenticationFailed->message(),
        AgentFailureCode::AuthenticationFailed->value,
    ));

    expect($availability->state())->toMatchArray([
        'available' => false,
        'status' => 'unavailable',
        'code' => AgentFailureCode::AuthenticationFailed->value,
    ]);

    $availability->clear();

    expect($availability->state())->toMatchArray(['available' => true]);
});

test('changing runtime credentials clears a cached permanent failure', function (): void {
    $availability = resolve(AssistantAvailability::class);
    $availability->record(AgentEvent::error(
        AgentFailureCode::AuthenticationFailed->message(),
        AgentFailureCode::AuthenticationFailed->value,
    ));

    expect($availability->state()['available'])->toBeFalse();

    config()->set('assistant.oauth_token', 'replacement-oauth-token');

    expect($availability->state())
        ->toMatchArray(['available' => true, 'status' => 'available']);
});

test('local process failures pause starts instead of hammering the runtime', function (): void {
    $availability = resolve(AssistantAvailability::class);
    $availability->record(AgentEvent::error(
        AgentFailureCode::ProcessStart->message(),
        AgentFailureCode::ProcessStart->value,
    ));

    expect($availability->state())->toMatchArray([
        'available' => false,
        'status' => 'cooldown',
        'code' => AgentFailureCode::ProcessStart->value,
    ]);
});

test('a shorter generic cooldown cannot replace a later usage reset', function (): void {
    Date::setTestNow('2026-07-24 12:00:00');
    $availability = resolve(AssistantAvailability::class);
    $availability->record(AgentEvent::rateLimit(
        'rejected',
        now()->addMinutes(15)->toIso8601String(),
    ));

    $availability->record(AgentEvent::error(
        AgentFailureCode::ProcessFailed->message(),
        AgentFailureCode::ProcessFailed->value,
    ));

    expect($availability->state())->toMatchArray([
        'status' => 'cooldown',
        'code' => AgentFailureCode::RateLimit->value,
        'retry_at' => '2026-07-24T12:15:00+00:00',
    ]);
});

test('a later cooldown extends an existing shorter cooldown', function (): void {
    Date::setTestNow('2026-07-24 12:00:00');
    $availability = resolve(AssistantAvailability::class);
    $availability->record(AgentEvent::error(
        AgentFailureCode::ProcessFailed->message(),
        AgentFailureCode::ProcessFailed->value,
    ));

    $availability->record(AgentEvent::rateLimit(
        'rejected',
        now()->addMinutes(15)->toIso8601String(),
    ));

    expect($availability->state())->toMatchArray([
        'status' => 'cooldown',
        'code' => AgentFailureCode::RateLimit->value,
        'retry_at' => '2026-07-24T12:15:00+00:00',
    ]);
});

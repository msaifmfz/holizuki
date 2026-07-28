<?php

use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Services\ClaudeCliRunner;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/holizuki-cli-'.uniqid();
    $this->workspace = $this->root.'/runs/test-run';
    File::ensureDirectoryExists($this->workspace);
    config()->set('assistant.home', sys_get_temp_dir().'/holizuki-cli-home');
    config()->set('assistant.oauth_token', 'test-oauth-token');
    config()->set('assistant.availability.cache_key', 'assistant:test-availability-'.uniqid());
    Cache::clear();
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
    putenv('APP_KEY');
    putenv('ANTHROPIC_API_KEY');
    $_ENV = array_diff_key($_ENV, ['APP_KEY' => true]);
    $_SERVER = array_diff_key($_SERVER, ['ANTHROPIC_API_KEY' => true]);
});

function cliRequest(
    string $root,
    string $workspace,
    int $timeout = 10,
    int $startupTimeout = 5,
    int $idleTimeout = 5,
    int $maxStdoutBytes = 1_000_000,
    bool $noSessionPersistence = false,
    bool $resume = false,
): AgentRequest {
    return new AgentRequest(
        prompt: 'Improve the intro paragraph.',
        workingDirectory: $root,
        workspacePath: $workspace,
        model: 'claude-haiku-4-5',
        sessionId: '22222222-2222-2222-2222-222222222222',
        resume: $resume,
        systemPrompt: 'You are a test.',
        enabledTools: ['Read', 'Edit', 'Write'],
        allowedToolRules: ['Read(runs/test-run/**)', 'Edit(runs/test-run/draft.md)'],
        disallowedTools: ['Bash'],
        maxTurns: 3,
        timeout: $timeout,
        startupTimeout: $startupTimeout,
        idleTimeout: $idleTimeout,
        maxStdoutBytes: $maxStdoutBytes,
        noSessionPersistence: $noSessionPersistence,
    );
}

test('the runner streams parsed events from a real process', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude'));

    $events = iterator_to_array(
        resolve(ClaudeCliRunner::class)->stream(cliRequest($this->root, $this->workspace)),
        false,
    );

    expect(array_map(fn (AgentEvent $event): string => $event->type, $events))
        ->toBe([AgentEvent::TYPE_INIT, AgentEvent::TYPE_TEXT, AgentEvent::TYPE_TOOL_USE, AgentEvent::TYPE_RESULT]);

    expect($events[0]->sessionId)->toBe('fixture-session')
        ->and($events[1]->text)->toBe('Working on it.')
        ->and($events[2]->tool)->toBe('Edit')
        ->and($events[3]->costUsd)->toBe(0.001)
        ->and($events[3]->durationMs)->toBe(42)
        ->and($events[3]->subtype)->toBe('success');
});

test('the process runs at the stable root with hardened flags', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude'));

    iterator_to_array(resolve(ClaudeCliRunner::class)->stream(
        cliRequest($this->root, $this->workspace, noSessionPersistence: true),
    ), false);

    $arguments = File::get($this->root.'/arguments.txt');

    expect(File::get($this->root.'/prompt.txt'))->toBe('Improve the intro paragraph.')
        ->and(File::get($this->root.'/home.txt'))->toBe(config('assistant.home'))
        ->and(File::exists($this->root.'/touched.txt'))->toBeTrue()
        ->and($arguments)->toContain('--safe-mode')
        ->and($arguments)->toContain('--permission-mode')
        ->and($arguments)->toContain('dontAsk')
        ->and($arguments)->toContain('--setting-sources')
        ->and($arguments)->toContain('--disable-slash-commands')
        ->and($arguments)->toContain('--strict-mcp-config')
        ->and($arguments)->toContain('--no-session-persistence')
        ->and($arguments)->toContain("--tools\nRead,Edit,Write\n")
        ->and($arguments)->toContain("--allowed-tools\nRead(runs/test-run/**),Edit(runs/test-run/draft.md)\n")
        ->and($arguments)->not->toContain('Write(')
        ->and($arguments)->toContain("--disallowed-tools\nBash\n")
        ->and($arguments)->toContain("--session-id\n22222222-2222-2222-2222-222222222222\n")
        ->and($arguments)->not->toContain('--append-system-prompt');
});

test('a resumed request keeps the existing Claude session', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude'));

    iterator_to_array(resolve(ClaudeCliRunner::class)->stream(
        cliRequest($this->root, $this->workspace, resume: true),
    ), false);

    $arguments = File::get($this->root.'/arguments.txt');

    expect($arguments)
        ->toContain("--resume\n22222222-2222-2222-2222-222222222222\n")
        ->not->toContain('--session-id');
});

test('the child receives OAuth but no application or API credentials', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude'));
    putenv('APP_KEY=secret-app-key');
    putenv('ANTHROPIC_API_KEY=secret-api-key');
    $_ENV['APP_KEY'] = 'secret-app-key';
    $_SERVER['ANTHROPIC_API_KEY'] = 'secret-api-key';

    iterator_to_array(
        resolve(ClaudeCliRunner::class)->stream(cliRequest($this->root, $this->workspace)),
        false,
    );

    expect(File::get($this->root.'/environment.txt'))
        ->toContain('app_key=unset')
        ->toContain('anthropic_api_key=unset')
        ->toContain('oauth=set');
});

test('a failing process yields a typed safe authentication error', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-error'));

    $events = iterator_to_array(
        resolve(ClaudeCliRunner::class)->stream(cliRequest($this->root, $this->workspace)),
        false,
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(AgentEvent::TYPE_ERROR)
        ->and($events[0]->code)->toBe(AgentFailureCode::AuthenticationFailed->value)
        ->and($events[0]->text)->toBe(AgentFailureCode::AuthenticationFailed->message())
        ->and($events[0]->text)->not->toContain('claude: fatal');
});

test('an error result remains the single terminal event even with a nonzero exit', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-structured-error'));

    $events = iterator_to_array(
        resolve(ClaudeCliRunner::class)->stream(cliRequest($this->root, $this->workspace)),
        false,
    );

    expect(array_map(fn (AgentEvent $event): string => $event->type, $events))
        ->toBe([AgentEvent::TYPE_INIT, AgentEvent::TYPE_RESULT])
        ->and($events[1]->isError)->toBeTrue()
        ->and($events[1]->code)->toBe(AgentFailureCode::RateLimit->value)
        ->and($events[1]->usage)->toBe(['input_tokens' => 10]);
});

test('a process without a terminal result yields a protocol error', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-no-result'));

    $events = iterator_to_array(
        resolve(ClaudeCliRunner::class)->stream(cliRequest($this->root, $this->workspace)),
        false,
    );

    $last = end($events);

    expect($last)->toBeInstanceOf(AgentEvent::class)
        ->and($last->type)->toBe(AgentEvent::TYPE_ERROR)
        ->and($last->code)->toBe(AgentFailureCode::ProtocolError->value);
});

test('a process that exceeds the total timeout is stopped', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-slow'));

    $events = iterator_to_array(resolve(ClaudeCliRunner::class)->stream(
        cliRequest($this->root, $this->workspace, timeout: 1, startupTimeout: 10, idleTimeout: 10),
    ), false);

    $last = end($events);

    expect($last)->toBeInstanceOf(AgentEvent::class)
        ->and($last->code)->toBe(AgentFailureCode::TurnTimeout->value);
});

test('a process that stops producing output is stopped by the idle timeout', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-idle'));

    $events = iterator_to_array(resolve(ClaudeCliRunner::class)->stream(
        cliRequest($this->root, $this->workspace, timeout: 10, startupTimeout: 5, idleTimeout: 1),
    ), false);

    $last = end($events);

    expect($last)->toBeInstanceOf(AgentEvent::class)
        ->and($last->code)->toBe(AgentFailureCode::IdleTimeout->value);
});

test('closing the event generator stops the child process', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-idle'));
    $events = resolve(ClaudeCliRunner::class)->stream(
        cliRequest($this->root, $this->workspace, timeout: 10, startupTimeout: 5, idleTimeout: 10),
    );

    expect($events->current()->type)->toBe(AgentEvent::TYPE_INIT);

    unset($events);

    expect(File::exists($this->root.'/stopped.txt'))->toBeTrue();
});

test('stdout is stopped at the configured byte ceiling', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude'));

    $events = iterator_to_array(resolve(ClaudeCliRunner::class)->stream(
        cliRequest($this->root, $this->workspace, maxStdoutBytes: 50),
    ), false);

    expect($events)->toHaveCount(1)
        ->and($events[0]->code)->toBe(AgentFailureCode::OutputLimit->value);
});

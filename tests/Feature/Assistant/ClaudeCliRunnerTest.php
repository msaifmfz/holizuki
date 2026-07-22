<?php

use App\Domain\Assistant\Services\ClaudeCliRunner;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->workspace = sys_get_temp_dir().'/holizuki-cli-'.uniqid();
    File::ensureDirectoryExists($this->workspace);
    config()->set('assistant.home', sys_get_temp_dir().'/holizuki-cli-home');
});

afterEach(function (): void {
    File::deleteDirectory($this->workspace);
});

function cliRequest(string $workspace, int $timeout = 10): AgentRequest
{
    return new AgentRequest(
        prompt: 'Improve the intro paragraph.',
        workspacePath: $workspace,
        model: 'claude-haiku-4-5',
        sessionId: '22222222-2222-2222-2222-222222222222',
        resume: false,
        systemPrompt: 'You are a test.',
        allowedTools: ['Read', 'Edit'],
        disallowedTools: ['Bash'],
        maxTurns: 3,
        timeout: $timeout,
    );
}

test('the runner streams parsed events from a real process', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude'));

    $events = iterator_to_array(resolve(ClaudeCliRunner::class)->stream(cliRequest($this->workspace)), false);

    expect(array_map(fn (AgentEvent $event): string => $event->type, $events))
        ->toBe([AgentEvent::TYPE_INIT, AgentEvent::TYPE_TEXT, AgentEvent::TYPE_TOOL_USE, AgentEvent::TYPE_RESULT]);

    expect($events[0]->sessionId)->toBe('fixture-session')
        ->and($events[1]->text)->toBe('Working on it.')
        ->and($events[2]->tool)->toBe('Edit')
        ->and($events[3]->costUsd)->toBe(0.001)
        ->and($events[3]->durationMs)->toBe(42);
});

test('the process runs inside the workspace with the configured home', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude'));

    iterator_to_array(resolve(ClaudeCliRunner::class)->stream(cliRequest($this->workspace)), false);

    expect(File::get($this->workspace.'/prompt.txt'))->toBe('Improve the intro paragraph.')
        ->and(File::get($this->workspace.'/home.txt'))->toBe(config('assistant.home'))
        ->and(File::exists($this->workspace.'/touched.txt'))->toBeTrue();
});

test('a failing process yields its stderr as an error event', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-error'));

    $events = iterator_to_array(resolve(ClaudeCliRunner::class)->stream(cliRequest($this->workspace)), false);

    $last = end($events);

    expect($last)->toBeInstanceOf(AgentEvent::class)
        ->and($last->type)->toBe(AgentEvent::TYPE_ERROR)
        ->and($last->text)->toContain('not logged in');
});

test('a process that exceeds the timeout is stopped with an error event', function (): void {
    config()->set('assistant.binary', base_path('tests/Fixtures/fake-claude-slow'));

    $events = iterator_to_array(resolve(ClaudeCliRunner::class)->stream(cliRequest($this->workspace, timeout: 1)), false);

    $last = end($events);

    expect($last)->toBeInstanceOf(AgentEvent::class)
        ->and($last->type)->toBe(AgentEvent::TYPE_ERROR)
        ->and($last->text)->toContain('took too long');
});

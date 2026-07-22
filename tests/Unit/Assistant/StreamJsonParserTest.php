<?php

use App\Domain\Assistant\Services\StreamJsonParser;
use App\Domain\Assistant\ValueObjects\AgentEvent;

test('blank and malformed lines produce no events', function (string $line): void {
    expect(new StreamJsonParser()->parse($line))->toBe([]);
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'not json' => ['plain text output'],
    'json scalar' => ['42'],
    'unknown type' => ['{"type":"telemetry","x":1}'],
]);

test('an init event carries the session id', function (): void {
    $events = new StreamJsonParser()->parse('{"type":"system","subtype":"init","session_id":"abc-123","model":"claude-opus-4-8"}');

    expect($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(AgentEvent::TYPE_INIT)
        ->and($events[0]->sessionId)->toBe('abc-123');
});

test('assistant messages yield text and tool-use events in order', function (): void {
    $line = json_encode([
        'type' => 'assistant',
        'message' => ['content' => [
            ['type' => 'text', 'text' => 'Let me tighten the intro.'],
            ['type' => 'tool_use', 'name' => 'Edit', 'input' => ['file_path' => '/workspace/7/draft.md']],
            ['type' => 'tool_use', 'name' => 'Read', 'input' => ['file_path' => 'meta.json']],
        ]],
    ]);

    $events = new StreamJsonParser()->parse((string) $line);

    expect($events)->toHaveCount(3)
        ->and($events[0]->type)->toBe(AgentEvent::TYPE_TEXT)
        ->and($events[0]->text)->toBe('Let me tighten the intro.')
        ->and($events[1]->type)->toBe(AgentEvent::TYPE_TOOL_USE)
        ->and($events[1]->tool)->toBe('Edit')
        ->and($events[1]->target)->toBe('draft.md')
        ->and($events[2]->target)->toBe('meta.json');
});

test('result events expose cost, duration, and error state', function (): void {
    $events = new StreamJsonParser()->parse(
        '{"type":"result","subtype":"success","is_error":false,"duration_ms":8123,"total_cost_usd":0.0421,"result":"Done."}',
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(AgentEvent::TYPE_RESULT)
        ->and($events[0]->isError)->toBeFalse()
        ->and($events[0]->costUsd)->toBe(0.0421)
        ->and($events[0]->durationMs)->toBe(8123)
        ->and($events[0]->text)->toBe('Done.');
});

test('an error result is flagged', function (): void {
    $events = new StreamJsonParser()->parse('{"type":"result","subtype":"error_max_turns","is_error":true}');

    expect($events[0]->isError)->toBeTrue()
        ->and($events[0]->costUsd)->toBeNull();
});

<?php

use App\Domain\Assistant\Enums\AgentFailureCode;
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
        ->and($events[0]->subtype)->toBe('success')
        ->and($events[0]->text)->toBe('Done.');
});

test('error results use their errors array and subtype without exposing raw diagnostics', function (): void {
    $events = new StreamJsonParser()->parse(
        '{"type":"result","subtype":"error_during_execution","is_error":true,"errors":["Not logged in · Please run /login"],"usage":{"input_tokens":12,"output_tokens":3},"stop_reason":"error"}',
    );

    expect($events[0]->isError)->toBeTrue()
        ->and($events[0]->code)->toBe(AgentFailureCode::AuthenticationFailed->value)
        ->and($events[0]->text)->toBe(AgentFailureCode::AuthenticationFailed->message())
        ->and($events[0]->text)->not->toContain('/login')
        ->and($events[0]->usage)->toBe(['input_tokens' => 12, 'output_tokens' => 3])
        ->and($events[0]->stopReason)->toBe('error');
});

test('all documented error result subtypes receive stable failure codes', function (
    string $subtype,
    AgentFailureCode $code,
): void {
    $events = new StreamJsonParser()->parse(json_encode([
        'type' => 'result',
        'subtype' => $subtype,
        'is_error' => true,
        'errors' => [],
    ], JSON_THROW_ON_ERROR));

    expect($events[0]->code)->toBe($code->value)
        ->and($events[0]->text)->toBe($code->message());
})->with([
    'maximum turns' => ['error_max_turns', AgentFailureCode::MaxTurns],
    'maximum budget' => ['error_max_budget_usd', AgentFailureCode::MaxBudget],
    'structured output retries' => ['error_max_structured_output_retries', AgentFailureCode::StructuredOutput],
    'execution' => ['error_during_execution', AgentFailureCode::Execution],
]);

test('assistant message errors suppress unsafe provider text', function (): void {
    $events = new StreamJsonParser()->parse(
        '{"type":"assistant","error":"model_not_found","message":{"content":[{"type":"text","text":"raw provider diagnostic"}]}}',
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(AgentEvent::TYPE_ERROR)
        ->and($events[0]->code)->toBe(AgentFailureCode::ModelNotFound->value)
        ->and($events[0]->text)->toBe(AgentFailureCode::ModelNotFound->message())
        ->and($events[0]->text)->not->toContain('raw provider diagnostic');
});

test('API retry events expose safe progress metadata', function (): void {
    $events = new StreamJsonParser()->parse(
        '{"type":"system","subtype":"api_retry","attempt":2,"max_retries":4,"retry_delay_ms":1500,"error_status":529,"error":"overloaded"}',
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(AgentEvent::TYPE_RETRY)
        ->and($events[0]->code)->toBe(AgentFailureCode::ProviderOverloaded->value)
        ->and($events[0]->attempt)->toBe(2)
        ->and($events[0]->retryDelayMs)->toBe(1500);
});

test('rate limit rejections expose their reset timestamp', function (): void {
    $events = new StreamJsonParser()->parse(
        '{"type":"rate_limit_event","rate_limit_info":{"status":"rejected","resetsAt":1784908800000}}',
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(AgentEvent::TYPE_RATE_LIMIT)
        ->and($events[0]->isError)->toBeTrue()
        ->and($events[0]->code)->toBe(AgentFailureCode::RateLimit->value)
        ->and($events[0]->retryAt)->toBe('2026-07-24T16:00:00+00:00');
});

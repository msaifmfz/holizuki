<?php

declare(strict_types=1);

use App\Domain\Assistant\Enums\AssistantTaskType;

test('each task resolves its configured model and turn budget', function (): void {
    config()->set('assistant.models.metadata', 'claude-haiku-4-5');
    config()->set('assistant.max_turns.metadata', 8);

    expect(AssistantTaskType::Metadata->model())->toBe('claude-haiku-4-5')
        ->and(AssistantTaskType::Metadata->maxTurns())->toBe(8);
});

test('only conversational tasks resume the persistent session', function (AssistantTaskType $task, bool $expected): void {
    expect($task->usesPersistentSession())->toBe($expected);
})->with([
    'chat' => [AssistantTaskType::Chat, true],
    'outline' => [AssistantTaskType::Outline, true],
    'transform' => [AssistantTaskType::Transform, false],
    'metadata' => [AssistantTaskType::Metadata, false],
    'images' => [AssistantTaskType::Images, false],
]);

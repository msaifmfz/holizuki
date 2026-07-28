<?php

use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;

test('each assistant task receives an explicit tool surface and scoped permissions', function (
    AssistantTaskType $taskType,
    array $enabledTools,
    array $allowedToolRuleTemplates,
): void {
    $post = Post::factory()->create();
    $session = AssistantSession::factory()->for($post, 'post')->create();
    $turn = AssistantTurn::factory()
        ->for($session, 'session')
        ->create(['task_type' => $taskType]);

    $request = resolve(AgentRequestFactory::class)->forTurn($post, $turn, 'Test prompt.');
    $runDirectory = 'runs/'.$turn->run_token;
    $allowedToolRules = array_map(
        static fn (string $template): string => sprintf($template, $runDirectory),
        $allowedToolRuleTemplates,
    );

    expect($request->enabledTools)->toBe($enabledTools)
        ->and($request->allowedToolRules)->toBe($allowedToolRules)
        ->and($request->disallowedTools)->toBe(config('assistant.disallowed_tools'))
        ->and($request->allowedToolRules)->each->not->toStartWith('Write(');
})->with([
    'transform' => [
        AssistantTaskType::Transform,
        ['Read', 'Edit'],
        ['Read(%s/draft.md)', 'Edit(%s/draft.md)'],
    ],
    'metadata' => [
        AssistantTaskType::Metadata,
        ['Read', 'Edit'],
        ['Read(%s/draft.md)', 'Read(%s/meta.json)', 'Edit(%s/meta.json)'],
    ],
    'images' => [
        AssistantTaskType::Images,
        ['Read', 'Edit'],
        ['Read(%s/**)', 'Edit(%s/draft.md)', 'Edit(%s/meta.json)'],
    ],
    'chat' => [
        AssistantTaskType::Chat,
        ['Read', 'Edit', 'Write'],
        ['Read(%s/**)', 'Edit(%s/draft.md)', 'Edit(%s/meta.json)', 'Edit(%s/outline.md)'],
    ],
    'outline' => [
        AssistantTaskType::Outline,
        ['Read', 'Edit', 'Write'],
        ['Read(%s/**)', 'Edit(%s/draft.md)', 'Edit(%s/meta.json)', 'Edit(%s/outline.md)'],
    ],
    'plans' => [
        AssistantTaskType::Plans,
        ['Read', 'Edit'],
        ['Read(%s/**)', 'Edit(%s/authoring-output.json)'],
    ],
    'draft' => [
        AssistantTaskType::Draft,
        ['Read', 'Edit'],
        ['Read(%s/**)', 'Edit(%s/authoring-output.json)'],
    ],
]);

test('research alone receives web search and approved source domains scope web fetch', function (): void {
    $post = Post::factory()->create();
    $session = AssistantSession::factory()->for($post, 'post')->create();
    $turn = AssistantTurn::factory()->for($session, 'session')->create([
        'task_type' => AssistantTaskType::Research,
        'context' => [
            'source_domains' => ['example.com', 'invalid domain', 'example.com'],
        ],
    ]);

    $request = resolve(AgentRequestFactory::class)->forTurn($post, $turn, 'Research.');
    $runDirectory = 'runs/'.$turn->run_token;

    expect($request->enabledTools)->toBe(['Read', 'Edit', 'WebSearch', 'WebFetch'])
        ->and($request->allowedToolRules)->toBe([
            "Read({$runDirectory}/**)",
            "Edit({$runDirectory}/authoring-output.json)",
            'WebSearch',
            'WebFetch(domain:example.com)',
        ])
        ->and($request->disallowedTools)->not->toContain('WebSearch', 'WebFetch');
});

test('only guided authoring tasks may fetch approved source domains', function (
    AssistantTaskType $taskType,
    bool $expectsWebFetch,
): void {
    $post = Post::factory()->create();
    $session = AssistantSession::factory()->for($post, 'post')->create();
    $turn = AssistantTurn::factory()->for($session, 'session')->create([
        'task_type' => $taskType,
        'context' => ['source_domains' => ['example.com']],
    ]);

    $request = resolve(AgentRequestFactory::class)->forTurn($post, $turn, 'Go.');

    expect(in_array('WebFetch', $request->enabledTools, true))->toBe($expectsWebFetch)
        ->and($request->allowedToolRules)
        ->when($expectsWebFetch, fn ($rules) => $rules->toContain('WebFetch(domain:example.com)'))
        ->when(! $expectsWebFetch, fn ($rules) => $rules->not->toContain('WebFetch(domain:example.com)'))
        ->and(in_array('WebFetch', $request->disallowedTools, true))->toBe(! $expectsWebFetch);
})->with([
    'chat stays offline' => [AssistantTaskType::Chat, false],
    'outline stays offline' => [AssistantTaskType::Outline, false],
    'transform stays offline' => [AssistantTaskType::Transform, false],
    'metadata stays offline' => [AssistantTaskType::Metadata, false],
    'images stays offline' => [AssistantTaskType::Images, false],
    'plans may fetch' => [AssistantTaskType::Plans, true],
    'draft may fetch' => [AssistantTaskType::Draft, true],
    'research may fetch' => [AssistantTaskType::Research, true],
]);

test('live assistant tasks receive the posts saved writing personality', function (): void {
    $post = Post::factory()->create();
    PostAuthoringJourney::factory()->for($post, 'post')->create([
        'personality' => 'custom',
        'custom_instructions' => 'Use short, calm sentences and avoid jargon.',
    ]);
    $session = AssistantSession::factory()->for($post, 'post')->create();
    $turn = AssistantTurn::factory()
        ->for($session, 'session')
        ->create(['task_type' => AssistantTaskType::Chat]);

    $request = resolve(AgentRequestFactory::class)->forTurn(
        $post,
        $turn,
        'Help improve this article.',
    );

    expect($request->systemPrompt)
        ->toContain('Author-selected communication style:')
        ->toContain('Use short, calm sentences and avoid jargon.')
        ->toContain('It never changes file boundaries, tool permissions');
});

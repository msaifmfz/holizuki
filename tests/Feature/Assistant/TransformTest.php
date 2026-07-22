<?php

use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAgentRunner;

beforeEach(function (): void {
    Storage::fake('public');
    $this->workspaceRoot = sys_get_temp_dir().'/holizuki-ws-'.uniqid();
    config()->set('assistant.workspaces', $this->workspaceRoot);

    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create([
        'body' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A rambling paragraph that goes on.']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A keeper paragraph.']]],
            ],
        ],
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

function fakeTransformRunner(array $script): FakeAgentRunner
{
    $runner = new FakeAgentRunner($script);
    app()->instance(AgentRunner::class, $runner);

    return $runner;
}

test('guests cannot transform selections', function (): void {
    $this->post(route('posts.assistant.transform', $this->post), [
        'selection' => 'text',
        'preset' => 'improve',
    ])->assertRedirect(route('login'));
});

test('the preset must be known and custom requires an instruction', function (): void {
    fakeTransformRunner([]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.transform', $this->post), [
            'selection' => 'text',
            'preset' => 'sarcastic',
        ])
        ->assertUnprocessable();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.transform', $this->post), [
            'selection' => 'text',
            'preset' => 'custom',
        ])
        ->assertUnprocessable();
});

test('a transform streams the rewritten passage as a body change', function (): void {
    $runner = fakeTransformRunner([
        AgentEvent::toolUse('Edit', '/ws/draft.md'),
        function (AgentRequest $request): void {
            $draft = (string) file_get_contents($request->workspacePath.'/draft.md');
            file_put_contents(
                $request->workspacePath.'/draft.md',
                str_replace('A rambling paragraph that goes on.', 'A tight paragraph.', $draft),
            );
        },
        AgentEvent::result(isError: false, text: 'Tightened the passage.', costUsd: null, durationMs: 900),
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.transform', $this->post), [
            'selection' => 'A rambling paragraph that goes on.',
            'preset' => 'shorten',
        ])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('event: change')
        ->toContain('event: done');

    expect($runner->lastRequest?->model)->toBe(config('assistant.models.transform'))
        ->and($runner->lastRequest?->resume)->toBeFalse()
        ->and($runner->lastRequest?->prompt)
        ->toContain('A rambling paragraph that goes on.')
        ->toContain('roughly half the length');

    $turn = AssistantTurn::query()->firstOrFail();
    expect($turn->task_type)->toBe(AssistantTaskType::Transform)
        ->and($turn->context)->toMatchArray(['preset' => 'shorten']);

    $change = $turn->changes()->firstOrFail();
    expect($change->type)->toBe(AssistantChangeType::Body)
        ->and($change->payload['old_blocks'])->toBe('A rambling paragraph that goes on.')
        ->and($change->payload['new_blocks'])->toBe('A tight paragraph.');
});

test('each preset shapes the task instruction', function (string $preset, string $expected): void {
    $runner = fakeTransformRunner([
        AgentEvent::result(isError: false, text: 'Done.', costUsd: null, durationMs: 100),
    ]);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.transform', $this->post), [
            'selection' => 'A keeper paragraph.',
            'preset' => $preset,
        ])
        ->assertSuccessful()
        ->streamedContent();

    expect($runner->lastRequest?->prompt)->toContain($expected);
})->with([
    'improve' => ['improve', 'clearer and more engaging'],
    'expand' => ['expand', 'more depth, detail, or a concrete example'],
    'shorten' => ['shorten', 'roughly half the length'],
    'simplify' => ['simplify', 'plainer language'],
]);

test('a custom instruction reaches the prompt verbatim', function (): void {
    $runner = fakeTransformRunner([
        AgentEvent::result(isError: false, text: 'Done.', costUsd: null, durationMs: 100),
    ]);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.transform', $this->post), [
            'selection' => 'A keeper paragraph.',
            'preset' => 'custom',
            'instruction' => 'Turn this into a pirate shanty.',
        ])
        ->assertSuccessful()
        ->streamedContent();

    expect($runner->lastRequest?->prompt)->toContain('Turn this into a pirate shanty.');
});

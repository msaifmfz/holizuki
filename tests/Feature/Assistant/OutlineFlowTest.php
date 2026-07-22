<?php

use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantSession;
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
    $this->post = Post::factory()->for($this->user, 'author')->create(['body' => null]);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

function fakeOutlineRunner(array $script): FakeAgentRunner
{
    $runner = new FakeAgentRunner($script);
    app()->instance(AgentRunner::class, $runner);

    return $runner;
}

test('the step is validated and start requires a topic', function (): void {
    fakeOutlineRunner([]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.outline', $this->post), ['step' => 'publish'])
        ->assertUnprocessable();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.outline', $this->post), ['step' => 'start'])
        ->assertUnprocessable();
});

test('the start step sends the topic and records the outline turn', function (): void {
    $runner = fakeOutlineRunner([
        AgentEvent::text("Here's a working outline — happy to adjust."),
        function (AgentRequest $request): void {
            file_put_contents($request->workspacePath.'/outline.md', "Working title\n\n## Section one\n");
        },
        AgentEvent::result(isError: false, text: 'Outlined.', costUsd: null, durationMs: 900),
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.outline', $this->post), [
            'step' => 'start',
            'message' => 'Why home espresso beats cafe espresso',
        ])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('event: done');

    expect($runner->lastRequest?->model)->toBe(config('assistant.models.outline'))
        ->and($runner->lastRequest?->resume)->toBeFalse()
        ->and($runner->lastRequest?->prompt)
        ->toContain('Why home espresso beats cafe espresso')
        ->toContain('outline.md');

    $turn = AssistantTurn::query()->firstOrFail();
    expect($turn->task_type)->toBe(AssistantTaskType::Outline)
        ->and($turn->context)->toMatchArray(['step' => 'start']);
});

test('the draft step resumes the session and proposes the drafted body', function (): void {
    $session = AssistantSession::factory()->for($this->post, 'post')->create();
    AssistantTurn::factory()->for($session, 'session')->create([
        'task_type' => AssistantTaskType::Outline,
        'status' => AssistantTurnStatus::Completed,
    ]);

    $runner = fakeOutlineRunner([
        function (AgentRequest $request): void {
            file_put_contents(
                $request->workspacePath.'/draft.md',
                "## Section one\n\nThe drafted opening.\n",
            );
        },
        AgentEvent::result(isError: false, text: 'Drafted the article.', costUsd: null, durationMs: 5000),
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.outline', $this->post), ['step' => 'draft'])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('event: change')
        ->toContain('The drafted opening.');

    expect($runner->lastRequest?->resume)->toBeTrue()
        ->and($runner->lastRequest?->sessionId)->toBe($session->claude_session_id)
        ->and($runner->lastRequest?->prompt)->toContain('outline is approved');

    $turn = AssistantTurn::query()->latest('id')->firstOrFail();
    expect($turn->context)->toMatchArray(['step' => 'draft'])
        ->and($turn->changes()->count())->toBe(1);
});

test('outline.md survives workspace re-materialization between steps', function (): void {
    // Two sequential turns against the same workspace: the first writes
    // outline.md, the second (after a fresh materialize) must still see it.
    $runner = fakeOutlineRunner([
        function (AgentRequest $request): AgentEvent {
            $outline = $request->workspacePath.'/outline.md';

            if (! file_exists($outline)) {
                file_put_contents($outline, "## Planned section\n");

                return AgentEvent::result(isError: false, text: 'Outlined.', costUsd: null, durationMs: 100);
            }

            return AgentEvent::result(isError: false, text: 'Outline still present.', costUsd: null, durationMs: 100);
        },
    ]);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.outline', $this->post), ['step' => 'start', 'message' => 'Topic'])
        ->assertSuccessful()
        ->streamedContent();

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.outline', $this->post), ['step' => 'draft'])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('Outline still present.');
});

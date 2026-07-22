<?php

use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
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
    $this->post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'Chat post',
        'body' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Intro']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A dull opening paragraph.']]],
            ],
        ],
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

function fakeChatRunner(array $script): FakeAgentRunner
{
    $runner = new FakeAgentRunner($script);
    app()->instance(AgentRunner::class, $runner);

    return $runner;
}

test('guests cannot chat', function (): void {
    $this->post(route('posts.assistant.chat', $this->post), ['message' => 'Hello'])
        ->assertRedirect(route('login'));
});

test('a message is required', function (): void {
    fakeChatRunner([]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.chat', $this->post), ['message' => ''])
        ->assertUnprocessable();
});

test('a chat turn streams narration and proposes body changes from draft edits', function (): void {
    $runner = fakeChatRunner([
        AgentEvent::text('Let me punch up that opening.'),
        AgentEvent::toolUse('Edit', '/ws/draft.md'),
        function (AgentRequest $request): void {
            $draft = file_get_contents($request->workspacePath.'/draft.md');
            file_put_contents(
                $request->workspacePath.'/draft.md',
                str_replace('A dull opening paragraph.', 'An opening that grabs you by the collar.', (string) $draft),
            );
        },
        AgentEvent::result(isError: false, text: 'Rewrote the opening.', costUsd: null, durationMs: 3100),
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.chat', $this->post), ['message' => 'Make the opening more exciting'])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)
        ->toContain('event: narration')
        ->toContain('event: change')
        ->toContain('grabs you by the collar')
        ->toContain('event: done');

    expect($runner->lastRequest?->model)->toBe(config('assistant.models.chat'))
        ->and($runner->lastRequest?->prompt)->toBe('Make the opening more exciting');

    $turn = AssistantTurn::query()->firstOrFail();
    expect($turn->task_type)->toBe(AssistantTaskType::Chat)
        ->and($turn->status)->toBe(AssistantTurnStatus::Completed);

    $change = $turn->changes()->firstOrFail();
    expect($change->type)->toBe(AssistantChangeType::Body)
        ->and($change->payload['old_blocks'])->toBe('A dull opening paragraph.')
        ->and($change->payload['new_blocks'])->toBe('An opening that grabs you by the collar.')
        ->and($change->payload['anchor_before'])->toBe('## Intro');
});

test('the first chat turn starts fresh and later turns resume the session', function (): void {
    // One runner for both requests — the router caches the controller (and
    // its injected runner) for the lifetime of the test app.
    $runner = fakeChatRunner([AgentEvent::result(isError: false, text: 'Okay.', costUsd: null, durationMs: 50)]);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.chat', $this->post), ['message' => 'First message'])
        ->assertSuccessful()
        ->streamedContent();

    expect($runner->lastRequest?->resume)->toBeFalse();

    $sessionId = AssistantSession::query()->firstOrFail()->claude_session_id;
    expect($runner->lastRequest?->sessionId)->toBe($sessionId);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.chat', $this->post), ['message' => 'Second message'])
        ->assertSuccessful()
        ->streamedContent();

    expect($runner->lastRequest?->resume)->toBeTrue()
        ->and($runner->lastRequest?->sessionId)->toBe($sessionId);
});

test('a failed resume rotates the session id so the next message starts fresh', function (): void {
    $session = AssistantSession::factory()->for($this->post, 'post')->create();
    AssistantTurn::factory()->for($session, 'session')->create([
        'task_type' => AssistantTaskType::Chat,
        'status' => AssistantTurnStatus::Completed,
    ]);
    $originalSessionId = $session->claude_session_id;

    fakeChatRunner([
        AgentEvent::error('No conversation found with session ID: '.$originalSessionId),
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.chat', $this->post), ['message' => 'Are you there?'])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('event: error');
    expect($session->refresh()->claude_session_id)->not->toBe($originalSessionId)
        ->and($session->status)->toBe(AssistantSessionStatus::Idle);
});

test('metadata suggestions coming out of a chat turn are recorded too', function (): void {
    fakeChatRunner([
        function (AgentRequest $request): void {
            $metaPath = $request->workspacePath.'/meta.json';
            $meta = json_decode((string) file_get_contents($metaPath), true);
            $meta['title'] = 'A collar-grabbing headline';
            file_put_contents($metaPath, json_encode($meta));
        },
        AgentEvent::result(isError: false, text: 'Suggested a stronger title.', costUsd: null, durationMs: 800),
    ]);

    $this->actingAs($this->user)
        ->post(route('posts.assistant.chat', $this->post), ['message' => 'Suggest a better title'])
        ->assertSuccessful()
        ->streamedContent();

    $change = AssistantTurn::query()->firstOrFail()->changes()->firstOrFail();
    expect($change->type)->toBe(AssistantChangeType::Title)
        ->and($change->payload['new'])->toBe('A collar-grabbing headline');
});

<?php

use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAgentRunner;

beforeEach(function (): void {
    Storage::fake('public');
    $this->workspaceRoot = sys_get_temp_dir().'/holizuki-ws-'.uniqid();
    config()->set('assistant.workspaces', $this->workspaceRoot);
    config()->set('assistant.home', sys_get_temp_dir().'/holizuki-home');

    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'Original title',
        'excerpt' => 'Original excerpt.',
        'body' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A useful article body.']]]],
        ],
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

function fakeRunner(array $script): FakeAgentRunner
{
    $runner = new FakeAgentRunner($script);
    app()->instance(AgentRunner::class, $runner);

    return $runner;
}

test('guests cannot generate metadata', function (): void {
    $this->post(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertRedirect(route('login'));
});

test('the endpoint reports when the assistant is disabled', function (): void {
    config()->set('assistant.enabled', false);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'assistant_disabled');
});

test('fields must come from the generatable whitelist', function (): void {
    fakeRunner([]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.metadata', $this->post), ['fields' => ['slug']])
        ->assertUnprocessable();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.metadata', $this->post), ['fields' => []])
        ->assertUnprocessable();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.metadata', $this->post), [
            'fields' => ['excerpt', 'excerpt'],
        ])
        ->assertUnprocessable();
});

test('a metadata turn streams narration and records field proposals', function (): void {
    $runner = fakeRunner([
        AgentEvent::init('11111111-1111-1111-1111-111111111111'),
        AgentEvent::text('Reading the draft to write your metadata.'),
        AgentEvent::toolUse('Edit', '/ws/meta.json'),
        function (AgentRequest $request): void {
            $metaPath = $request->workspacePath.'/meta.json';
            $meta = json_decode((string) file_get_contents($metaPath), true);
            $meta['excerpt'] = 'A crisp new excerpt.';
            $meta['seo_title'] = 'Useful article — read this';
            file_put_contents($metaPath, json_encode($meta));
        },
        AgentEvent::result(isError: false, text: 'Updated excerpt and SEO title.', costUsd: 0.012, durationMs: 4200),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt', 'seo_title']]);

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $stream = $response->streamedContent();

    expect($stream)
        ->toContain('event: narration')
        ->toContain('Reading the draft to write your metadata.')
        ->toContain('event: activity')
        ->toContain('event: change')
        ->toContain('event: done');

    $turn = AssistantTurn::query()->firstOrFail();
    $request = $runner->lastRequest ?? throw new RuntimeException('Expected an agent request.');
    $runDirectory = 'runs/'.$turn->run_token;

    expect($request->resume)->toBeFalse()
        ->and($request->model)->toBe(config('assistant.models.metadata'))
        ->and($request->workspacePath)->toBe($this->workspaceRoot.'/'.$this->post->id.'/'.$runDirectory)
        ->and($request->prompt)->toContain($request->workspacePath.'/draft.md')
        ->and($request->prompt)->toContain($request->workspacePath.'/meta.json')
        ->and($request->systemPrompt)->toContain($request->workspacePath.'/draft.md')
        ->and($request->systemPrompt)->toContain($request->workspacePath.'/meta.json')
        ->and($request->enabledTools)->toBe(['Read', 'Edit'])
        ->and($request->allowedToolRules)->toBe([
            "Read({$runDirectory}/draft.md)",
            "Read({$runDirectory}/meta.json)",
            "Edit({$runDirectory}/meta.json)",
        ]);

    expect($turn->status)->toBe(AssistantTurnStatus::Completed)
        ->and($turn->assistant_message)->toContain('Reading the draft')
        ->and((float) $turn->cost_usd)->toBe(0.012)
        ->and($turn->duration_ms)->toBe(4200);

    $changes = $turn->changes()->orderBy('id')->get();
    expect($changes)->toHaveCount(2)
        ->and($changes->pluck('type')->all())->toEqualCanonicalizing([AssistantChangeType::Excerpt, AssistantChangeType::SeoTitle])
        ->and($changes->firstWhere('type', AssistantChangeType::Excerpt)?->payload)->toBe([
            'old' => 'Original excerpt.',
            'new' => 'A crisp new excerpt.',
        ])
        ->and($changes->every(fn ($change): bool => $change->status === AssistantChangeStatus::Proposed))->toBeTrue();

    $session = AssistantSession::query()->firstOrFail();
    expect($session->status)->toBe(AssistantSessionStatus::Idle);
});

test('an agent error fails the turn and streams a friendly message', function (): void {
    fakeRunner([
        AgentEvent::text('Working on it.'),
        AgentEvent::result(
            isError: true,
            text: AgentFailureCode::RateLimit->message(),
            costUsd: null,
            durationMs: null,
            code: AgentFailureCode::RateLimit->value,
            retryAt: now()->addMinutes(15)->toISOString(),
        ),
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('event: error')
        ->toContain('after the cooldown');

    expect(AssistantTurn::query()->firstOrFail()->status)->toBe(AssistantTurnStatus::Failed);
});

test('a second turn is rejected while one is running', function (): void {
    fakeRunner([]);

    AssistantSession::factory()->running()->for($this->post, 'post')->create();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'post_busy');
});

test('a guard failure for the SSE client renders a readable JSON error, not HTML', function (): void {
    fakeRunner([]);

    AssistantSession::factory()->running()->for($this->post, 'post')->create();

    // The real streaming client sends an event-stream Accept and cannot parse
    // an HTML error page — the busy message must come back as JSON.
    $this->actingAs($this->user)
        ->withHeaders([
            'Accept' => 'application/json, text/event-stream',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'code' => 'post_busy',
            'message' => 'The assistant is already working on this post.',
        ]);
});

test('an abandoned running session is reclaimed after the timeout', function (): void {
    fakeRunner([
        AgentEvent::result(isError: false, text: 'Done.', costUsd: null, durationMs: 100),
    ]);

    $session = AssistantSession::factory()->for($this->post, 'post')->create([
        'status' => AssistantSessionStatus::Running,
        'turn_started_at' => now()->subSeconds((int) config('assistant.global_lock_seconds') + 60),
    ]);
    $abandonedTurn = AssistantTurn::factory()->running()->for($session, 'session')->create();
    $session->forceFill(['active_run_token' => $abandonedTurn->run_token])->save();

    $this->actingAs($this->user)
        ->post(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertSuccessful()
        ->streamedContent();

    expect($session->refresh()->status)->toBe(AssistantSessionStatus::Idle)
        ->and(AssistantTurn::query()->where('status', AssistantTurnStatus::Failed)->count())->toBe(1);
});

test('an invalid draft rewrite fails the turn instead of proposing changes', function (): void {
    $media = PostMedia::factory()->for($this->post, 'post')->create();

    fakeRunner([
        function (AgentRequest $request) use ($media): void {
            file_put_contents($request->workspacePath.'/draft.md', "![](media:{$media->id})\n");
        },
        AgentEvent::result(isError: false, text: 'Rewrote the draft.', costUsd: null, durationMs: 900),
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('event: error');
    expect(AssistantTurn::query()->firstOrFail()->status)->toBe(AssistantTurnStatus::Failed)
        ->and(AssistantTurn::query()->firstOrFail()->changes()->count())->toBe(0);
});

test('a crashing runner fails the turn gracefully', function (): void {
    $runner = fakeRunner([
        AgentEvent::text('About to crash.'),
        function (): void {
            throw new RuntimeException('boom');
        },
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('event: error')
        ->toContain('crashed');

    expect(AssistantTurn::query()->firstOrFail()->status)->toBe(AssistantTurnStatus::Failed);
});

test('authentication failures surface as a sign-in hint', function (): void {
    fakeRunner([
        AgentEvent::error(
            AgentFailureCode::AuthenticationFailed->message(),
            AgentFailureCode::AuthenticationFailed->value,
        ),
    ]);

    $stream = $this->actingAs($this->user)
        ->post(route('posts.assistant.metadata', $this->post), ['fields' => ['excerpt']])
        ->assertSuccessful()
        ->streamedContent();

    expect($stream)->toContain('not signed in on the server');
});

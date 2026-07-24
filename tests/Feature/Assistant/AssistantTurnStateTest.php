<?php

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\RequestAssistantCancellation;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Exceptions\DuplicateAssistantRequestException;
use App\Domain\Assistant\Exceptions\OutlineNotReadyException;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('public');
    $this->workspaceRoot = sys_get_temp_dir().'/holizuki-state-'.uniqid();
    config()->set('assistant.workspaces', $this->workspaceRoot);
    config()->set('assistant.global_lock_key', 'assistant:test-run:'.uniqid());
    $this->post = Post::factory()
        ->for(User::factory(), 'author')
        ->create();
});

afterEach(function (): void {
    Cache::lock(config()->string('assistant.global_lock_key'))->forceRelease();
    File::deleteDirectory($this->workspaceRoot);
});

test('starting a turn creates a fenced run and rejects a duplicate request id', function (): void {
    $requestId = (string) Str::uuid();
    $turn = resolve(StartAssistantTurn::class)->handle(
        $this->post,
        AssistantTaskType::Chat,
        'Help me improve this draft.',
        $requestId,
    );
    $session = $turn->session()->firstOrFail();

    expect(Str::isUuid($turn->run_token))->toBeTrue()
        ->and($turn->request_id)->toBe($requestId)
        ->and($session->active_run_token)->toBe($turn->run_token)
        ->and($session->status)->toBe(AssistantSessionStatus::Running)
        ->and(File::isDirectory(resolve(WorkspaceManager::class)->pathFor($this->post, $turn)))->toBeTrue();

    expect(fn () => resolve(StartAssistantTurn::class)->handle(
        $this->post,
        AssistantTaskType::Chat,
        'Do not run twice.',
        $requestId,
    ))->toThrow(DuplicateAssistantRequestException::class);

    resolve(FailAssistantTurn::class)->handle($turn, 'Test cleanup.');
    resolve(AssistantRunLease::class)->release($turn->run_token);
});

test('cancellation stays fenced until the runner records a terminal state', function (): void {
    $turn = resolve(StartAssistantTurn::class)->handle(
        $this->post,
        AssistantTaskType::Chat,
        'A long-running request.',
        (string) Str::uuid(),
    );

    $cancelled = resolve(RequestAssistantCancellation::class)->handle($this->post);
    $session = $turn->session()->firstOrFail();

    expect($cancelled?->status)->toBe(AssistantTurnStatus::Cancelling)
        ->and($cancelled?->cancel_requested_at)->not->toBeNull()
        ->and($session->refresh()->status)->toBe(AssistantSessionStatus::Cancelling)
        ->and($session->active_run_token)->toBe($turn->run_token);

    $changes = resolve(CompleteAssistantTurn::class)->handle(
        $turn,
        $this->post,
        'Late success.',
        null,
        100,
    );

    expect($changes)->toBe([])
        ->and($turn->refresh()->status)->toBe(AssistantTurnStatus::Cancelling);

    resolve(FailAssistantTurn::class)->handle(
        $turn,
        AgentFailureCode::Cancelled->message(),
        AssistantTurnStatus::Cancelled,
        AgentFailureCode::Cancelled,
    );
    resolve(AssistantRunLease::class)->release($turn->run_token);

    expect($turn->refresh()->status)->toBe(AssistantTurnStatus::Cancelled)
        ->and($session->refresh()->status)->toBe(AssistantSessionStatus::Idle)
        ->and($session->active_run_token)->toBeNull();
});

test('a late terminal update cannot release a newer active run', function (): void {
    $session = AssistantSession::factory()->for($this->post, 'post')->create();
    $oldTurn = AssistantTurn::factory()->for($session, 'session')->running()->create();
    $newTurn = AssistantTurn::factory()->for($session, 'session')->running()->create();
    $session->forceFill([
        'status' => AssistantSessionStatus::Running,
        'active_run_token' => $newTurn->run_token,
        'turn_started_at' => now(),
    ])->save();

    $updated = resolve(FailAssistantTurn::class)->handle($oldTurn, 'Late failure.');

    expect($updated)->toBeFalse()
        ->and($oldTurn->refresh()->status)->toBe(AssistantTurnStatus::Running)
        ->and($session->refresh()->active_run_token)->toBe($newTurn->run_token)
        ->and($session->status)->toBe(AssistantSessionStatus::Running);
});

test('a missing transcript explicitly clears resume state', function (): void {
    $turn = AssistantTurn::factory()
        ->for(AssistantSession::factory()->for($this->post, 'post'), 'session')
        ->running()
        ->create();
    $session = $turn->session()->firstOrFail();
    $session->forceFill([
        'status' => AssistantSessionStatus::Running,
        'active_run_token' => $turn->run_token,
        'resume_ready' => true,
        'conversation_model' => AssistantTaskType::Chat->model(),
        'turn_started_at' => now(),
    ])->save();
    $oldClaudeSessionId = $session->claude_session_id;

    resolve(FailAssistantTurn::class)->handle(
        $turn,
        AgentFailureCode::SessionNotFound->message(),
        errorCode: AgentFailureCode::SessionNotFound,
    );

    expect($session->refresh()->resume_ready)->toBeFalse()
        ->and($session->conversation_model)->toBeNull()
        ->and($session->claude_session_id)->not->toBe($oldClaudeSessionId);
});

test('drafting is rejected before a persisted outline is ready', function (): void {
    expect(fn () => resolve(StartAssistantTurn::class)->handle(
        $this->post,
        AssistantTaskType::Outline,
        'Draft from the outline.',
        (string) Str::uuid(),
        ['step' => 'draft'],
    ))->toThrow(OutlineNotReadyException::class);
});

test('turn snapshots reload the post after a concurrent autosave', function (): void {
    $stalePost = $this->post->fresh();
    Post::query()->whereKey($this->post->id)->update(['title' => 'Latest autosaved title']);

    $turn = resolve(StartAssistantTurn::class)->handle(
        $stalePost,
        AssistantTaskType::Chat,
        'Read the latest title.',
        (string) Str::uuid(),
    );

    expect($turn->snapshot_meta['title'])->toBe('Latest autosaved title');

    resolve(FailAssistantTurn::class)->handle($turn, 'Test cleanup.');
    resolve(AssistantRunLease::class)->release($turn->run_token);
});

test('workspace preparation failure removes the partial run and releases the lease', function (): void {
    config()->set('assistant.limits.image_count', 0);
    $path = 'posts/'.$this->post->id.'/inline/pic.webp';
    Storage::disk('public')->put($path, 'image');
    PostMedia::factory()
        ->for($this->post, 'post')
        ->create(['path' => $path]);

    expect(fn () => resolve(StartAssistantTurn::class)->handle(
        $this->post,
        AssistantTaskType::Chat,
        'This should not start.',
        (string) Str::uuid(),
    ))->toThrow(UnexpectedValueException::class);

    $workspace = resolve(WorkspaceManager::class);
    $runs = $workspace->rootFor($this->post).'/runs';

    expect(File::isDirectory($runs) ? File::directories($runs) : [])->toBe([])
        ->and(AssistantTurn::query()->firstOrFail()->status)->toBe(AssistantTurnStatus::Failed);

    config()->set('assistant.limits.image_count', 20);
    $next = resolve(StartAssistantTurn::class)->handle(
        $this->post,
        AssistantTaskType::Metadata,
        'A valid follow-up.',
        (string) Str::uuid(),
    );

    expect($next->status)->toBe(AssistantTurnStatus::Running);

    resolve(FailAssistantTurn::class)->handle($next, 'Test cleanup.');
    resolve(AssistantRunLease::class)->release($next->run_token);
});

test('unsafe persistent failures rotate the conversation before the next turn', function (): void {
    $turn = AssistantTurn::factory()
        ->for(AssistantSession::factory()->for($this->post, 'post'), 'session')
        ->running()
        ->create(['task_type' => AssistantTaskType::Chat]);
    $session = $turn->session()->firstOrFail();
    $session->forceFill([
        'status' => AssistantSessionStatus::Running,
        'active_run_token' => $turn->run_token,
        'resume_ready' => true,
        'conversation_model' => AssistantTaskType::Chat->model(),
        'turn_started_at' => now(),
    ])->save();
    $oldSessionId = $session->claude_session_id;

    resolve(FailAssistantTurn::class)->handle(
        $turn,
        AgentFailureCode::Cancelled->message(),
        AssistantTurnStatus::Cancelled,
        AgentFailureCode::Cancelled,
    );

    expect($session->refresh()->claude_session_id)->not->toBe($oldSessionId)
        ->and($session->resume_ready)->toBeFalse()
        ->and($session->conversation_model)->toBeNull();
});

test('reclaiming an abandoned persistent turn starts a fresh conversation', function (): void {
    $session = AssistantSession::factory()->for($this->post, 'post')->create([
        'status' => AssistantSessionStatus::Running,
        'resume_ready' => true,
        'conversation_model' => AssistantTaskType::Chat->model(),
        'turn_started_at' => now()->subSeconds(
            resolve(AssistantRunLease::class)->durationSeconds() + 1,
        ),
    ]);
    $abandonedTurn = AssistantTurn::factory()->for($session, 'session')->running()->create([
        'task_type' => AssistantTaskType::Chat,
    ]);
    $session->forceFill(['active_run_token' => $abandonedTurn->run_token])->save();
    $oldClaudeSessionId = $session->claude_session_id;

    $nextTurn = resolve(StartAssistantTurn::class)->handle(
        $this->post,
        AssistantTaskType::Chat,
        'Start a safe conversation.',
        (string) Str::uuid(),
    );

    expect($abandonedTurn->refresh()->status)->toBe(AssistantTurnStatus::Failed)
        ->and($abandonedTurn->error_code)->toBe('turn_abandoned')
        ->and($session->refresh()->claude_session_id)->not->toBe($oldClaudeSessionId)
        ->and($session->resume_ready)->toBeFalse()
        ->and($session->conversation_model)->toBe(AssistantTaskType::Chat->model())
        ->and($nextTurn->session()->firstOrFail()->claude_session_id)->toBe($session->claude_session_id);

    resolve(FailAssistantTurn::class)->handle($nextTurn, 'Test cleanup.');
    resolve(AssistantRunLease::class)->release($nextTurn->run_token);
});

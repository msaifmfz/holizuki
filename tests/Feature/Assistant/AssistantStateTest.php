<?php

use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('assistant.availability.cache_key', 'assistant:test-state-'.uniqid());
    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create();
});

test('guests cannot read assistant state', function (): void {
    $this->get(route('posts.assistant.state', $this->post))
        ->assertRedirect(route('login'));
});

test('a post without assistant history returns an idle empty state', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.state', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('available', true)
        ->assertJsonPath('status', 'idle')
        ->assertJsonPath('retry_at', null)
        ->assertJsonPath('active_turn', null)
        ->assertJsonPath('outline_ready', false)
        ->assertJsonPath('changes', [])
        ->assertJsonPath('turns', []);
});

test('a freshly running session reports running', function (): void {
    $session = AssistantSession::factory()->running()->for($this->post, 'post')->create();
    $turn = AssistantTurn::factory()->running()->for($session, 'session')->create();
    $session->forceFill(['active_run_token' => $turn->run_token])->save();

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.state', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('active_turn.id', $turn->id)
        ->assertJsonPath('active_turn.task_type', $turn->task_type->value);
});

test('a running session whose turn outlived the timeout reports idle', function (): void {
    AssistantSession::factory()->for($this->post, 'post')->create([
        'status' => AssistantSessionStatus::Running,
        'turn_started_at' => now()->subSeconds((int) config('assistant.global_lock_seconds') + 60),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.state', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('status', 'idle')
        ->assertJsonPath('active_turn', null);
});

test('an active turn remains visible while future starts are cooling down', function (): void {
    $session = AssistantSession::factory()->running()->for($this->post, 'post')->create();
    $turn = AssistantTurn::factory()->running()->for($session, 'session')->create();
    $session->forceFill(['active_run_token' => $turn->run_token])->save();
    resolve(AssistantAvailability::class)->record(AgentEvent::error(
        AgentFailureCode::ProviderOverloaded->message(),
        AgentFailureCode::ProviderOverloaded->value,
    ));

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.state', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('available', false)
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('active_turn.id', $turn->id);
});

test('state returns pending changes and the conversation in order', function (): void {
    $session = AssistantSession::factory()->for($this->post, 'post')->create();
    $first = AssistantTurn::factory()->for($session, 'session')->create(['user_prompt' => 'First ask']);
    $second = AssistantTurn::factory()->for($session, 'session')->create(['user_prompt' => 'Second ask']);

    $pending = AssistantChange::factory()->for($this->post, 'post')->for($second, 'turn')->create();
    AssistantChange::factory()->for($this->post, 'post')->for($second, 'turn')->create([
        'status' => AssistantChangeStatus::Rejected,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('posts.assistant.state', $this->post))
        ->assertSuccessful();

    $response->assertJsonCount(1, 'changes')
        ->assertJsonPath('changes.0.id', $pending->id)
        ->assertJsonCount(2, 'turns')
        ->assertJsonPath('turns.0.id', $first->id)
        ->assertJsonPath('turns.1.id', $second->id)
        ->assertJsonPath('turns.1.user_prompt', 'Second ask');
});

test('cancel requests cancellation without releasing the active turn', function (): void {
    $session = AssistantSession::factory()->running()->for($this->post, 'post')->create();
    $turn = AssistantTurn::factory()->running()->for($session, 'session')->create();
    $session->forceFill(['active_run_token' => $turn->run_token])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.cancel', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('status', 'cancelling')
        ->assertJsonPath('turn_id', $turn->id);

    expect($session->refresh()->status)->toBe(AssistantSessionStatus::Cancelling)
        ->and($turn->refresh()->status)->toBe(AssistantTurnStatus::Cancelling);
});

test('request-scoped cancel ignores a different active turn', function (): void {
    $session = AssistantSession::factory()->running()->for($this->post, 'post')->create();
    $turn = AssistantTurn::factory()->running()->for($session, 'session')->create();
    $session->forceFill(['active_run_token' => $turn->run_token])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.cancel', $this->post), [
            'request_id' => (string) Str::uuid(),
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'idle')
        ->assertJsonPath('turn_id', null);

    expect($session->refresh()->status)->toBe(AssistantSessionStatus::Running)
        ->and($turn->refresh()->status)->toBe(AssistantTurnStatus::Running)
        ->and($turn->cancel_requested_at)->toBeNull();
});

test('request-scoped cancel stops its matching active turn', function (): void {
    $session = AssistantSession::factory()->running()->for($this->post, 'post')->create();
    $turn = AssistantTurn::factory()->running()->for($session, 'session')->create();
    $session->forceFill(['active_run_token' => $turn->run_token])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.cancel', $this->post), [
            'request_id' => $turn->request_id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'cancelling')
        ->assertJsonPath('turn_id', $turn->id);

    expect($session->refresh()->status)->toBe(AssistantSessionStatus::Cancelling)
        ->and($turn->refresh()->status)->toBe(AssistantTurnStatus::Cancelling);
});

test('cancel is harmless when nothing is running', function (): void {
    AssistantSession::factory()->for($this->post, 'post')->create();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.cancel', $this->post))
        ->assertSuccessful();
});

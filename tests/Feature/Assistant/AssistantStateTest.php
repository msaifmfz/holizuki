<?php

use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;

beforeEach(function (): void {
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
        ->assertJsonPath('session.status', 'idle')
        ->assertJsonPath('changes', [])
        ->assertJsonPath('turns', []);
});

test('a freshly running session reports running', function (): void {
    AssistantSession::factory()->running()->for($this->post, 'post')->create();

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.state', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('session.status', 'running');
});

test('a running session whose turn outlived the timeout reports idle', function (): void {
    AssistantSession::factory()->for($this->post, 'post')->create([
        'status' => AssistantSessionStatus::Running,
        'turn_started_at' => now()->subSeconds((int) config('assistant.turn_timeout') + 60),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('posts.assistant.state', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('session.status', 'idle');
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

test('cancel recovers a stuck running session', function (): void {
    $session = AssistantSession::factory()->running()->for($this->post, 'post')->create();
    $turn = AssistantTurn::factory()->running()->for($session, 'session')->create();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.cancel', $this->post))
        ->assertSuccessful()
        ->assertJsonPath('status', 'idle');

    expect($session->refresh()->status)->toBe(AssistantSessionStatus::Idle)
        ->and($turn->refresh()->status)->toBe(AssistantTurnStatus::Cancelled);
});

test('cancel is harmless when nothing is running', function (): void {
    AssistantSession::factory()->for($this->post, 'post')->create();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.cancel', $this->post))
        ->assertSuccessful();
});

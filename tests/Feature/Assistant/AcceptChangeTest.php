<?php

use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('assistant.workspaces', sys_get_temp_dir().'/holizuki-ws-'.uniqid());

    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create([
        'title' => 'Original title',
        'excerpt' => 'Original excerpt.',
        'body' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Intro']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Old paragraph.']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Closing thoughts.']]],
            ],
        ],
    ]);
    $this->turn = AssistantTurn::factory()
        ->for(AssistantSession::factory()->for($this->post, 'post'), 'session')
        ->create();
});

function makeChange(Post $post, AssistantTurn $turn, AssistantChangeType $type, array $payload): AssistantChange
{
    return AssistantChange::factory()->for($post, 'post')->for($turn, 'turn')->create([
        'type' => $type,
        'payload' => $payload,
        'base_lock_version' => $post->lock_version,
    ]);
}

test('guests cannot decide changes', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Excerpt, ['old' => 'Original excerpt.', 'new' => 'Better.']);

    $this->post(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertRedirect(route('login'));
});

test('accepting a scalar change saves it through the post pipeline', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Excerpt, [
        'old' => 'Original excerpt.',
        'new' => 'A crisp new excerpt.',
    ]);
    $previousVersion = $this->post->lock_version;

    $response = $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]));

    $response->assertSuccessful()
        ->assertJsonPath('change.status', 'accepted')
        ->assertJsonPath('post.excerpt', 'A crisp new excerpt.')
        ->assertJsonPath('post.lock_version', $previousVersion + 1);

    expect($this->post->refresh()->excerpt)->toBe('A crisp new excerpt.')
        ->and($change->refresh()->status)->toBe(AssistantChangeStatus::Accepted)
        ->and($change->decided_at)->not->toBeNull();
});

test('accepting a tags change syncs tag names', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Tags, [
        'old' => [],
        'new' => ['laravel', 'queues'],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertSuccessful()
        ->assertJsonPath('post.tags', ['laravel', 'queues']);

    expect($this->post->tags()->pluck('name')->all())->toEqualCanonicalizing(['laravel', 'queues']);
});

test('a scalar change is stale when the author already edited the field', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Excerpt, [
        'old' => 'Some other base value.',
        'new' => 'AI suggestion.',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertUnprocessable()
        ->assertJsonPath('change.status', 'stale');

    expect($this->post->refresh()->excerpt)->toBe('Original excerpt.');
});

test('accepting a body hunk splices it into the current body', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Body, [
        'old_blocks' => 'Old paragraph.',
        'new_blocks' => 'A much sharper paragraph.',
        'anchor_before' => '## Intro',
        'anchor_after' => 'Closing thoughts.',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertSuccessful();

    $body = $this->post->refresh()->body?->plainText();

    expect($body)->toContain('A much sharper paragraph.')
        ->not->toContain('Old paragraph.')
        ->toContain('Closing thoughts.');
});

test('a body hunk whose region vanished is stale', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Body, [
        'old_blocks' => 'This text no longer exists.',
        'new_blocks' => 'Replacement.',
        'anchor_before' => null,
        'anchor_after' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertUnprocessable()
        ->assertJsonPath('change.status', 'stale');
});

test('an insertion hunk lands after its anchor', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Body, [
        'old_blocks' => '',
        'new_blocks' => 'Freshly inserted paragraph.',
        'anchor_before' => 'Old paragraph.',
        'anchor_after' => 'Closing thoughts.',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertSuccessful();

    expect($this->post->refresh()->body?->plainText())
        ->toContain('Old paragraph. Freshly inserted paragraph. Closing thoughts.');
});

test('accepting a body change records a revisionable save', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Body, [
        'old_blocks' => 'Old paragraph.',
        'new_blocks' => 'Improved paragraph.',
        'anchor_before' => '## Intro',
        'anchor_after' => null,
    ]);
    $previousVersion = $this->post->lock_version;

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertSuccessful();

    expect($this->post->refresh()->lock_version)->toBe($previousVersion + 1)
        ->and($this->post->updated_by_id)->toBe($this->user->id);
});

test('rejecting a change leaves the post untouched', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Excerpt, [
        'old' => 'Original excerpt.',
        'new' => 'Discarded suggestion.',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.reject', [$this->post, $change]))
        ->assertSuccessful()
        ->assertJsonPath('change.status', 'rejected');

    expect($this->post->refresh()->excerpt)->toBe('Original excerpt.');
});

test('an already decided change cannot be decided again', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Excerpt, [
        'old' => 'Original excerpt.',
        'new' => 'Twice?',
    ]);
    $change->forceFill(['status' => AssistantChangeStatus::Rejected])->save();

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertConflict();
});

test('a change belonging to another post is not found', function (): void {
    $otherPost = Post::factory()->for($this->user, 'author')->create();
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Excerpt, [
        'old' => 'Original excerpt.',
        'new' => 'Suggestion.',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$otherPost, $change]))
        ->assertNotFound();
});

test('a duplicated hunk resolves through its anchor', function (): void {
    $this->post->forceFill([
        'body' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Repeated.']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Divider.']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Repeated.']]],
            ],
        ],
    ])->save();

    $change = makeChange($this->post, $this->turn, AssistantChangeType::Body, [
        'old_blocks' => 'Repeated.',
        'new_blocks' => 'Replaced once.',
        'anchor_before' => 'Divider.',
        'anchor_after' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertSuccessful();

    expect($this->post->refresh()->body?->plainText())
        ->toBe('Repeated. Divider. Replaced once.');
});

test('an ambiguous duplicated hunk without a usable anchor is stale', function (): void {
    $this->post->forceFill([
        'body' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Repeated.']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Repeated.']]],
            ],
        ],
    ])->save();

    $change = makeChange($this->post, $this->turn, AssistantChangeType::Body, [
        'old_blocks' => 'Repeated.',
        'new_blocks' => 'Which one?',
        'anchor_before' => null,
        'anchor_after' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertUnprocessable();
});

test('an insertion into an empty body appends the new content', function (): void {
    $this->post->forceFill(['body' => null])->save();

    $change = makeChange($this->post, $this->turn, AssistantChangeType::Body, [
        'old_blocks' => '',
        'new_blocks' => 'The very first paragraph.',
        'anchor_before' => null,
        'anchor_after' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertSuccessful();

    expect($this->post->refresh()->body?->plainText())->toBe('The very first paragraph.');
});

test('an empty insertion hunk is stale', function (): void {
    $change = makeChange($this->post, $this->turn, AssistantChangeType::Body, [
        'old_blocks' => '',
        'new_blocks' => '',
        'anchor_before' => null,
        'anchor_after' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.assistant.changes.accept', [$this->post, $change]))
        ->assertUnprocessable();
});

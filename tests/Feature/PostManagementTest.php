<?php

use App\Enums\PostRevisionEvent;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->body = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'A useful article body.']],
        ]],
    ];
});

test('guests cannot access post management', function (): void {
    $this->get(route('posts.index'))->assertRedirect(route('login'));
});

test('administrators can list and create an immediate draft', function (): void {
    Post::factory()->for($this->user, 'author')->create(['title' => 'Existing post']);

    $this->actingAs($this->user)
        ->get(route('posts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('posts/index')
            ->has('posts.data', 1)
            ->where('posts.data.0.title', 'Existing post'));

    $response = $this->actingAs($this->user)->post(route('posts.store'));
    $post = Post::latest('id')->firstOrFail();

    $response->assertRedirect(route('posts.edit', $post));
    expect($post->title)->toBe('Untitled post')
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->author_id)->toBe($this->user->id)
        ->and($post->slug)->toStartWith('untitled-post-');
});

test('administrators can filter posts by editorial status', function (): void {
    $draft = Post::factory()->for($this->user, 'author')->create(['title' => 'Draft post']);
    $scheduled = Post::factory()->scheduled()->for($this->user, 'author')->create(['title' => 'Scheduled post']);
    $published = Post::factory()->published()->for($this->user, 'author')->create(['title' => 'Published post']);

    foreach ([
        'draft' => $draft,
        'scheduled' => $scheduled,
        'published' => $published,
    ] as $status => $post) {
        $this->actingAs($this->user)
            ->get(route('posts.index', ['status' => $status]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('posts/index')
                ->where('filters.status', $status)
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $post->id));
    }
});

test('administrators can open a scheduled post in the editor', function (): void {
    Storage::fake('public');
    $post = Post::factory()->scheduled()->for($this->user, 'author')->create([
        'updated_by_id' => $this->user->id,
        'title' => null,
        'featured_image_path' => 'posts/editor.webp',
        'slug_is_manual' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('posts.edit', $post))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('posts/edit')
            ->has('post', fn (AssertableInertia $postData): AssertableInertia => $postData
                ->where('id', $post->id)
                ->where('title', 'Untitled post')
                ->where('status', 'scheduled')
                ->where('author', $this->user->name)
                ->where('last_editor', $this->user->name)
                ->where('featured_image_url', Storage::disk('public')->url('posts/editor.webp'))
                ->where('slug_is_manual', true)
                ->where('lock_version', 0)
                ->etc()));
});

test('autosave updates a draft and manual save creates a revision', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 0]);
    $payload = [
        'title' => 'A Better Title',
        'slug' => $post->slug,
        'slug_is_manual' => false,
        'excerpt' => '',
        'body' => $this->body,
        'featured_image_alt' => '',
        'lock_version' => 0,
    ];

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), $payload)
        ->assertOk()
        ->assertJsonPath('slug', 'a-better-title')
        ->assertJsonPath('lock_version', 1);

    expect($post->revisions()->count())->toBe(0);

    $this->actingAs($this->user)
        ->patchJson(route('posts.update', $post), [...$payload, 'lock_version' => 1, 'excerpt' => 'Manual excerpt'])
        ->assertOk()
        ->assertJsonPath('lock_version', 2);

    expect($post->revisions()->sole()->event)->toBe(PostRevisionEvent::Saved);
});

test('stale autosaves return a conflict and confirmed overwrite preserves the server version', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 2, 'title' => 'Server title']);
    $payload = [
        'title' => 'Local title',
        'slug' => 'local-title',
        'slug_is_manual' => true,
        'excerpt' => 'Excerpt',
        'body' => $this->body,
        'featured_image_alt' => 'Alt',
        'lock_version' => 1,
    ];

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), $payload)
        ->assertConflict()
        ->assertJsonPath('conflict.lock_version', 2);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), [...$payload, 'force' => true])
        ->assertOk()
        ->assertJsonPath('lock_version', 3);

    expect($post->refresh()->title)->toBe('Local title')
        ->and($post->revisions()->sole()->title)->toBe('Server title')
        ->and($post->revisions()->sole()->event)->toBe(PostRevisionEvent::ConflictOverwrite);
});

test('posts move to trash, restore as drafts, and can be permanently deleted', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create();

    $this->actingAs($this->user)->delete(route('posts.destroy', $post))->assertRedirect(route('posts.index'));
    expect($post->refresh()->trashed())->toBeTrue()
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->scheduled_at)->toBeNull();

    $this->actingAs($this->user)->post(route('posts.restore', $post))->assertRedirect(route('posts.edit', $post));
    expect($post->refresh()->trashed())->toBeFalse()->and($post->status)->toBe(PostStatus::Draft);

    $this->actingAs($this->user)->delete(route('posts.destroy', $post));
    $this->actingAs($this->user)->delete(route('posts.force-destroy', $post))->assertRedirect(route('posts.trash.index'));
    $this->assertModelMissing($post);
});

test('administrators can search the trash', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'title' => null,
        'slug' => 'discarded-draft',
    ]);
    $post->delete();

    $this->actingAs($this->user)
        ->get(route('posts.trash.index', ['search' => 'discarded']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('posts/index')
            ->where('trash', true)
            ->where('filters.search', 'discarded')
            ->where('counts.trash', 1)
            ->has('posts.data', 1, fn (AssertableInertia $postData): AssertableInertia => $postData
                ->where('id', $post->id)
                ->where('title', 'Untitled post')
                ->where('status', 'trashed')
                ->where('author', $this->user->name)
                ->where('last_editor', null)
                ->where('scheduled_at', null)
                ->where('published_at', null)
                ->etc()));
});

test('a malformed text node without text content is rejected', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 0]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), [
            'title' => 'Title',
            'slug' => 'title',
            'slug_is_manual' => true,
            'excerpt' => '',
            'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text']]]]],
            'featured_image_alt' => '',
            'lock_version' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

test('a manual slug that reduces to empty falls back to a generated slug', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 0]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), [
            'title' => 'Meaningful Title',
            'slug' => '///',
            'slug_is_manual' => true,
            'excerpt' => '',
            'body' => $this->body,
            'featured_image_alt' => '',
            'lock_version' => 0,
        ])
        ->assertOk();

    expect($post->refresh()->slug)->not->toBe('');
});

test('search does not treat wildcard characters as wildcards', function (): void {
    Post::factory()->for($this->user, 'author')->create(['title' => 'Alpha']);
    Post::factory()->for($this->user, 'author')->create(['title' => 'Beta']);

    $this->actingAs($this->user)
        ->get(route('posts.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('posts.data', 0));
});

test('a manual slug that normalizes into an existing slug fails validation', function (): void {
    Post::factory()->for($this->user, 'author')->create(['slug' => 'my-post']);
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 0]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), [
            'title' => 'Another Post',
            'slug' => 'My-Post',
            'slug_is_manual' => true,
            'excerpt' => '',
            'body' => $this->body,
            'featured_image_alt' => '',
            'lock_version' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('fields omitted from an autosave payload are preserved', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'lock_version' => 0,
        'title' => 'Kept title',
        'excerpt' => 'Kept excerpt',
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), [
            'slug' => $post->slug,
            'slug_is_manual' => true,
            'lock_version' => 0,
        ])
        ->assertOk();

    expect($post->refresh()->title)->toBe('Kept title')
        ->and($post->excerpt)->toBe('Kept excerpt')
        ->and($post->body)->not->toBeNull();
});

test('array query parameters on the post list are ignored', function (): void {
    $this->actingAs($this->user)
        ->get(route('posts.index', ['status' => ['x'], 'search' => ['y']]))
        ->assertOk();

    $this->actingAs($this->user)
        ->get(route('posts.trash.index', ['search' => ['y']]))
        ->assertOk();
});

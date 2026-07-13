<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->body = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Revision body']]]],
    ];
});

test('administrators can browse revision history and preview a revision', function (): void {
    Storage::fake('public');
    $post = Post::factory()->for($this->user, 'author')->create(['title' => null, 'lock_version' => 2]);
    $systemRevision = PostRevision::factory()->for($post)->create([
        'editor_id' => null,
        'revision_number' => 1,
        'title' => 'System revision',
    ]);
    $editorRevision = PostRevision::factory()->for($post)->for($this->user, 'editor')->create([
        'revision_number' => 2,
        'title' => null,
        'body' => $this->body,
        'featured_image_path' => 'posts/revisions/preview.webp',
        'featured_image_alt' => 'Revision preview',
    ]);

    $this->actingAs($this->user)
        ->get(route('posts.revisions.index', $post))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('posts/revisions/index')
            ->where('post.id', $post->id)
            ->where('post.title', 'Untitled post')
            ->where('post.lock_version', 2)
            ->has('revisions.data', 2)
            ->where('revisions.data.0.id', $editorRevision->id)
            ->where('revisions.data.0.editor', $this->user->name)
            ->where('revisions.data.1.id', $systemRevision->id)
            ->where('revisions.data.1.editor', 'System'));

    $this->actingAs($this->user)
        ->get(route('posts.revisions.show', [$post, $editorRevision]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('posts/preview')
            ->where('post.id', $post->id)
            ->where('post.title', 'Untitled post')
            ->where('post.body', $this->body)
            ->where('post.featured_image_url', Storage::disk('public')->url('posts/revisions/preview.webp'))
            ->where('post.featured_image_alt', 'Revision preview')
            ->where('post.published_at', null)
            ->where('revision.id', $editorRevision->id)
            ->where('revision.editor', $this->user->name));
});

test('revision retention is configurable', function (): void {
    config()->set('blog.revision_limit', 3);
    $post = Post::factory()->for($this->user, 'author')->create(['body' => $this->body]);

    foreach (range(1, 5) as $version) {
        $this->actingAs($this->user)
            ->patchJson(route('posts.update', $post), [
                'title' => 'Revision '.$version,
                'slug' => 'revision-'.$version,
                'slug_is_manual' => true,
                'excerpt' => 'Excerpt '.$version,
                'body' => $this->body,
                'featured_image_alt' => $post->featured_image_alt,
                'lock_version' => $version - 1,
            ])->assertOk();
    }

    expect($post->revisions()->pluck('revision_number')->all())->toBe([3, 4, 5]);
});

test('restoring a revision replaces editorial content without changing publication state', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'title' => 'Original title',
        'slug' => 'original-title',
        'body' => $this->body,
        'lock_version' => 0,
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.update', $post), [
            'title' => 'Saved revision',
            'slug' => 'saved-revision',
            'slug_is_manual' => true,
            'excerpt' => 'Saved excerpt',
            'body' => $this->body,
            'featured_image_alt' => $post->featured_image_alt,
            'lock_version' => 0,
        ])->assertOk();

    $revision = $post->revisions()->sole();

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), [
            'title' => 'Current content',
            'slug' => 'current-content',
            'slug_is_manual' => true,
            'excerpt' => 'Current excerpt',
            'body' => $this->body,
            'featured_image_alt' => $post->featured_image_alt,
            'lock_version' => 1,
        ])->assertOk();

    $this->actingAs($this->user)
        ->postJson(route('posts.revisions.restore', [$post, $revision]), ['lock_version' => 2])
        ->assertOk()
        ->assertJsonPath('lock_version', 3);

    expect($post->refresh()->title)->toBe('Saved revision')
        ->and($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at)->not->toBeNull();
});

test('a revision from another post cannot be restored through scoped bindings', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $otherPost = Post::factory()->for($this->user, 'author')->create();
    $otherRevision = $otherPost->revisions()->create([
        'revision_number' => 1,
        'event' => 'saved',
        'slug' => $otherPost->slug,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.revisions.restore', [$post, $otherRevision]), ['lock_version' => 0])
        ->assertNotFound();
});

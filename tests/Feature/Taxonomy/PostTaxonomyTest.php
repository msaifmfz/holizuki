<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
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

/** @return array<string, mixed> */
function draftPayload(Post $post, array $overrides = []): array
{
    return [
        'title' => $post->title,
        'slug' => $post->slug,
        'slug_is_manual' => false,
        'excerpt' => $post->excerpt,
        'body' => $post->body,
        'featured_image_alt' => $post->featured_image_alt,
        'lock_version' => $post->lock_version,
        ...$overrides,
    ];
}

test('the editor receives categories, authors, and tag suggestions', function (): void {
    $category = Category::factory()->create(['name' => 'Engineering']);
    Tag::factory()->create(['name' => 'Laravel']);
    $post = Post::factory()->for($this->user, 'author')->create(['category_id' => $category->id]);
    $post->tags()->attach(Tag::factory()->create(['name' => 'Testing']));

    $this->actingAs($this->user)
        ->get(route('posts.edit', $post))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('posts/edit')
            ->where('post.category_id', $category->id)
            ->where('post.author_id', $this->user->id)
            ->where('post.tags', ['Testing'])
            ->has('categories', 1)
            ->has('authors', 1)
            ->where('tagSuggestions', ['Laravel', 'Testing']));
});

test('saving a draft persists category and author assignment', function (): void {
    $category = Category::factory()->create();
    $author = User::factory()->create();
    $post = Post::factory()->for($this->user, 'author')->create(['category_id' => null, 'lock_version' => 0]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), draftPayload($post, [
            'category_id' => $category->id,
            'author_id' => $author->id,
        ]))
        ->assertOk();

    $post->refresh();
    expect($post->category_id)->toBe($category->id)
        ->and($post->author_id)->toBe($author->id);
});

test('saving tags creates missing tags, reuses existing ones, and deduplicates', function (): void {
    $existing = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 0]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), draftPayload($post, [
            'tags' => ['Queues', ' queues ', 'laravel', 'Fresh Idea'],
        ]))
        ->assertOk();

    $tagNames = $post->refresh()->tags->pluck('name')->sort()->values()->all();
    expect($tagNames)->toBe(['Fresh Idea', 'Laravel', 'Queues'])
        ->and(Tag::count())->toBe(3)
        ->and($post->tags->firstWhere('name', 'Laravel')->id)->toBe($existing->id)
        ->and(Tag::where('name', 'Fresh Idea')->firstOrFail()->slug)->toBe('fresh-idea');
});

test('syncing an empty tag list detaches all tags', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 0]);
    $post->tags()->attach(Tag::factory()->count(2)->create());

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), draftPayload($post, ['tags' => []]))
        ->assertOk();

    expect($post->refresh()->tags)->toHaveCount(0)->and(Tag::count())->toBe(2);
});

test('a post cannot be published without a category', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'category_id' => null,
        'body' => $this->body,
        'lock_version' => 0,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');

    $this->actingAs($this->user)
        ->postJson(route('posts.schedule', $post), [
            'lock_version' => 0,
            'scheduled_at' => now()->addDay()->toISOString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
});

test('a categorized post publishes successfully', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'body' => $this->body,
        'lock_version' => 0,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => 0])
        ->assertOk()
        ->assertJsonPath('status', 'published');
});

test('tag input is limited to ten tags', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 0]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), draftPayload($post, [
            'tags' => array_map(fn (int $index): string => "Tag {$index}", range(1, 11)),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tags');
});

test('restoring a revision leaves category and tags untouched', function (): void {
    $originalCategory = Category::factory()->create();
    $post = Post::factory()->for($this->user, 'author')->create([
        'category_id' => $originalCategory->id,
        'title' => 'Original title',
        'lock_version' => 0,
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.update', $post), draftPayload($post))
        ->assertOk();
    $revision = $post->revisions()->firstOrFail();

    $newCategory = Category::factory()->create();
    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), draftPayload($post->refresh(), [
            'title' => 'Rewritten title',
            'category_id' => $newCategory->id,
            'tags' => ['Kept Tag'],
        ]))
        ->assertOk();

    $this->actingAs($this->user)
        ->postJson(route('posts.revisions.restore', [$post, $revision]), ['lock_version' => 2])
        ->assertOk();

    $post->refresh();
    expect($post->title)->toBe('Original title')
        ->and($post->category_id)->toBe($newCategory->id)
        ->and($post->tags->pluck('name')->all())->toBe(['Kept Tag']);
});

<?php

use App\Actions\Posts\PublishPost;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->body = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Publishable body']]]],
    ];
});

test('administrators can preview a post before publishing', function (): void {
    Storage::fake('public');
    $post = Post::factory()->for($this->user, 'author')->create([
        'title' => null,
        'body' => $this->body,
        'featured_image_path' => 'posts/preview.webp',
        'featured_image_alt' => 'Preview image',
    ]);

    $this->actingAs($this->user)
        ->get(route('posts.preview', $post))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('posts/preview')
            ->where('revision', null)
            ->has('post', fn (AssertableInertia $postData): AssertableInertia => $postData
                ->where('id', $post->id)
                ->where('title', 'Untitled post')
                ->where('excerpt', $post->excerpt)
                ->where('body', $this->body)
                ->where('featured_image_url', Storage::disk('public')->url('posts/preview.webp'))
                ->where('featured_image_alt', 'Preview image')
                ->where('published_at', null)
                ->etc()));
});

test('publishing requires every editorial field and featured image', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'excerpt' => null,
        'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        'featured_image_path' => null,
        'featured_image_alt' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['excerpt', 'body', 'featured_image_path', 'featured_image_alt']);
});

test('unsupported rich text is rejected safely', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'body' => ['type' => 'doc', 'content' => ['not-a-node']],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

test('a complete draft can publish and unpublish without changing its stable slug', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['body' => $this->body, 'lock_version' => 0]);

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => 0])
        ->assertOk()
        ->assertJsonPath('status', 'published')
        ->assertJsonPath('lock_version', 1);

    $originalSlug = $post->refresh()->slug;
    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at)->not->toBeNull()
        ->and($post->slug_locked_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), [
            'title' => 'Changed after publication',
            'slug' => $originalSlug,
            'slug_is_manual' => false,
            'excerpt' => $post->excerpt,
            'body' => $this->body,
            'featured_image_alt' => $post->featured_image_alt,
            'lock_version' => 1,
        ])->assertOk();

    expect($post->refresh()->slug)->toBe($originalSlug);

    $this->actingAs($this->user)
        ->postJson(route('posts.unpublish', $post), ['lock_version' => 2])
        ->assertOk()
        ->assertJsonPath('status', 'draft');

    expect($post->refresh()->status)->toBe(PostStatus::Draft)
        ->and($post->published_at)->not->toBeNull();
});

test('republishing preserves the original publication chronology', function (): void {
    Date::setTestNow('2026-07-18 12:00:00');
    $originalPublishedAt = now()->subMonths(6)->startOfSecond();
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'body' => $this->body,
        'published_at' => $originalPublishedAt,
        'lock_version' => 0,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.unpublish', $post), ['lock_version' => 0])
        ->assertOk()
        ->assertJsonPath('lock_version', 1);

    Date::setTestNow('2026-07-19 12:00:00');

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => 1])
        ->assertOk()
        ->assertJsonPath('lock_version', 2);

    expect($post->refresh()->published_at?->equalTo($originalPublishedAt))->toBeTrue();
});

test('future publishing is scheduled in UTC and the command publishes only due posts', function (): void {
    Date::setTestNow('2026-07-12 12:00:00');
    $due = Post::factory()->scheduled()->for($this->user, 'author')->create([
        'body' => $this->body,
        'scheduled_at' => now()->subMinute(),
    ]);
    $future = Post::factory()->scheduled()->for($this->user, 'author')->create([
        'body' => $this->body,
        'scheduled_at' => now()->addHour(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($due->refresh()->status)->toBe(PostStatus::Published)
        ->and($due->published_at?->equalTo(now()->subMinute()))->toBeTrue()
        ->and($future->refresh()->status)->toBe(PostStatus::Draft)
        ->and($future->scheduled_at)->not->toBeNull();
});

test('administrators can schedule a complete draft for a future instant', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['body' => $this->body]);
    $scheduledAt = now()->addDay()->startOfSecond()->toISOString();

    $this->actingAs($this->user)
        ->postJson(route('posts.schedule', $post), ['lock_version' => 0, 'scheduled_at' => $scheduledAt])
        ->assertOk()
        ->assertJsonPath('status', 'scheduled');

    expect($post->refresh()->status)->toBe(PostStatus::Draft)
        ->and($post->scheduled_at?->toISOString())->toBe($scheduledAt);
});

test('scheduled publishing skips a post that is no longer scheduled', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'body' => $this->body,
        'status' => PostStatus::Draft,
        'scheduled_at' => null,
    ]);

    resolve(PublishPost::class)->handle($post, editor: null, publishedAt: now());

    expect($post->refresh()->status)->toBe(PostStatus::Draft)
        ->and($post->published_at)->toBeNull()
        ->and($post->revisions()->count())->toBe(0);
});

<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostRedirect;

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
function seoAutosavePayload(Post $post, array $overrides = []): array
{
    $draft = $post->draft()->firstOrFail();

    return [
        'title' => $draft->title,
        'slug' => $draft->slug,
        'slug_is_manual' => $draft->slug_is_manual,
        'excerpt' => $draft->excerpt,
        'body' => $draft->body,
        'featured_image_alt' => $draft->featured_image_alt,
        'featured_image_caption' => $draft->featured_image_caption,
        'seo_title' => $draft->seo_title,
        'meta_description' => $draft->meta_description,
        'canonical_url' => $draft->canonical_url,
        'og_title' => $draft->og_title,
        'og_description' => $draft->og_description,
        'noindex' => $draft->noindex,
        'lock_version' => $post->lock_version,
        ...$overrides,
    ];
}

test('autosave persists per-post seo fields', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['body' => $this->body]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post, [
            'seo_title' => 'Hand-tuned SEO title',
            'meta_description' => 'A crisp meta description for search results.',
            'canonical_url' => 'https://example.com/original-guide',
            'og_title' => 'Social title',
            'og_description' => 'Social description',
            'noindex' => true,
        ]))
        ->assertOk();

    $draft = $post->refresh()->draft;
    expect($draft?->seo_title)->toBe('Hand-tuned SEO title')
        ->and($draft?->meta_description)->toBe('A crisp meta description for search results.')
        ->and($draft?->canonical_url)->toBe('https://example.com/original-guide')
        ->and($draft?->og_title)->toBe('Social title')
        ->and($draft?->og_description)->toBe('Social description')
        ->and($draft?->noindex)->toBeTrue()
        ->and($post->seo_title)->toBeNull();
});

test('an invalid canonical url is rejected', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['body' => $this->body]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post, ['canonical_url' => 'not-a-url']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('canonical_url');
});

test('resaving identical content does not bump content_updated_at', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create(['body' => $this->body]);
    $originalContentUpdatedAt = $post->content_updated_at;

    $this->travelTo(now()->addHour());

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post))
        ->assertOk();

    expect($post->refresh()->content_updated_at?->toIso8601String())
        ->toBe($originalContentUpdatedAt?->toIso8601String());
});

test('draft changes bump content_updated_at only when they are published', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create(['body' => $this->body]);
    $originalContentUpdatedAt = $post->content_updated_at;

    $this->travelTo(now()->addHour());

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post, [
            'seo_title' => 'Search-only tweak',
        ]))
        ->assertOk();

    expect($post->refresh()->content_updated_at?->toIso8601String())
        ->toBe($originalContentUpdatedAt?->toIso8601String());

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post, [
            'body' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'A meaningfully rewritten body.']],
                ]],
            ],
        ]))
        ->assertOk();

    expect($post->refresh()->content_updated_at?->toIso8601String())
        ->toBe($originalContentUpdatedAt?->toIso8601String());

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => $post->lock_version])
        ->assertOk();

    expect($post->refresh()->content_updated_at?->toIso8601String())
        ->toBe(now()->toIso8601String());
});

test('publishing sets content_updated_at', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['body' => $this->body]);
    expect($post->content_updated_at)->toBeNull();

    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => $post->lock_version])
        ->assertOk();

    expect($post->refresh()->content_updated_at)->not->toBeNull();
});

test('revisions snapshot seo fields and restoring brings them back', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'body' => $this->body,
        'seo_title' => 'Original SEO title',
        'meta_description' => 'Original meta description',
        'noindex' => true,
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.update', $post), seoAutosavePayload($post))
        ->assertOk();

    $revision = $post->revisions()->sole();
    expect($revision->seo_title)->toBe('Original SEO title')
        ->and($revision->meta_description)->toBe('Original meta description')
        ->and($revision->noindex)->toBeTrue();

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post->refresh(), [
            'seo_title' => 'Replaced SEO title',
            'meta_description' => 'Replaced meta description',
            'noindex' => false,
        ]))
        ->assertOk();

    $this->actingAs($this->user)
        ->postJson(route('posts.revisions.restore', [$post, $revision]), ['lock_version' => $post->refresh()->lock_version])
        ->assertOk();

    $draft = $post->refresh()->draft;
    expect($draft?->seo_title)->toBe('Original SEO title')
        ->and($draft?->meta_description)->toBe('Original meta description')
        ->and($draft?->noindex)->toBeTrue();
});

test('changing the slug of a published post records a permanent redirect', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'body' => $this->body,
        'slug' => 'first-slug',
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post, [
            'slug' => 'second-slug',
            'slug_is_manual' => true,
        ]))
        ->assertOk();
    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => $post->refresh()->lock_version])
        ->assertOk();

    $redirect = PostRedirect::sole();
    expect($redirect->old_slug)->toBe('first-slug')
        ->and($redirect->post_id)->toBe($post->id);
});

test('slug changes on never-published drafts do not record redirects', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'body' => $this->body,
        'slug' => 'draft-slug',
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post, [
            'slug' => 'renamed-draft-slug',
            'slug_is_manual' => true,
        ]))
        ->assertOk();

    expect(PostRedirect::count())->toBe(0);
});

test('repeated slug changes keep every old slug pointing at the post', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'body' => $this->body,
        'slug' => 'slug-a',
    ]);

    foreach (['slug-b', 'slug-c'] as $slug) {
        $this->actingAs($this->user)
            ->patchJson(route('posts.autosave', $post->refresh()), seoAutosavePayload($post, [
                'slug' => $slug,
                'slug_is_manual' => true,
            ]))
            ->assertOk();
        $this->actingAs($this->user)
            ->postJson(route('posts.publish', $post), ['lock_version' => $post->refresh()->lock_version])
            ->assertOk();
    }

    expect(PostRedirect::pluck('post_id', 'old_slug')->all())
        ->toBe(['slug-a' => $post->id, 'slug-b' => $post->id]);
});

test('reclaiming a redirected slug removes the shadowing redirect', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'body' => $this->body,
        'slug' => 'slug-a',
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post), seoAutosavePayload($post, [
            'slug' => 'slug-b',
            'slug_is_manual' => true,
        ]))
        ->assertOk();
    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => $post->refresh()->lock_version])
        ->assertOk();

    $this->actingAs($this->user)
        ->patchJson(route('posts.autosave', $post->refresh()), seoAutosavePayload($post, [
            'slug' => 'slug-a',
            'slug_is_manual' => true,
        ]))
        ->assertOk();
    $this->actingAs($this->user)
        ->postJson(route('posts.publish', $post), ['lock_version' => $post->refresh()->lock_version])
        ->assertOk();

    expect(PostRedirect::pluck('post_id', 'old_slug')->all())
        ->toBe(['slug-b' => $post->id]);
});

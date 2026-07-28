<?php

use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\DeletePostMedia;
use App\Domain\Publishing\Enums\PostMediaOrigin;
use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use App\Domain\Publishing\Models\PostRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->post = Post::factory()->for($this->user, 'author')->create();
});

test('guests cannot access a post media library', function (): void {
    $this->get(route('posts.media.index', $this->post))
        ->assertRedirect(route('login'));

    $this->post(route('posts.media.store', $this->post), [
        'images' => [UploadedFile::fake()->image('moon.webp')],
    ])->assertRedirect(route('login'));
});

test('administrators can batch upload images with opaque filenames', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson(route('posts.media.store', $this->post), [
            'images' => [
                UploadedFile::fake()->image('moonrise.webp', 1200, 675),
                UploadedFile::fake()->image('telescope.png', 800, 600),
            ],
        ])
        ->assertCreated()
        ->assertJsonCount(2, 'media')
        ->assertJsonPath('media.0.display_name', 'moonrise')
        ->assertJsonPath('media.0.origin', 'library_upload')
        ->assertJsonPath('media.0.status', 'ready')
        ->assertJsonPath('media.0.story_priority', 'optional')
        ->assertJsonPath('media.0.width', 1200)
        ->assertJsonPath('media.0.height', 675);

    $media = PostMedia::query()->whereBelongsTo($this->post)->oldest()->firstOrFail();

    expect($media->origin)->toBe(PostMediaOrigin::LibraryUpload)
        ->and($media->story_priority)->toBe(PostMediaStoryPriority::Optional)
        ->and($media->path)->not->toContain('moonrise')
        ->and($media->display_name)->toBe('moonrise');
    Storage::disk('public')->assertExists($media->path);

    $this->actingAs($this->user)
        ->getJson(route('posts.media.index', $this->post))
        ->assertOk()
        ->assertJsonCount(2, 'media')
        ->assertJsonFragment(['id' => $media->id, 'display_name' => 'moonrise']);

    expect($response->json('media.0'))->not->toHaveKey('path');
});

test('media uploads enforce supported image types dimensions and batch limits', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('posts.media.store', $this->post), [
            'images' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images.0');

    $this->actingAs($this->user)
        ->postJson(route('posts.media.store', $this->post), [
            'images' => [UploadedFile::fake()->image('oversize.png')->size(5121)],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images.0');

    $this->actingAs($this->user)
        ->postJson(route('posts.media.store', $this->post), [
            'images' => [UploadedFile::fake()->image('too-wide.png', 10_001, 1)],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images.0');

    $this->assertDatabaseCount('post_media', 0);

    PostMedia::factory()->count(20)->for($this->post)->create();

    $this->actingAs($this->user)
        ->postJson(route('posts.media.store', $this->post), [
            'images' => [UploadedFile::fake()->image('one-too-many.webp')],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images');
});

test('administrators can update media suggestions and posts cannot cross-manage media', function (): void {
    $media = PostMedia::factory()->for($this->post)->libraryUpload()->create();

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'display_name' => 'Moon over the northern ridge',
            'alt_text' => 'A full moon rising behind a dark mountain ridge',
            'caption' => 'The July full moon.',
            'role' => 'illustration',
            'placement' => 'introduction',
            'story_priority' => 'required',
            'story_notes' => 'Explain how this view changed the direction of the trip.',
        ])
        ->assertOk()
        ->assertJsonPath('media.display_name', 'Moon over the northern ridge')
        ->assertJsonPath('media.role', 'illustration')
        ->assertJsonPath('media.placement', 'introduction')
        ->assertJsonPath('media.story_priority', 'required')
        ->assertJsonPath(
            'media.story_notes',
            'Explain how this view changed the direction of the trip.',
        );

    expect($media->refresh()->role)->toBe(PostMediaRole::Illustration)
        ->and($media->placement)->toBe(PostMediaPlacement::Introduction)
        ->and($media->story_priority)->toBe(PostMediaStoryPriority::Required)
        ->and($media->story_notes)
        ->toBe('Explain how this view changed the direction of the trip.');

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'role' => 'article-destroyer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $otherPost = Post::factory()->for($this->user, 'author')->create();
    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$otherPost, $media]), [
            'display_name' => 'Wrong post',
        ])
        ->assertNotFound();
});

test('media metadata updates synchronize only matching working draft image attributes', function (): void {
    $media = PostMedia::factory()->for($this->post)->libraryUpload()->create([
        'alt_text' => 'Stored alternative text',
        'caption' => 'Stored caption',
        'width' => 1440,
        'height' => 900,
    ]);
    $originalBody = postMediaDocument($media);
    $this->post->forceFill(['body' => $originalBody])->save();
    $this->post->draft()->update(['body' => $originalBody]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'caption' => '  A revised ridge caption.  ',
        ])
        ->assertOk()
        ->assertJsonPath('media.caption', 'A revised ridge caption.')
        // Rewriting the draft body bumps the lock version and returns the new
        // body so an open editor reconciles instead of re-sending stale content.
        ->assertJsonPath('lock_version', 1)
        ->assertJsonPath('body.content.0.attrs.caption', 'A revised ridge caption.');

    expect($this->post->draft()->firstOrFail()->body?->toArray())->toBe([
        'type' => 'doc',
        'content' => [[
            'type' => 'image',
            'attrs' => [
                'mediaId' => $media->id,
                'src' => 'media:'.$media->id,
                'alt' => 'A moonlit ridge',
                'caption' => 'A revised ridge caption.',
                'width' => 1440,
                'height' => 900,
            ],
        ]],
    ])->and($this->post->refresh()->body?->toArray())->toBe($originalBody)
        ->and($this->post->lock_version)->toBe(1);

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'alt_text' => '  Moonlight above the northern ridge.  ',
        ])
        ->assertOk()
        ->assertJsonPath('media.alt_text', 'Moonlight above the northern ridge.');

    expect($this->post->draft()->firstOrFail()->body?->toArray()['content'][0]['attrs'])->toBe([
        'mediaId' => $media->id,
        'src' => 'media:'.$media->id,
        'alt' => 'Moonlight above the northern ridge.',
        'caption' => 'A revised ridge caption.',
        'width' => 1440,
        'height' => 900,
    ])->and(PostRevision::query()->whereBelongsTo($this->post)->count())->toBe(0);

    $this->actingAs($this->user)
        ->get(route('posts.preview', $this->post))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('post.body.content.0.attrs.mediaId', $media->id)
            ->where('post.body.content.0.attrs.src', Storage::disk('public')->url($media->path))
            ->where('post.body.content.0.attrs.alt', 'Moonlight above the northern ridge.')
            ->where('post.body.content.0.attrs.caption', 'A revised ridge caption.')
            ->where('post.body.content.0.attrs.width', 1440)
            ->where('post.body.content.0.attrs.height', 900));
});

test('media metadata updates leave the lock version alone when the draft body is untouched', function (): void {
    $used = PostMedia::factory()->for($this->post)->libraryUpload()->create([
        'width' => 1440,
        'height' => 900,
    ]);
    $unused = PostMedia::factory()->for($this->post)->libraryUpload()->create();
    $this->post->draft()->update(['body' => postMediaDocument($used)]);

    // An image the draft never references cannot change the body...
    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $unused]), [
            'alt_text' => 'An unused library image',
        ])
        ->assertOk()
        ->assertJsonPath('lock_version', 0)
        ->assertJsonPath('body', null);

    // ...and neither can a field that never reaches the body.
    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $used]), [
            'story_notes' => 'Shot at dusk from the north ridge.',
        ])
        ->assertOk()
        ->assertJsonPath('lock_version', 0)
        ->assertJsonPath('body', null);

    expect($this->post->refresh()->lock_version)->toBe(0);
});

test('generic media updates cannot select or demote the featured image', function (): void {
    $media = PostMedia::factory()->for($this->post)->create([
        'role' => PostMediaRole::Supporting,
        'placement' => PostMediaPlacement::Body,
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'role' => 'hero',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'placement' => 'featured',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('placement');

    expect($media->refresh()->role)->toBe(PostMediaRole::Supporting)
        ->and($media->placement)->toBe(PostMediaPlacement::Body);

    $this->actingAs($this->user)
        ->postJson(route('posts.media.feature', [$this->post, $media]), [
            'lock_version' => $this->post->lock_version,
        ])
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'display_name' => 'Current featured image',
            'role' => 'hero',
            'placement' => 'featured',
        ])
        ->assertSuccessful()
        ->assertJsonPath('media.display_name', 'Current featured image')
        ->assertJsonPath('media.role', 'hero')
        ->assertJsonPath('media.placement', 'featured');

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'role' => 'supporting',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->actingAs($this->user)
        ->patchJson(route('posts.media.update', [$this->post, $media]), [
            'placement' => 'body',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('placement');

    expect($media->refresh()->role)->toBe(PostMediaRole::Hero)
        ->and($media->placement)->toBe(PostMediaPlacement::Featured)
        ->and($this->post->draft()->firstOrFail()->featured_image_path)->toBe($media->path);
});

test('a library image can become the private working drafts featured image', function (): void {
    $publicPath = $this->post->featured_image_path;
    $media = PostMedia::factory()->for($this->post)->create([
        'path' => 'posts/'.$this->post->id.'/library/hero.webp',
        'alt_text' => 'Neighbors planting seedlings together',
        'caption' => 'The first planting day.',
        'role' => PostMediaRole::Supporting,
        'placement' => PostMediaPlacement::Body,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.media.feature', [$this->post, $media]), [
            'lock_version' => $this->post->lock_version,
        ])
        ->assertOk()
        ->assertJsonPath('featured_image_alt', 'Neighbors planting seedlings together')
        ->assertJsonPath('featured_image_caption', 'The first planting day.')
        ->assertJsonPath('lock_version', 1)
        ->assertJsonPath('media.role', 'hero')
        ->assertJsonPath('media.placement', 'featured');

    expect($this->post->refresh()->featured_image_path)->toBe($publicPath)
        ->and($this->post->draft?->featured_image_path)->toBe($media->path)
        ->and($media->refresh()->role)->toBe(PostMediaRole::Hero)
        ->and($media->placement)->toBe(PostMediaPlacement::Featured);

    $replacement = PostMedia::factory()->for($this->post)->create([
        'path' => 'posts/'.$this->post->id.'/library/replacement.webp',
        'role' => PostMediaRole::Supporting,
        'placement' => PostMediaPlacement::Body,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.media.feature', [$this->post, $replacement]), [
            'lock_version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('lock_version', 2);

    expect($media->refresh()->role)->toBe(PostMediaRole::Unassigned)
        ->and($media->placement)->toBe(PostMediaPlacement::Unassigned)
        ->and($replacement->refresh()->role)->toBe(PostMediaRole::Hero)
        ->and($replacement->placement)->toBe(PostMediaPlacement::Featured);

    $this->actingAs($this->user)
        ->deleteJson(route('posts.featured-image.destroy', $this->post), [
            'lock_version' => 2,
        ])
        ->assertOk();

    expect($replacement->refresh()->role)->toBe(PostMediaRole::Unassigned)
        ->and($replacement->placement)->toBe(PostMediaPlacement::Unassigned);
});

test('media used by a retained revision cannot be deleted', function (): void {
    $media = PostMedia::factory()->for($this->post)->libraryUpload()->create();
    PostRevision::factory()->for($this->post)->create([
        'snapshot' => postMediaRevisionSnapshot($this->post, $media),
    ]);
    Storage::disk('public')->put($media->path, 'image');

    $this->actingAs($this->user)
        ->deleteJson(route('posts.media.destroy', [$this->post, $media]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media');

    Storage::disk('public')->assertExists($media->path);
    $this->assertModelExists($media);
});

test('media with saved story writing requires explicit discard confirmation', function (): void {
    $media = PostMedia::factory()->for($this->post)->libraryUpload()->create();
    $task = AuthoringTask::factory()->for($this->post, 'post')->create();
    $plan = AuthoringArtifact::factory()
        ->for($this->post, 'post')
        ->for($task, 'task')
        ->create();
    $composition = AuthoringImageComposition::factory()
        ->for($this->post, 'post')
        ->for($plan, 'plan')
        ->create([
            'passage_markdown' => 'The author already wrote about this photo.',
            'passage_input_hash' => str_repeat('a', 64),
        ]);
    $composition->media()->attach($media->id, [
        'plan_id' => $plan->id,
        'position' => 1,
    ]);
    Storage::disk('public')->put($media->path, 'image');

    $this->actingAs($this->user)
        ->deleteJson(route('posts.media.destroy', [$this->post, $media]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media');

    Storage::disk('public')->assertExists($media->path);
    $this->assertModelExists($media);
    $this->assertModelExists($composition);

    $this->actingAs($this->user)
        ->deleteJson(route('posts.media.destroy', [$this->post, $media]), [
            'discard_story_writing' => true,
        ])
        ->assertNoContent();

    Storage::disk('public')->assertMissing($media->path);
    $this->assertModelMissing($media);
    $this->assertModelMissing($composition);
});

test('media deletion rechecks current post references instead of trusting stale models', function (): void {
    $media = PostMedia::factory()->for($this->post)->libraryUpload()->create();
    $stalePost = Post::query()->findOrFail($this->post->id);
    $currentPost = Post::query()->findOrFail($this->post->id);
    $currentPost->forceFill(['body' => postMediaDocument($media)])->save();
    Storage::disk('public')->put($media->path, 'image');

    expect(
        fn (): mixed => resolve(DeletePostMedia::class)->handle($stalePost, $media),
    )->toThrow(ValidationException::class);

    Storage::disk('public')->assertExists($media->path);
    $this->assertModelExists($media);
});

test('media deletion removes the freshly locked row path after commit', function (): void {
    $media = PostMedia::factory()->for($this->post)->libraryUpload()->create([
        'path' => 'posts/'.$this->post->id.'/media/original.webp',
    ]);
    $staleMedia = PostMedia::query()->findOrFail($media->id);
    $currentPath = 'posts/'.$this->post->id.'/media/current.webp';
    $media->forceFill(['path' => $currentPath])->save();
    Storage::disk('public')->put($staleMedia->path, 'old image');
    Storage::disk('public')->put($currentPath, 'current image');

    resolve(DeletePostMedia::class)->handle($this->post, $staleMedia);

    Storage::disk('public')->assertExists($staleMedia->path);
    Storage::disk('public')->assertMissing($currentPath);
    $this->assertModelMissing($media);
});

test('unplaced library images survive pruning until explicitly removed', function (): void {
    $media = PostMedia::factory()->for($this->post)->libraryUpload()->create([
        'path' => 'posts/'.$this->post->id.'/media/unplaced.webp',
        'created_at' => now()->subDays(2),
    ]);
    Storage::disk('public')->put($media->path, 'image');

    $this->artisan('posts:prune-media')->assertSuccessful();

    Storage::disk('public')->assertExists($media->path);
    $this->assertModelExists($media);

    $this->actingAs($this->user)
        ->deleteJson(route('posts.media.destroy', [$this->post, $media]))
        ->assertNoContent();

    Storage::disk('public')->assertMissing($media->path);
    $this->assertModelMissing($media);
});

test('inline images referenced only by the private draft survive pruning', function (): void {
    $media = PostMedia::factory()->for($this->post)->create([
        'path' => 'posts/'.$this->post->id.'/inline/draft-only.webp',
        'created_at' => now()->subDays(2),
    ]);
    $this->post->forceFill(['body' => ['type' => 'doc', 'content' => []]])->save();
    $this->post->draft()->update(['body' => postMediaDocument($media)]);
    Storage::disk('public')->put($media->path, 'image');

    $this->artisan('posts:prune-media')->assertSuccessful();

    Storage::disk('public')->assertExists($media->path);
    $this->assertModelExists($media);
});

/** @return array<string, mixed> */
function postMediaRevisionSnapshot(Post $post, PostMedia $media): array
{
    return [
        'version' => 1,
        'author_id' => $post->author_id,
        'category_id' => $post->category_id,
        'title' => $post->title,
        'slug' => $post->slug,
        'slug_is_manual' => false,
        'excerpt' => $post->excerpt,
        'body' => postMediaDocument($media),
        'featured_image_path' => null,
        'featured_image_alt' => null,
        'featured_image_caption' => null,
        'seo_title' => null,
        'meta_description' => null,
        'canonical_url' => null,
        'og_title' => null,
        'og_description' => null,
        'og_image_path' => null,
        'noindex' => false,
        'tags' => [],
    ];
}

/** @return array<string, mixed> */
function postMediaDocument(PostMedia $media): array
{
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'image',
            'attrs' => [
                'mediaId' => $media->id,
                'src' => 'media:'.$media->id,
                'alt' => 'A moonlit ridge',
                'caption' => null,
                'width' => $media->width,
                'height' => $media->height,
            ],
        ]],
    ];
}

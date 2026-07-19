<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\CreatePostRevision;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

test('featured images require a supported image and alternative text', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['featured_image_path' => null, 'featured_image_alt' => null]);

    $this->actingAs($this->user)
        ->postJson(route('posts.featured-image.store', $post), [
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'alt_text' => '',
            'lock_version' => 0,
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['image', 'alt_text']);
});

test('administrators can upload, replace, and remove a featured image', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create([
        'featured_image_path' => null,
        'featured_image_alt' => null,
        'featured_image_caption' => 'A previous caption',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('posts.featured-image.store', $post), [
            'image' => UploadedFile::fake()->image('featured.webp', 1600, 900),
            'alt_text' => 'Moonlit mountains',
            'lock_version' => 0,
        ])->assertOk()
        ->assertJsonPath('featured_image_alt', 'Moonlit mountains');

    $firstPath = $post->refresh()->featured_image_path;
    Storage::disk('public')->assertExists((string) $firstPath);
    expect($response->json('lock_version'))->toBe(1);

    $this->actingAs($this->user)
        ->postJson(route('posts.featured-image.store', $post), [
            'image' => UploadedFile::fake()->image('replacement.png', 1600, 900),
            'alt_text' => 'Replacement image',
            'lock_version' => 1,
        ])->assertOk()
        ->assertJsonPath('lock_version', 2);

    expect($post->revisions()->where('featured_image_path', $firstPath)->exists())->toBeTrue();
    Storage::disk('public')->assertExists((string) $firstPath);

    $this->actingAs($this->user)
        ->deleteJson(route('posts.featured-image.destroy', $post), ['lock_version' => 2])
        ->assertOk()
        ->assertJsonPath('featured_image_url', null)
        ->assertJsonPath('featured_image_caption', null);

    expect($post->refresh()->featured_image_path)->toBeNull()
        ->and($post->featured_image_caption)->toBeNull();
});

test('permanent deletion removes current and historical featured images', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['featured_image_path' => 'posts/1/current.webp']);
    $post->revisions()->create([
        'revision_number' => 1,
        'event' => 'saved',
        'slug' => $post->slug,
        'featured_image_path' => 'posts/1/old.webp',
    ]);
    Storage::disk('public')->put('posts/1/current.webp', 'current');
    Storage::disk('public')->put('posts/1/old.webp', 'old');

    $this->actingAs($this->user)->delete(route('posts.destroy', $post));
    $this->actingAs($this->user)->delete(route('posts.force-destroy', $post))->assertRedirect();

    Storage::disk('public')->assertMissing(['posts/1/current.webp', 'posts/1/old.webp']);
});

test('pruned revision images survive when the surrounding transaction rolls back', function (): void {
    config()->set('blog.revision_limit', 1);
    $post = Post::factory()->for($this->user, 'author')->create(['featured_image_path' => 'posts/current.webp']);
    Storage::disk('public')->put('posts/current.webp', 'current');
    Storage::disk('public')->put('posts/old.webp', 'old');
    $post->revisions()->create([
        'revision_number' => 1,
        'event' => PostRevisionEvent::ImageChanged,
        'slug' => $post->slug,
        'featured_image_path' => 'posts/old.webp',
    ]);

    try {
        DB::transaction(function () use ($post): void {
            resolve(CreatePostRevision::class)->handle($post, $this->user, PostRevisionEvent::Saved);

            throw new RuntimeException('Simulated failure after pruning.');
        });
    } catch (RuntimeException) {
        // Expected: the rollback must discard the deferred image deletion.
    }

    Storage::disk('public')->assertExists('posts/old.webp');
    expect($post->revisions()->count())->toBe(1);
});

test('the featured image of a published post cannot be removed', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create(['lock_version' => 0]);

    $this->actingAs($this->user)
        ->deleteJson(route('posts.featured-image.destroy', $post), ['lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    expect($post->refresh()->featured_image_path)->not->toBeNull();
});

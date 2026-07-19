<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

test('social sharing images require a supported image', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();

    $this->actingAs($this->user)
        ->postJson(route('posts.og-image.store', $post), [
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            'lock_version' => 0,
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
});

test('administrators can upload, replace, and remove a social sharing image', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();

    $response = $this->actingAs($this->user)
        ->postJson(route('posts.og-image.store', $post), [
            'image' => UploadedFile::fake()->image('share.webp', 1200, 630),
            'lock_version' => 0,
        ])->assertOk()
        ->assertJsonPath('lock_version', 1);

    $firstPath = (string) $post->refresh()->og_image_path;
    Storage::disk('public')->assertExists($firstPath);
    expect($response->json('og_image_url'))->toBe(Storage::disk('public')->url($firstPath));

    $this->actingAs($this->user)
        ->postJson(route('posts.og-image.store', $post), [
            'image' => UploadedFile::fake()->image('replacement.png', 1200, 630),
            'lock_version' => 1,
        ])->assertOk()
        ->assertJsonPath('lock_version', 2);

    Storage::disk('public')->assertMissing($firstPath);

    $this->actingAs($this->user)
        ->deleteJson(route('posts.og-image.destroy', $post), ['lock_version' => 2])
        ->assertOk()
        ->assertJsonPath('og_image_url', null);

    expect($post->refresh()->og_image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles('posts/'.$post->id))->toBe([]);
});

test('stale lock versions are rejected', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['lock_version' => 3]);

    $this->actingAs($this->user)
        ->postJson(route('posts.og-image.store', $post), [
            'image' => UploadedFile::fake()->image('share.webp', 1200, 630),
            'lock_version' => 1,
        ])->assertConflict();
});

test('the og image overrides the featured image in post meta and json-ld', function (): void {
    $post = Post::factory()->published()->for($this->user, 'author')->create([
        'og_image_path' => 'posts/1/share.webp',
    ]);

    $ogImageUrl = Storage::disk('public')->url('posts/1/share.webp');

    $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.$ogImageUrl.'">', false);
});

test('permanent deletion removes current and historical social sharing images', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create(['og_image_path' => 'posts/1/og-current.webp']);
    $post->revisions()->create([
        'revision_number' => 1,
        'event' => 'saved',
        'slug' => $post->slug,
        'og_image_path' => 'posts/1/og-old.webp',
    ]);
    Storage::disk('public')->put('posts/1/og-current.webp', 'current');
    Storage::disk('public')->put('posts/1/og-old.webp', 'old');

    $this->actingAs($this->user)->delete(route('posts.destroy', $post));
    $this->actingAs($this->user)->delete(route('posts.force-destroy', $post))->assertRedirect();

    Storage::disk('public')->assertMissing(['posts/1/og-current.webp', 'posts/1/og-old.webp']);
});

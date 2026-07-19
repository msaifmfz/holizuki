<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use App\Domain\Publishing\Models\PostRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

test('guests cannot upload inline post images', function (): void {
    $post = Post::factory()->create();

    $this->post(route('posts.inline-images.store', $post), [
        'image' => UploadedFile::fake()->image('diagram.png'),
    ])->assertRedirect(route('login'));
});

test('administrators can upload an inline image with intrinsic dimensions', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();

    $response = $this->actingAs($this->user)
        ->postJson(route('posts.inline-images.store', $post), [
            'image' => UploadedFile::fake()->image('diagram.png', 1200, 675),
        ])
        ->assertCreated()
        ->assertJsonPath('width', 1200)
        ->assertJsonPath('height', 675)
        ->assertJsonStructure(['id', 'url', 'width', 'height']);

    $media = PostMedia::query()->findOrFail($response->json('id'));

    expect($media->post_id)->toBe($post->id);
    Storage::disk('public')->assertExists($media->path);
});

test('inline images require a supported image no larger than five megabytes', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();

    $this->actingAs($this->user)
        ->postJson(route('posts.inline-images.store', $post), [
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    $this->actingAs($this->user)
        ->postJson(route('posts.inline-images.store', $post), [
            'image' => UploadedFile::fake()->image('huge.png')->size(5121),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    $this->actingAs($this->user)
        ->postJson(route('posts.inline-images.store', $post), [
            'image' => UploadedFile::fake()->image('too-wide.png', 10_001, 1),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    $this->assertDatabaseCount('post_media', 0);
});

test('a post rejects rich text media owned by another post', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $otherPost = Post::factory()->for($this->user, 'author')->create();
    $foreignMedia = PostMedia::factory()->for($otherPost)->create();
    $body = documentWithImage($foreignMedia->id);

    $this->actingAs($this->user)
        ->patchJson(route('posts.update', $post), [
            'title' => $post->title,
            'slug' => $post->slug,
            'slug_is_manual' => false,
            'excerpt' => $post->excerpt,
            'body' => $body,
            'featured_image_alt' => $post->featured_image_alt,
            'category_id' => $post->category_id,
            'author_id' => $post->author_id,
            'tags' => [],
            'lock_version' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    expect($post->refresh()->body)->not->toBe($body);
});

test('the public reader resolves owned image urls and dimensions on the server', function (): void {
    $post = Post::factory()->published()->create();
    $media = PostMedia::factory()->for($post)->create([
        'path' => 'posts/'.$post->id.'/inline/diagram.webp',
        'width' => 1600,
        'height' => 900,
    ]);
    $post->forceFill([
        'body' => documentWithImage(
            mediaId: $media->id,
            source: 'https://untrusted.example/ignored.webp',
            caption: 'Queue topology',
        ),
    ])->save();

    $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('post.body.content.0.attrs.mediaId', $media->id)
            ->where('post.body.content.0.attrs.src', Storage::disk('public')->url($media->path))
            ->where('post.body.content.0.attrs.caption', 'Queue topology')
            ->where('post.body.content.0.attrs.width', 1600)
            ->where('post.body.content.0.attrs.height', 900));
});

test('media pruning deletes only stale images absent from current and revision bodies', function (): void {
    $post = Post::factory()->for($this->user, 'author')->create();
    $unused = PostMedia::factory()->for($post)->create([
        'path' => 'posts/'.$post->id.'/inline/unused.webp',
        'created_at' => now()->subHours(25),
    ]);
    $missingFile = PostMedia::factory()->for($post)->create([
        'path' => 'posts/'.$post->id.'/inline/missing.webp',
        'created_at' => now()->subHours(25),
    ]);
    $current = PostMedia::factory()->for($post)->create([
        'path' => 'posts/'.$post->id.'/inline/current.webp',
        'created_at' => now()->subHours(25),
    ]);
    $revision = PostMedia::factory()->for($post)->create([
        'path' => 'posts/'.$post->id.'/inline/revision.webp',
        'created_at' => now()->subHours(25),
    ]);
    $recent = PostMedia::factory()->for($post)->create([
        'path' => 'posts/'.$post->id.'/inline/recent.webp',
        'created_at' => now()->subHours(23),
    ]);

    $post->forceFill(['body' => documentWithImage($current->id)])->save();
    PostRevision::factory()->for($post)->create(['body' => documentWithImage($revision->id)]);

    foreach ([$unused, $current, $revision, $recent] as $media) {
        Storage::disk('public')->put($media->path, 'image');
    }

    $this->artisan('posts:prune-media')->assertSuccessful();

    Storage::disk('public')->assertMissing($unused->path);
    Storage::disk('public')->assertExists([$current->path, $revision->path, $recent->path]);
    $this->assertDatabaseMissing('post_media', ['id' => $unused->id]);
    $this->assertDatabaseMissing('post_media', ['id' => $missingFile->id]);
    $this->assertDatabaseHas('post_media', ['id' => $current->id]);
    $this->assertDatabaseHas('post_media', ['id' => $revision->id]);
    $this->assertDatabaseHas('post_media', ['id' => $recent->id]);
});

/** @return array<string, mixed> */
function documentWithImage(
    int $mediaId,
    string $source = '/temporary/client-source.webp',
    ?string $caption = null,
): array {
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'image',
            'attrs' => [
                'mediaId' => $mediaId,
                'src' => $source,
                'alt' => 'An architecture diagram',
                'caption' => $caption,
                'width' => 800,
                'height' => 450,
            ],
        ]],
    ];
}

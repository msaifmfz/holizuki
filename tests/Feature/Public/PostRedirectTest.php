<?php

use App\Models\Post;
use App\Models\PostRedirect;
use App\Models\User;

test('old slugs of published posts redirect permanently to the current url', function (): void {
    $post = Post::factory()->published()->create(['slug' => 'new-slug']);
    PostRedirect::create(['old_slug' => 'old-slug', 'post_id' => $post->id]);

    $this->get('/posts/old-slug')
        ->assertStatus(301)
        ->assertRedirect(route('public.posts.show', 'new-slug'));
});

test('chained slug changes redirect straight to the final url', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create([
        'slug' => 'slug-a',
        'body' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Body text.']],
            ]],
        ],
    ]);

    foreach (['slug-b', 'slug-c'] as $slug) {
        $post->refresh();
        $this->actingAs($user)
            ->patchJson(route('posts.autosave', $post), [
                'title' => $post->title,
                'slug' => $slug,
                'slug_is_manual' => true,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'featured_image_alt' => $post->featured_image_alt,
                'lock_version' => $post->lock_version,
            ])
            ->assertOk();
    }

    $this->get('/posts/slug-a')->assertStatus(301)->assertRedirect(route('public.posts.show', 'slug-c'));
    $this->get('/posts/slug-b')->assertStatus(301)->assertRedirect(route('public.posts.show', 'slug-c'));
    $this->get('/posts/slug-c')->assertOk();
});

test('redirects to unpublished posts return 404', function (): void {
    $post = Post::factory()->create(['slug' => 'draft-slug']);
    PostRedirect::create(['old_slug' => 'old-slug', 'post_id' => $post->id]);

    $this->get('/posts/old-slug')->assertNotFound();
});

test('unknown slugs still return 404', function (): void {
    $this->get('/posts/never-existed')->assertNotFound();
});

test('a live post shadows any redirect with the same slug', function (): void {
    $winner = Post::factory()->published()->create(['slug' => 'shared-slug']);
    $other = Post::factory()->published()->create(['slug' => 'other-slug']);
    PostRedirect::create(['old_slug' => 'shared-slug', 'post_id' => $other->id]);

    $this->get('/posts/shared-slug')
        ->assertOk()
        ->assertSee($winner->title);
});

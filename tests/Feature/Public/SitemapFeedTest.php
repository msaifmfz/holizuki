<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;

test('the sitemap lists public urls for published content only', function (): void {
    $author = User::factory()->author()->create(['author_slug' => 'jane-writer']);
    $category = Category::factory()->create(['slug' => 'engineering']);
    $published = Post::factory()->published()->for($author, 'author')->create([
        'slug' => 'published-post',
        'category_id' => $category->id,
    ]);
    $published->tags()->attach(Tag::factory()->create(['slug' => 'laravel']));
    Post::factory()->create(['slug' => 'draft-post']);
    Category::factory()->create(['slug' => 'empty-topic']);

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    $xml = $response->getContent();
    expect($xml)->toContain(route('home'))
        ->toContain(route('public.posts.show', 'published-post'))
        ->toContain(route('public.categories.show', 'engineering'))
        ->toContain(route('public.tags.show', 'laravel'))
        ->toContain(route('public.authors.show', 'jane-writer'))
        ->toContain(route('public.about'))
        ->not->toContain('draft-post')
        ->not->toContain('empty-topic');
});

test('the rss feed lists the latest published posts', function (): void {
    $newest = Post::factory()->published()->create([
        'title' => 'Newest & greatest',
        'slug' => 'newest-post',
        'published_at' => now(),
    ]);
    Post::factory()->published()->create(['published_at' => now()->subDay()]);
    Post::factory()->create(['slug' => 'draft-post']);

    $response = $this->get('/feed')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/rss+xml');
    $xml = $response->getContent();
    expect($xml)->toContain('Newest &amp; greatest')
        ->toContain(route('public.posts.show', 'newest-post'))
        ->toContain($newest->published_at->toRssString())
        ->not->toContain('draft-post');
});

test('publishing a post refreshes the cached feed and sitemap', function (): void {
    $user = User::factory()->create();
    Post::factory()->published()->create(['slug' => 'first-post']);

    $this->get('/feed')->assertOk();
    $this->get('/sitemap.xml')->assertOk();

    $draft = Post::factory()->create([
        'slug' => 'fresh-post',
        'body' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Fresh words.']],
            ]],
        ],
        'lock_version' => 0,
    ]);
    $this->actingAs($user)
        ->postJson(route('posts.publish', $draft), ['lock_version' => 0])
        ->assertOk();

    expect($this->get('/feed')->getContent())->toContain('fresh-post')
        ->and($this->get('/sitemap.xml')->getContent())->toContain('fresh-post');
});

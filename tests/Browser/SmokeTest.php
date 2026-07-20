<?php

use App\Domain\Identity\Models\User;
use App\Domain\Inbox\Models\ContactSubmission;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;

it('renders public pages without browser errors', function (): void {
    $author = User::factory()->author()->create(['author_slug' => 'smoke-author']);
    $category = Category::factory()->create(['slug' => 'smoke-category']);
    $post = Post::factory()->published()->for($author, 'author')->create([
        'slug' => 'smoke-post',
        'category_id' => $category->id,
        'featured_image_path' => null,
        'published_at' => '2026-07-18 12:00:00',
    ]);
    $post->tags()->attach(Tag::factory()->create(['slug' => 'smoke-tag']));

    visit([
        '/',
        '/posts/smoke-post',
        '/categories/smoke-category',
        '/tags/smoke-tag',
        '/authors/smoke-author',
        '/search?q=smoke',
        '/topics',
        '/archive',
        '/archive/2026',
        '/archive/2026/07',
        '/about',
        '/contact',
        '/privacy',
        '/terms',
        '/login',
    ])
        ->wait(1)
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

it('renders the custom 404 page without browser errors', function (): void {
    visit('/this-page-does-not-exist')
        ->wait(1)
        ->assertSee('404')
        ->assertNoSmoke();
});

it('keeps error pages inside their originating portal', function (): void {
    config()->set('analytics.dashboard_enabled', false);
    $this->actingAs(User::factory()->create());

    visit('/dashboard/audience')
        ->wait(1)
        ->assertSee('404')
        ->assertPresent('[data-slot="sidebar-wrapper"]')
        ->assertNotPresent('.site-header')
        ->assertSeeLink('Back to dashboard')
        ->assertDontSeeLink('Back to the blog')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();

    visit('/dashboard/does-not-exist')
        ->wait(1)
        ->assertSee('404')
        ->assertPresent('[data-slot="sidebar-wrapper"]')
        ->assertNotPresent('.site-header')
        ->assertSeeLink('Back to dashboard')
        ->assertDontSeeLink('Back to the blog')
        ->assertNoSmoke();

    visit('/this-page-does-not-exist')
        ->wait(1)
        ->assertSee('404')
        ->assertPresent('.site-header')
        ->assertNotPresent('[data-slot="sidebar-wrapper"]')
        ->assertSeeLink('Back to the blog')
        ->assertDontSeeLink('Back to dashboard')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

it('renders authenticated pages without browser errors', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    ContactSubmission::factory()->create();
    $this->actingAs($user);

    visit([
        '/dashboard',
        '/settings/profile',
        '/settings/security',
        '/settings/appearance',
        '/posts',
        "/posts/{$post->id}/edit",
        "/posts/{$post->id}/preview",
        "/posts/{$post->id}/revisions",
        '/categories',
        '/tags',
        '/inbox',
    ])->wait(1)
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

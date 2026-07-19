<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use Inertia\Testing\AssertableInertia;

test('post pages fall back to reader-facing fields when no seo overrides are set', function (): void {
    $post = Post::factory()->published()->create([
        'title' => 'Ten Days in Kyoto',
        'slug' => 'ten-days-in-kyoto',
        'excerpt' => 'A practical two-week itinerary.',
    ]);

    $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/posts/show')
            ->where('seo.title', 'Ten Days in Kyoto')
            ->where('seo.description', 'A practical two-week itinerary.')
            ->where('seo.canonical', route('public.posts.show', 'ten-days-in-kyoto'))
            ->where('seo.type', 'article')
            ->missing('seo.robots')
            ->missing('seo.og_title'));
});

test('per-post seo overrides replace the derived meta values', function (): void {
    $post = Post::factory()->published()->create([
        'title' => 'Ten Days in Kyoto',
        'slug' => 'ten-days-in-kyoto',
        'excerpt' => 'A practical two-week itinerary.',
        'seo_title' => 'Kyoto Itinerary: 10 Days Done Right',
        'meta_description' => 'Day-by-day Kyoto plan with costs.',
        'canonical_url' => 'https://example.com/kyoto-guide',
        'og_title' => 'The Only Kyoto Guide You Need',
        'og_description' => 'Costs, maps, and mistakes to avoid.',
    ]);

    $response = $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('seo.title', 'Kyoto Itinerary: 10 Days Done Right')
            ->where('seo.description', 'Day-by-day Kyoto plan with costs.')
            ->where('seo.canonical', 'https://example.com/kyoto-guide')
            ->where('seo.og_title', 'The Only Kyoto Guide You Need')
            ->where('seo.og_description', 'Costs, maps, and mistakes to avoid.'));

    $response->assertSee('<meta property="og:title" content="The Only Kyoto Guide You Need">', false)
        ->assertSee('<meta property="og:description" content="Costs, maps, and mistakes to avoid.">', false)
        ->assertSee('<link rel="canonical" href="https://example.com/kyoto-guide">', false);
});

test('noindex posts emit a robots meta tag', function (): void {
    $post = Post::factory()->published()->create(['noindex' => true]);

    $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

test('the article json-ld shares one person node between author and publisher', function (): void {
    $author = User::factory()->author()->create([
        'name' => 'Jane Writer',
        'author_slug' => 'jane-writer',
        'social_links' => ['github' => 'https://github.com/jane', 'x' => 'https://x.com/jane'],
    ]);
    $post = Post::factory()->published()->for($author, 'author')->create();

    $response = $this->get(route('public.posts.show', $post->slug))->assertOk();

    $personId = route('public.authors.show', 'jane-writer').'#person';
    $response->assertSee('"@type":"Person"', false)
        ->assertSee('"@id":"'.$personId.'"', false)
        ->assertSee('"sameAs":["https://github.com/jane","https://x.com/jane"]', false)
        ->assertSee('"publisher":{"@id":"'.$personId.'"}', false)
        ->assertSee('"author":{"@id":"'.$personId.'"}', false)
        ->assertDontSee('"@type":"Organization"', false);
});

test('paginated pages emit prev and next link metadata', function (): void {
    $category = Category::factory()->create(['slug' => 'itineraries']);
    Post::factory()->published()->count(25)->create(['category_id' => $category->id]);

    $this->get(route('public.categories.show', ['category' => 'itineraries', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('seo.prev_url', route('public.categories.show', 'itineraries'))
            ->where('seo.next_url', route('public.categories.show', ['category' => 'itineraries', 'page' => 3])))
        ->assertSee('<link rel="prev"', false)
        ->assertSee('<link rel="next"', false);

    $this->get(route('public.categories.show', 'itineraries'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->missing('seo.prev_url')
            ->where('seo.next_url', route('public.categories.show', ['category' => 'itineraries', 'page' => 2])));
});

test('the sitemap uses content_updated_at as lastmod and skips noindex posts', function (): void {
    $post = Post::factory()->published()->create([
        'slug' => 'fresh-guide',
        'published_at' => now()->subWeek(),
        'content_updated_at' => now()->subDay(),
    ]);
    Post::factory()->published()->create(['slug' => 'hidden-guide', 'noindex' => true]);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain(route('public.posts.show', 'fresh-guide'))
        ->toContain((string) $post->content_updated_at?->toISOString())
        ->not->toContain((string) $post->updated_at?->toISOString())
        ->not->toContain('hidden-guide');
});

test('noindex posts remain in the reader-facing feed', function (): void {
    Post::factory()->published()->create(['slug' => 'hidden-guide', 'noindex' => true]);

    expect($this->get('/feed')->assertOk()->getContent())->toContain('hidden-guide');
});

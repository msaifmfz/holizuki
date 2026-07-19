<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

test('topics list only categories and tags used by published posts', function (): void {
    $publishedCategory = Category::factory()->create(['name' => 'Engineering', 'slug' => 'engineering']);
    $draftOnlyCategory = Category::factory()->create(['name' => 'Drafts', 'slug' => 'drafts']);
    $publishedTag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $draftOnlyTag = Tag::factory()->create(['name' => 'Secret', 'slug' => 'secret']);

    $first = Post::factory()->published()->create(['category_id' => $publishedCategory->id]);
    $second = Post::factory()->published()->create(['category_id' => $publishedCategory->id]);
    $first->tags()->attach($publishedTag);
    $second->tags()->attach($publishedTag);

    $draft = Post::factory()->create(['category_id' => $draftOnlyCategory->id]);
    $draft->tags()->attach($draftOnlyTag);

    $this->get(route('public.topics'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/topics')
            ->has('categories', 1)
            ->where('categories.0.slug', $publishedCategory->slug)
            ->where('categories.0.posts_count', 2)
            ->has('tags', 1)
            ->where('tags.0.slug', $publishedTag->slug)
            ->where('tags.0.posts_count', 2)
            ->where('seo.canonical', route('public.topics')));
});

test('archive pages filter published posts by year and month', function (): void {
    $july = Post::factory()->published()->create([
        'title' => 'July article',
        'published_at' => '2026-07-12 12:00:00',
    ]);
    $june = Post::factory()->published()->create([
        'title' => 'June article',
        'published_at' => '2026-06-12 12:00:00',
    ]);
    Post::factory()->published()->create([
        'title' => 'Older article',
        'published_at' => '2025-12-12 12:00:00',
    ]);
    Post::factory()->create([
        'title' => 'Draft in July',
        'published_at' => '2026-07-13 12:00:00',
    ]);

    $this->get(route('public.archive', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/archive')
            ->has('posts.data', 2)
            ->where('posts.data.0.id', $july->id)
            ->where('posts.data.1.id', $june->id)
            ->where('seo.canonical', route('public.archive', ['year' => 2026])));

    $this->get(route('public.archive', ['year' => 2026, 'month' => '07']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/archive')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $july->id)
            ->where('seo.canonical', route('public.archive', ['year' => 2026, 'month' => '07'])));
});

test('archive rejects invalid and empty requested periods', function (): void {
    Post::factory()->published()->create(['published_at' => '2026-07-12 12:00:00']);

    $this->get('/archive/2024')->assertNotFound();
    $this->get('/archive/2026/02')->assertNotFound();
    $this->get('/archive/2026/7')->assertNotFound();
    $this->get('/archive/2026/13')->assertNotFound();
    $this->get('/archive/not-a-year')->assertNotFound();
});

test('post navigation follows publication chronology and excludes non-public posts', function (): void {
    Date::setTestNow('2026-07-18 12:00:00');

    $older = Post::factory()->published()->create([
        'title' => 'Older article',
        'published_at' => now()->subDays(2),
    ]);
    $current = Post::factory()->published()->create([
        'title' => 'Current article',
        'published_at' => now()->subDay(),
    ]);
    $newer = Post::factory()->published()->create([
        'title' => 'Newer article',
        'published_at' => now(),
    ]);
    Post::factory()->create(['published_at' => now()->subHours(12)]);
    Post::factory()->scheduled()->create(['scheduled_at' => now()->addHour()]);
    $trashed = Post::factory()->published()->create(['published_at' => now()->subHours(12)]);
    $trashed->delete();

    $this->get(route('public.posts.show', $current->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('previous.id', $older->id)
            ->where('next.id', $newer->id));
});

test('related posts prefer shared tags then category before recent fallbacks', function (): void {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $sharedTag = Tag::factory()->create();

    $post = Post::factory()->published()->create([
        'category_id' => $category->id,
        'published_at' => '2026-07-10 12:00:00',
    ]);
    $post->tags()->attach($sharedTag);

    $tagMatch = Post::factory()->published()->create([
        'category_id' => $otherCategory->id,
        'published_at' => '2026-07-01 12:00:00',
    ]);
    $tagMatch->tags()->attach($sharedTag);

    $categoryMatch = Post::factory()->published()->create([
        'category_id' => $category->id,
        'published_at' => '2026-07-09 12:00:00',
    ]);
    $recentFallback = Post::factory()->published()->create([
        'category_id' => $otherCategory->id,
        'published_at' => '2026-07-08 12:00:00',
    ]);

    $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('related', 3)
            ->where('related.0.id', $tagMatch->id)
            ->where('related.1.id', $categoryMatch->id)
            ->where('related.2.id', $recentFallback->id));
});

test('search includes body taxonomy tags and author names and favors title matches', function (): void {
    $author = User::factory()->author()->create(['name' => 'Octavia Writer']);
    $category = Category::factory()->create(['name' => 'Distributed Systems']);
    $tag = Tag::factory()->create(['name' => 'Observability']);

    $bodyMatch = Post::factory()->published()->for($author, 'author')->create([
        'title' => 'A recent note',
        'category_id' => $category->id,
        'published_at' => now(),
        'body' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Immutable walruses live here.']]]],
        ],
    ]);
    $bodyMatch->tags()->attach($tag);

    $titleMatch = Post::factory()->published()->create([
        'title' => 'Walruses in production',
        'excerpt' => 'A title-weighted result.',
        'published_at' => now()->subYear(),
    ]);

    foreach (['immutable', 'distributed', 'observability', 'octavia'] as $query) {
        $this->get(route('public.search', ['q' => $query]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $bodyMatch->id));
    }

    $this->get(route('public.search', ['q' => 'walruses']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('posts.data', 2)
            ->where('posts.data.0.id', $titleMatch->id)
            ->where('posts.data.1.id', $bodyMatch->id));
});

<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the homepage shows only published posts with the newest as featured', function (): void {
    $older = Post::factory()->published()->create(['title' => 'Older article', 'published_at' => now()->subDay()]);
    $newest = Post::factory()->published()->create(['title' => 'Newest article', 'published_at' => now()]);
    Post::factory()->create(['title' => 'Draft article']);
    Post::factory()->scheduled()->create(['title' => 'Scheduled article']);
    Post::factory()->published()->create(['title' => 'Trashed article'])->delete();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/home')
            ->where('featured.id', $newest->id)
            ->where('featured.title', 'Newest article')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $older->id)
            ->has('seo.title')
            ->has('seo.canonical')
            ->has('footerCategories'));
});

test('the homepage renders an empty state when nothing is published', function (): void {
    Post::factory()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/home')
            ->where('featured', null)
            ->has('posts.data', 0));
});

test('a published post renders with taxonomy, author, and related posts', function (): void {
    $category = Category::factory()->create(['name' => 'Engineering']);
    $author = User::factory()->author()->create(['name' => 'Jane Writer']);
    $post = Post::factory()->published()->for($author, 'author')->create([
        'category_id' => $category->id,
        'title' => 'The main article',
    ]);
    $post->tags()->attach(Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']));

    $related = Post::factory()->published()->create(['category_id' => $category->id]);
    Post::factory()->published()->create();

    $this->get(route('public.posts.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/posts/show')
            ->where('post.title', 'The main article')
            ->where('post.category.name', 'Engineering')
            ->where('post.tags.0.slug', 'laravel')
            ->where('post.author.name', 'Jane Writer')
            ->has('post.body')
            ->has('related', 1)
            ->where('related.0.id', $related->id)
            ->where('seo.type', 'article')
            ->where('seo.canonical', route('public.posts.show', $post->slug))
            ->has('seo.json_ld'));
});

test('draft, scheduled, and trashed posts are not publicly visible', function (): void {
    $draft = Post::factory()->create();
    $scheduled = Post::factory()->scheduled()->create();
    $trashed = Post::factory()->published()->create();
    $trashed->delete();

    foreach ([$draft, $scheduled, $trashed] as $post) {
        $this->get('/posts/'.$post->slug)->assertNotFound();
    }
});

test('unknown public urls render the custom error page with a 404 status', function (): void {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('error')
            ->where('status', 404));

    $this->get('/posts/not-a-real-slug')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('error')
            ->where('status', 404));
});

test('the static pages render with seo metadata', function (array $routeData): void {
    [$routeName, $component] = $routeData;

    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component($component)
            ->has('seo.title')
            ->where('seo.canonical', route($routeName)));
})->with([
    'about' => [['public.about', 'public/about']],
    'privacy' => [['public.privacy', 'public/privacy']],
    'terms' => [['public.terms', 'public/terms']],
]);

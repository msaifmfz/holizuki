<?php

use App\Models\Post;
use Inertia\Testing\AssertableInertia;

test('search matches published posts by title and excerpt', function (): void {
    Post::factory()->published()->create(['title' => 'Queues in depth', 'excerpt' => 'Plain summary.']);
    Post::factory()->published()->create(['title' => 'Another post', 'excerpt' => 'All about queues here.']);
    Post::factory()->published()->create(['title' => 'Unrelated', 'excerpt' => 'Nothing to see.']);
    Post::factory()->create(['title' => 'Queues draft', 'excerpt' => 'A draft about queues.']);

    $this->get(route('public.search', ['q' => 'queues']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/search')
            ->where('query', 'queues')
            ->has('posts.data', 2)
            ->where('seo.robots', 'noindex,follow'));
});

test('search does not treat like wildcards as wildcards', function (): void {
    Post::factory()->published()->create(['title' => 'Shipping with confidence']);
    Post::factory()->published()->create(['title' => 'Plain title']);

    $this->get(route('public.search', ['q' => '%']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('posts.data', 0));

    $this->get(route('public.search', ['q' => '_____']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('posts.data', 0));
});

test('a blank query renders the empty search state', function (): void {
    Post::factory()->published()->create();

    $this->get(route('public.search'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/search')
            ->where('query', '')
            ->has('posts.data', 0));
});

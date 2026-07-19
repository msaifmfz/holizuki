<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
use Inertia\Testing\AssertableInertia;

test('a category archive lists only its published posts', function (): void {
    $category = Category::factory()->create(['name' => 'Engineering', 'slug' => 'engineering']);
    $published = Post::factory()->published()->create(['category_id' => $category->id]);
    Post::factory()->create(['category_id' => $category->id]);
    Post::factory()->published()->create();

    $this->get(route('public.categories.show', 'engineering'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/categories/show')
            ->where('category.name', 'Engineering')
            ->where('category.posts_count', 1)
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $published->id)
            ->where('seo.canonical', route('public.categories.show', 'engineering')));
});

test('archives paginate at twelve posts per page', function (): void {
    $category = Category::factory()->create(['slug' => 'notes']);
    Post::factory()->published()->count(13)->create(['category_id' => $category->id]);

    $this->get(route('public.categories.show', ['category' => 'notes', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/categories/show')
            ->has('posts.data', 1)
            ->where('posts.current_page', 2)
            ->where('posts.total', 13));
});

test('a tag archive lists published posts carrying the tag', function (): void {
    $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $tagged = Post::factory()->published()->create();
    $tagged->tags()->attach($tag);
    $draftTagged = Post::factory()->create();
    $draftTagged->tags()->attach($tag);
    Post::factory()->published()->create();

    $this->get(route('public.tags.show', 'laravel'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/tags/show')
            ->where('tag.name', 'Laravel')
            ->where('tag.posts_count', 1)
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $tagged->id));
});

test('an author archive lists the author profile and their published posts', function (): void {
    $author = User::factory()->author()->create(['name' => 'Jane Writer', 'author_slug' => 'jane-writer']);
    $post = Post::factory()->published()->for($author, 'author')->create();
    Post::factory()->for($author, 'author')->create();
    Post::factory()->published()->create();

    $this->get(route('public.authors.show', 'jane-writer'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('public/authors/show')
            ->where('author.name', 'Jane Writer')
            ->where('author.posts_count', 1)
            ->has('author.bio')
            ->has('author.social_links')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $post->id));
});

test('unknown archive slugs return 404', function (): void {
    $this->get('/categories/nope')->assertNotFound();
    $this->get('/tags/nope')->assertNotFound();
    $this->get('/authors/nope')->assertNotFound();
});

test('the footer highlights categories that have published posts', function (): void {
    $active = Category::factory()->create(['name' => 'Active']);
    Post::factory()->published()->create(['category_id' => $active->id]);
    Category::factory()->create(['name' => 'Empty']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('footerCategories', 1)
            ->where('footerCategories.0.name', 'Active'));
});

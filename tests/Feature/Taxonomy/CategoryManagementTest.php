<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('guests cannot access category management', function (): void {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
    $this->post(route('categories.store'), ['name' => 'Tech'])->assertRedirect(route('login'));
});

test('administrators can list categories with post counts', function (): void {
    $category = Category::factory()->create(['name' => 'Engineering']);
    Post::factory()->count(2)->for($this->user, 'author')->create(['category_id' => $category->id]);
    Category::factory()->create(['name' => 'Design']);

    $this->actingAs($this->user)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('categories/index')
            ->has('categories', 2)
            ->where('categories.0.name', 'Design')
            ->where('categories.0.posts_count', 0)
            ->where('categories.1.name', 'Engineering')
            ->where('categories.1.posts_count', 2));
});

test('administrators can create a category with an auto-generated slug', function (): void {
    $this->actingAs($this->user)
        ->post(route('categories.store'), ['name' => 'Product Updates', 'description' => 'News about the product.'])
        ->assertRedirect(route('categories.index'));

    $category = Category::firstOrFail();
    expect($category->name)->toBe('Product Updates')
        ->and($category->slug)->toBe('product-updates')
        ->and($category->description)->toBe('News about the product.');
});

test('category slugs are suffixed when they collide', function (): void {
    Category::factory()->create(['name' => 'Tech', 'slug' => 'tech']);

    $this->actingAs($this->user)->post(route('categories.store'), ['name' => 'Tech!']);

    expect(Category::where('name', 'Tech!')->firstOrFail()->slug)->toBe('tech-2');
});

test('category names must be unique', function (): void {
    Category::factory()->create(['name' => 'Tech']);

    $this->actingAs($this->user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), ['name' => 'Tech'])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasErrors('name');
});

test('administrators can update a category and its slug follows the name', function (): void {
    $category = Category::factory()->create(['name' => 'Tech', 'slug' => 'tech']);

    $this->actingAs($this->user)
        ->put(route('categories.update', $category), ['name' => 'Technology'])
        ->assertRedirect(route('categories.index'));

    $category->refresh();
    expect($category->name)->toBe('Technology')
        ->and($category->slug)->toBe('technology')
        ->and($category->description)->toBeNull();
});

test('deleting a category leaves its posts uncategorized', function (): void {
    $category = Category::factory()->create();
    $post = Post::factory()->for($this->user, 'author')->create(['category_id' => $category->id]);

    $this->actingAs($this->user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'));

    expect(Category::count())->toBe(0)
        ->and($post->refresh()->category_id)->toBeNull()
        ->and($post->trashed())->toBeFalse();
});

test('category validation enforces length limits', function (array $payload, string $field): void {
    $this->actingAs($this->user)
        ->post(route('categories.store'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'name too long' => [['name' => str_repeat('a', 101)], 'name'],
    'name missing' => [['name' => ''], 'name'],
    'description too long' => [['name' => 'Valid', 'description' => str_repeat('a', 501)], 'description'],
]);

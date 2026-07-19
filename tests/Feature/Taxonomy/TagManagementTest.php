<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Tag;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('guests cannot access tag management', function (): void {
    $this->get(route('tags.index'))->assertRedirect(route('login'));
    $this->post(route('tags.store'), ['name' => 'Laravel'])->assertRedirect(route('login'));
});

test('administrators can list tags with post counts', function (): void {
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $post = Post::factory()->for($this->user, 'author')->create();
    $post->tags()->attach($tag);

    $this->actingAs($this->user)
        ->get(route('tags.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('tags/index')
            ->has('tags', 1)
            ->where('tags.0.name', 'Laravel')
            ->where('tags.0.posts_count', 1));
});

test('administrators can create a tag with an auto-generated slug', function (): void {
    $this->actingAs($this->user)
        ->post(route('tags.store'), ['name' => 'Dev Tools'])
        ->assertRedirect(route('tags.index'));

    expect(Tag::firstOrFail()->slug)->toBe('dev-tools');
});

test('tag slugs are suffixed when they collide', function (): void {
    Tag::factory()->create(['name' => 'PHP', 'slug' => 'php']);

    $this->actingAs($this->user)->post(route('tags.store'), ['name' => 'PHP!']);

    expect(Tag::where('name', 'PHP!')->firstOrFail()->slug)->toBe('php-2');
});

test('tag names must be unique', function (): void {
    Tag::factory()->create(['name' => 'Laravel']);

    $this->actingAs($this->user)
        ->from(route('tags.index'))
        ->post(route('tags.store'), ['name' => 'Laravel'])
        ->assertRedirect(route('tags.index'))
        ->assertSessionHasErrors('name');
});

test('administrators can rename a tag and its slug follows the name', function (): void {
    $tag = Tag::factory()->create(['name' => 'JS', 'slug' => 'js']);

    $this->actingAs($this->user)
        ->put(route('tags.update', $tag), ['name' => 'JavaScript'])
        ->assertRedirect(route('tags.index'));

    $tag->refresh();
    expect($tag->name)->toBe('JavaScript')->and($tag->slug)->toBe('javascript');
});

test('deleting a tag detaches it from posts without deleting them', function (): void {
    $tag = Tag::factory()->create();
    $post = Post::factory()->for($this->user, 'author')->create();
    $post->tags()->attach($tag);

    $this->actingAs($this->user)
        ->delete(route('tags.destroy', $tag))
        ->assertRedirect(route('tags.index'));

    expect(Tag::count())->toBe(0)
        ->and($post->refresh()->tags)->toHaveCount(0)
        ->and($post->trashed())->toBeFalse();
});

<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('guests cannot curate featured posts', function (): void {
    $post = Post::factory()->published()->create();

    $this->post(route('posts.feature.store', $post))->assertRedirect(route('login'));
    $this->delete(route('posts.feature.destroy', $post))->assertRedirect(route('login'));
});

test('only published posts may be featured', function (): void {
    $draft = Post::factory()->create();
    $scheduled = Post::factory()->scheduled()->create();

    $this->actingAs($this->user)
        ->postJson(route('posts.feature.store', $draft))
        ->assertUnprocessable();
    $this->actingAs($this->user)
        ->postJson(route('posts.feature.store', $scheduled))
        ->assertUnprocessable();

    expect($draft->refresh()->featured_at)->toBeNull()
        ->and($scheduled->refresh()->featured_at)->toBeNull();
});

test('featuring a fourth post removes the oldest curated post', function (): void {
    $posts = Post::factory()->published()->count(4)->create();

    foreach ($posts as $index => $post) {
        Date::setTestNow(sprintf('2026-07-18 12:%02d:00', $index));

        $this->actingAs($this->user)
            ->post(route('posts.feature.store', $post))
            ->assertNoContent();
    }

    expect($posts[0]->refresh()->featured_at)->toBeNull()
        ->and(Post::query()->whereNotNull('featured_at')->oldest('featured_at')->pluck('id')->all())
        ->toBe([$posts[1]->id, $posts[2]->id, $posts[3]->id]);
});

test('a featured post can be removed from the curated selection', function (): void {
    $post = Post::factory()->published()->create(['featured_at' => now()]);

    $this->actingAs($this->user)
        ->delete(route('posts.feature.destroy', $post))
        ->assertNoContent();

    expect($post->refresh()->featured_at)->toBeNull();
});

test('unpublishing or trashing a post removes it from the curated selection', function (): void {
    $unpublished = Post::factory()->published()->for($this->user, 'author')->create([
        'featured_at' => now()->subMinute(),
        'lock_version' => 0,
    ]);
    $trashed = Post::factory()->published()->for($this->user, 'author')->create([
        'featured_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('posts.unpublish', $unpublished), ['lock_version' => 0])
        ->assertOk();
    $this->actingAs($this->user)
        ->delete(route('posts.destroy', $trashed))
        ->assertRedirect();

    expect($unpublished->refresh()->featured_at)->toBeNull()
        ->and(Post::withTrashed()->findOrFail($trashed->id)->featured_at)->toBeNull();
});

test('the homepage orders curated posts and excludes them from the recent feed', function (): void {
    $olderFeature = Post::factory()->published()->create([
        'title' => 'Older feature',
        'featured_at' => now()->subMinute(),
        'published_at' => now()->subDays(2),
    ]);
    $newerFeature = Post::factory()->published()->create([
        'title' => 'Newer feature',
        'featured_at' => now(),
        'published_at' => now()->subDay(),
    ]);
    $recent = Post::factory()->published()->create([
        'title' => 'Recent article',
        'published_at' => now(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('featured', 2)
            ->where('featured.0.id', $newerFeature->id)
            ->where('featured.1.id', $olderFeature->id)
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $recent->id));
});

test('the homepage falls back to the newest post when nothing is curated', function (): void {
    $older = Post::factory()->published()->create(['published_at' => now()->subDay()]);
    $newest = Post::factory()->published()->create(['published_at' => now()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('featured', 1)
            ->where('featured.0.id', $newest->id)
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $older->id));
});

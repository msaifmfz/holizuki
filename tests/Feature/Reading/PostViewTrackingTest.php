<?php

use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Models\PostView;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

test('a published post view is counted once per session and UTC day', function (): void {
    Date::setTestNow('2026-07-18 12:00:00');
    $post = Post::factory()->published()->create();
    $this->withCookie(config('session.cookie'), str_repeat('a', 40));

    $this->post(route('public.posts.views.store', $post->slug))
        ->assertNoContent();
    $this->post(route('public.posts.views.store', $post->slug))
        ->assertNoContent();

    $this->assertDatabaseCount('post_views', 1);
    $this->assertDatabaseHas('post_views', [
        'post_id' => $post->id,
        'viewed_on' => '2026-07-18',
    ]);

    Date::setTestNow('2026-07-19 00:01:00');

    $this->post(route('public.posts.views.store', $post->slug))
        ->assertNoContent();

    $this->assertDatabaseCount('post_views', 2);
});

test('view events reject posts that are not publicly visible', function (): void {
    $draft = Post::factory()->create();
    $scheduled = Post::factory()->scheduled()->create();
    $trashed = Post::factory()->published()->create();
    $trashed->delete();

    foreach ([$draft, $scheduled, $trashed] as $post) {
        $this->post('/posts/'.$post->slug.'/views')->assertNotFound();
    }
});

test('view events are limited to thirty requests per session each minute', function (): void {
    $post = Post::factory()->published()->create();

    $this->withCookie(config('session.cookie'), str_repeat('b', 40));

    foreach (range(1, 30) as $request) {
        $this->post(route('public.posts.views.store', $post->slug))
            ->assertNoContent();
    }

    $this->post(route('public.posts.views.store', $post->slug))
        ->assertTooManyRequests();
    $this->assertDatabaseCount('post_views', 1);
});

test('view storage contains no reusable visitor or request fingerprint', function (): void {
    expect(Schema::hasColumns('post_views', [
        'post_id',
        'viewed_on',
        'visitor_hash',
        'created_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('post_views', 'ip_address'))->toBeFalse()
        ->and(Schema::hasColumn('post_views', 'user_agent'))->toBeFalse()
        ->and(Schema::hasColumn('post_views', 'session_id'))->toBeFalse();
});

test('popular posts use the last thirty days and exclude featured posts', function (): void {
    Date::setTestNow('2026-07-18 12:00:00');
    $featured = Post::factory()->published()->create(['featured_at' => now()]);
    $popular = Post::factory()->published()->create();
    $runnerUp = Post::factory()->published()->create();
    $oldOnly = Post::factory()->published()->create();

    PostView::factory()->for($featured)->count(5)->create(['viewed_on' => today()]);
    PostView::factory()->for($popular)->count(4)->create(['viewed_on' => today()->subDays(29)]);
    PostView::factory()->for($runnerUp)->count(2)->create(['viewed_on' => today()]);
    PostView::factory()->for($oldOnly)->count(8)->create(['viewed_on' => today()->subDays(30)]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('popular', 2)
            ->where('popular.0.id', $popular->id)
            ->where('popular.1.id', $runnerUp->id));
});

test('popular posts stay hidden until a recent view exists', function (): void {
    Post::factory()->published()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('popular', 0));
});

test('post view records older than the retention window are pruned', function (): void {
    Date::setTestNow('2026-07-18 12:00:00');
    $post = Post::factory()->published()->create();
    $expired = PostView::factory()->for($post)->create(['viewed_on' => today()->subDays(91)]);
    $retained = PostView::factory()->for($post)->create(['viewed_on' => today()->subDays(90)]);

    $this->artisan('model:prune', ['--model' => PostView::class])->assertSuccessful();

    $this->assertDatabaseMissing('post_views', ['id' => $expired->id]);
    $this->assertDatabaseHas('post_views', ['id' => $retained->id]);
});

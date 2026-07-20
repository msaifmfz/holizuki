<?php

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

it('renders disabled analytics pages in the admin portal', function (string $routeName): void {
    config()->set('analytics.dashboard_enabled', false);
    $administrator = User::factory()->create();

    $this->actingAs($administrator)
        ->get(route($routeName))
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('error')
            ->where('status', 404)
            ->where('portal', 'admin'));
})->with([
    'post analytics' => 'dashboard.posts.index',
    'audience analytics' => 'dashboard.audience',
]);

it('keeps administrator not found responses in the admin portal', function (string $url): void {
    $administrator = User::factory()->create();

    $this->actingAs($administrator)
        ->get($url)
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('error')
            ->where('status', 404)
            ->where('portal', 'admin'));
})->with([
    'missing post editor' => '/posts/999999/edit',
    'dashboard route' => '/dashboard/does-not-exist',
    'settings route' => '/settings/does-not-exist',
    'inbox route' => '/inbox/does-not-exist',
    'community route' => '/community/does-not-exist',
    'security route' => '/user/does-not-exist',
]);

it('keeps public not found responses in the public portal for administrators', function (string $url): void {
    $administrator = User::factory()->create();

    $this->actingAs($administrator)
        ->get($url)
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('error')
            ->where('status', 404)
            ->where('portal', 'public'));
})->with([
    'unknown public route' => '/this-page-does-not-exist',
    'public post' => '/posts/not-a-real-slug',
    'public category' => '/categories/not-a-real-slug',
    'public tag' => '/tags/not-a-real-slug',
]);

it('does not expose the admin portal to readers denied from admin routes', function (): void {
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)
        ->get(route('dashboard'))
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('error')
            ->where('status', 403)
            ->where('portal', 'public'));
});

it('keeps json errors out of the inertia error page', function (): void {
    config()->set('analytics.dashboard_enabled', false);
    $administrator = User::factory()->create();

    $this->actingAs($administrator)
        ->getJson(route('dashboard.analytics.realtime'))
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('renders production server errors in their originating portal', function (int $status, string $portal, array $middleware): void {
    app()->detectEnvironment(static fn (): string => 'production');
    $url = "/__testing/error-pages/{$portal}/{$status}";

    Route::get($url, static function () use ($status): never {
        abort($status);
    })->middleware($middleware);

    $administrator = User::factory()->create();
    $response = $this->actingAs($administrator)->get($url);

    if ($status === 503) {
        $response->assertServiceUnavailable();
    } else {
        $response->assertInternalServerError();
    }

    $response->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('error')
        ->where('status', $status)
        ->where('portal', $portal));
})->with([
    'admin 500' => [500, 'admin', ['web', 'auth', 'access-author-portal']],
    'admin 503' => [503, 'admin', ['web', 'auth', 'access-author-portal']],
    'public 500' => [500, 'public', ['web']],
    'public 503' => [503, 'public', ['web']],
]);

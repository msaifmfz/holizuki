<?php

use App\Domain\Identity\Models\User;

test('guests are redirected to the login page', function (): void {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('readers cannot visit author or settings pages', function (string $url): void {
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->get($url)->assertForbidden();
})->with([
    'dashboard' => '/dashboard',
    'posts' => '/posts',
    'community' => '/community/comments',
    'settings' => '/settings/profile',
    'passkeys' => '/user/passkeys/options',
]);

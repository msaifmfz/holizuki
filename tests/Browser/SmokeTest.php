<?php

use App\Models\Post;
use App\Models\User;

it('renders public pages without browser errors', function (): void {
    visit(['/', '/login'])
        ->wait(1)
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

it('renders authenticated pages without browser errors', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();
    $this->actingAs($user);

    visit([
        '/dashboard',
        '/settings/profile',
        '/settings/security',
        '/settings/appearance',
        '/posts',
        "/posts/{$post->id}/edit",
        "/posts/{$post->id}/preview",
        "/posts/{$post->id}/revisions",
    ])->wait(1)
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

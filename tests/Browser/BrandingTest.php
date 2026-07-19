<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;

it('uses Holizuki branding on the public homepage', function (): void {
    Post::factory()->published()->create(['title' => 'Designing calm publishing tools']);

    visit('/')
        ->wait(1)
        ->assertTitle('Home - Holizuki')
        ->assertSee('Holizuki')
        ->assertSee('Designing calm publishing tools')
        ->assertDontSee('Laravel')
        ->assertDontSee('Laracasts')
        ->assertDontSee('Deploy now')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

it('uses Holizuki branding on the login page', function (): void {
    visit('/login')
        ->wait(1)
        ->assertTitle('Log in - Holizuki')
        ->assertDontSee('Laravel')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

it('keeps the Holizuki homepage usable in mobile dark mode', function (): void {
    Post::factory()->published()->create(['title' => 'Designing calm publishing tools']);

    visit('/')
        ->on()->iPhone14Pro()
        ->inDarkMode()
        ->wait(1)
        ->assertTitle('Home - Holizuki')
        ->assertSee('Holizuki')
        ->assertSee('Designing calm publishing tools')
        ->assertDontSee('Laravel')
        ->assertNoSmoke();
});

it('uses Holizuki branding in the authenticated application shell', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    visit('/dashboard')
        ->wait(1)
        ->assertTitle('Dashboard - Holizuki')
        ->assertSee('Holizuki')
        ->assertDontSee('Laravel')
        ->assertDontSee('Repository')
        ->assertDontSee('Documentation')
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

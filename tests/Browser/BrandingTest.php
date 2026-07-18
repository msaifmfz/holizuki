<?php

use App\Models\User;

it('uses Holizuki branding on public pages', function (): void {
    visit('/')
        ->wait(1)
        ->assertTitle('Welcome - Holizuki')
        ->assertSee('Holizuki')
        ->assertSee('Write, revise, and publish')
        ->assertSee('Log in to continue')
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

it('keeps the Holizuki welcome design usable in mobile dark mode', function (): void {
    visit('/')
        ->on()->iPhone14Pro()
        ->inDarkMode()
        ->wait(1)
        ->assertTitle('Welcome - Holizuki')
        ->assertSee('Holizuki')
        ->assertSee('Log in to continue')
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

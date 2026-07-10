<?php

use App\Models\User;

it('renders public pages without browser errors', function (): void {
    visit(['/', '/login', '/register'])
        ->wait(1)
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

it('renders authenticated pages without browser errors', function (): void {
    $this->actingAs(User::factory()->create());

    visit([
        '/dashboard',
        '/settings/profile',
        '/settings/security',
        '/settings/appearance',
    ])->wait(1)
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

<?php

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

test('administrators are granted every gate ability', function (): void {
    $administrator = User::factory()->create();

    expect($administrator->role)->toBe(UserRole::Administrator)
        ->and($administrator->isAdministrator())->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('manage-blog'))->toBeTrue();
});

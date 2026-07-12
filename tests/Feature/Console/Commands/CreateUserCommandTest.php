<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('an administrator can be created interactively', function (): void {
    $password = 'ValidPassword1!';

    $this->artisan('user:create')
        ->expectsQuestion('Name', ' Site Administrator ')
        ->expectsQuestion('Email address', 'ADMIN@EXAMPLE.COM')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->expectsOutputToContain('Administrator admin@example.com created successfully.')
        ->doesntExpectOutputToContain($password)
        ->assertSuccessful();

    $user = User::query()->sole();

    $this->assertModelExists($user);
    expect($user->name)->toBe('Site Administrator')
        ->and($user->email)->toBe('admin@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Administrator)
        ->and(Hash::check($password, $user->password))->toBeTrue();
});

test('duplicate email addresses are rejected', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('user:create')
        ->expectsQuestion('Name', 'New Administrator')
        ->expectsQuestion('Email address', 'existing@example.com')
        ->expectsQuestion('Password', 'ValidPassword1!')
        ->expectsQuestion('Confirm password', 'ValidPassword1!')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

test('weak passwords are rejected', function (): void {
    $this->artisan('user:create')
        ->expectsQuestion('Name', 'New Administrator')
        ->expectsQuestion('Email address', 'new@example.com')
        ->expectsQuestion('Password', 'short')
        ->expectsQuestion('Confirm password', 'short')
        ->assertFailed();

    expect(User::query()->exists())->toBeFalse();
});

test('password confirmation must match', function (): void {
    $this->artisan('user:create')
        ->expectsQuestion('Name', 'New Administrator')
        ->expectsQuestion('Email address', 'new@example.com')
        ->expectsQuestion('Password', 'ValidPassword1!')
        ->expectsQuestion('Confirm password', 'different-password')
        ->assertFailed();

    expect(User::query()->exists())->toBeFalse();
});

<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('registration screen is not available', function (): void {
    $response = $this->get('/register');

    $response->assertNotFound();
    expect(Route::has('register'))->toBeFalse();
});

test('users cannot register publicly', function (): void {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();

    expect(Route::has('register.store'))->toBeFalse()
        ->and(User::query()->exists())->toBeFalse();
});

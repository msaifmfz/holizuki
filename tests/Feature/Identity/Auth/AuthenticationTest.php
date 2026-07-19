<?php

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function (): void {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('readers return to a same origin public path after login', function (): void {
    $reader = User::factory()->reader()->create();

    $response = $this->post(route('login.store'), [
        'email' => $reader->email,
        'password' => 'password',
        'return_to' => '/topics',
    ]);

    $response->assertRedirect('/topics');
});

test('the login page carries a requested public return path into the form', function (): void {
    $this->get(route('login', ['return_to' => '/posts/welcome#comments']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('returnTo', '/posts/welcome#comments'));
});

test('readers never redirect into the author portal after login', function (): void {
    $reader = User::factory()->reader()->create();

    $this->withSession(['url.intended' => route('dashboard')]);

    $response = $this->post(route('login.store'), [
        'email' => $reader->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/');
});

test('users with two factor enabled are redirected to two factor challenge', function (): void {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function (): void {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

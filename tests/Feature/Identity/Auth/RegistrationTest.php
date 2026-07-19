<?php

use App\Domain\Community\Mail\SubscriberConfirmationMail;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

test('registration attempts are throttled per ip', function (): void {
    foreach (range(1, 10) as $ignored) {
        $this->post('/register', [])->assertStatus(302);
    }

    $this->post('/register', [])->assertStatus(429);
});

test('registration screen is available', function (): void {
    $response = $this->get('/register');

    $response->assertOk();
    expect(Route::has('register'))->toBeTrue()
        ->and(Route::has('register.store'))->toBeTrue();
});

test('readers can register with a fixed public display name', function (): void {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Moon Reader',
        'email' => 'READER@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'return_to' => '/posts/welcome',
    ]);

    $reader = User::query()->sole();

    $response->assertRedirect('/posts/welcome');
    $this->assertAuthenticatedAs($reader);
    expect($reader->name)->toBe('Moon Reader')
        ->and($reader->email)->toBe('reader@example.com')
        ->and($reader->role)->toBe(UserRole::Reader)
        ->and($reader->email_verified_at)->toBeNull();
    Notification::assertSentTo($reader, VerifyEmail::class);
});

test('reader registration rejects unsafe and author portal return paths', function (string $returnTo): void {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Moon Reader',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_confirmation' => 'password',
        'return_to' => $returnTo,
    ]);

    $response->assertRedirect('/');
})->with([
    'external origin' => 'https://example.org/posts/a',
    'dashboard' => '/dashboard',
    'settings' => '/settings/profile',
    'confirmation token' => '/newsletter/confirm/'.str_repeat('a', 64),
]);

test('reader registration preserves a safe public comments anchor', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Moon Reader',
        'email' => 'anchored@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'return_to' => '/posts/welcome#comments',
    ])->assertRedirect('/posts/welcome#comments');
});

test('newsletter registration remains optional and unchecked by default', function (): void {
    Mail::fake();
    Notification::fake();

    $this->post('/register', [
        'name' => 'No Newsletter',
        'email' => 'no-newsletter@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/');

    expect(NewsletterSubscriber::query()->exists())->toBeFalse();
    Mail::assertNothingQueued();
});

test('registration can start a separate newsletter confirmation', function (): void {
    Mail::fake();
    Notification::fake();

    $this->post('/register', [
        'name' => 'Newsletter Reader',
        'email' => 'newsletter@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'newsletter' => true,
    ])->assertRedirect('/');

    expect(NewsletterSubscriber::query()->count())->toBe(1);
    Mail::assertQueued(SubscriberConfirmationMail::class);
});

test('display names must contain between two and forty characters', function (string $name): void {
    $this->post('/register', [
        'name' => $name,
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('name');
})->with([
    'too short' => 'A',
    'too long' => str_repeat('A', 41),
]);

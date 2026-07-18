<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'name' => 'Jane Writer',
        'email' => 'jane@example.com',
    ]);
});

/** @return array<string, mixed> */
function profilePayload(array $overrides = []): array
{
    return [
        'name' => 'Jane Writer',
        'email' => 'jane@example.com',
        ...$overrides,
    ];
}

test('updating the profile saves author fields and generates a slug when blank', function (): void {
    $this->actingAs($this->user)
        ->patch(route('profile.update'), profilePayload([
            'bio' => 'Writes about software.',
            'social_links' => ['website' => 'https://example.com', 'x' => ''],
        ]))
        ->assertRedirect(route('profile.edit'));

    $this->user->refresh();
    expect($this->user->author_slug)->toBe('jane-writer')
        ->and($this->user->bio)->toBe('Writes about software.')
        ->and($this->user->social_links)->toBe(['website' => 'https://example.com']);
});

test('generated author slugs are suffixed on collision', function (): void {
    User::factory()->create(['author_slug' => 'jane-writer']);

    $this->actingAs($this->user)->patch(route('profile.update'), profilePayload());

    expect($this->user->refresh()->author_slug)->toBe('jane-writer-2');
});

test('a custom author slug is kept and must be unique', function (): void {
    User::factory()->create(['author_slug' => 'taken']);

    $this->actingAs($this->user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), profilePayload(['author_slug' => 'taken']))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('author_slug');

    $this->actingAs($this->user)
        ->patch(route('profile.update'), profilePayload(['author_slug' => 'my-pen-name']))
        ->assertRedirect(route('profile.edit'));

    expect($this->user->refresh()->author_slug)->toBe('my-pen-name');
});

test('social links must be valid urls with known keys', function (): void {
    $this->actingAs($this->user)
        ->patch(route('profile.update'), profilePayload([
            'social_links' => ['website' => 'not-a-url'],
        ]))
        ->assertSessionHasErrors('social_links.website');

    $this->actingAs($this->user)
        ->patch(route('profile.update'), profilePayload([
            'social_links' => ['myspace' => 'https://example.com'],
        ]))
        ->assertSessionHasErrors('social_links');
});

test('an avatar can be uploaded and replaced', function (): void {
    Storage::fake('public');

    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('first.jpg', 400, 400)])
        ->assertRedirect(route('profile.edit'));

    $firstPath = $this->user->refresh()->avatar_path;
    expect($firstPath)->not->toBeNull();
    Storage::disk('public')->assertExists($firstPath);

    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('second.png', 400, 400)])
        ->assertRedirect(route('profile.edit'));

    $secondPath = $this->user->refresh()->avatar_path;
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertExists($secondPath);
    Storage::disk('public')->assertMissing($firstPath);
});

test('avatar uploads must be images', function (): void {
    Storage::fake('public');

    $this->actingAs($this->user)
        ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('avatar');
});

test('the avatar can be removed', function (): void {
    Storage::fake('public');

    $this->actingAs($this->user)->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('face.jpg')]);
    $path = $this->user->refresh()->avatar_path;

    $this->actingAs($this->user)
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('profile.edit'));

    expect($this->user->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('guests cannot manage avatars', function (): void {
    $this->post(route('profile.avatar.store'))->assertRedirect(route('login'));
    $this->delete(route('profile.avatar.destroy'))->assertRedirect(route('login'));
});

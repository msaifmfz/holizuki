<?php

declare(strict_types=1);

use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

test('readers can view the public account page', function (): void {
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->get(route('reader.account.edit'))->assertOk();
});

test('administrators and guests cannot use the reader account page', function (): void {
    $administrator = User::factory()->create();

    $this->actingAs($administrator)->get(route('reader.account.edit'))->assertForbidden();
    $this->post('/logout');
    $this->get(route('reader.account.edit'))->assertRedirect(route('login'));
});

test('readers can change their password with the current password', function (): void {
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader)->put(route('reader.account.password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($reader)->put(route('reader.account.password.update'), [
        'current_password' => 'password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertRedirect();

    expect(Hash::check('new-secret-password', $reader->refresh()->password))->toBeTrue();
});

test('readers can delete their account and their comments cascade', function (): void {
    $reader = User::factory()->reader()->create();
    Comment::factory()->approved()->create(['user_id' => $reader->id]);

    $this->actingAs($reader)->delete(route('reader.account.destroy'), [
        'password' => 'password',
    ])->assertRedirect('/');

    $this->assertGuest();
    expect(User::query()->whereKey($reader->id)->exists())->toBeFalse()
        ->and(Comment::query()->where('user_id', $reader->id)->exists())->toBeFalse();
});

<?php

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

test('administrators are granted every gate ability', function (): void {
    $administrator = User::factory()->create();

    expect($administrator->role)->toBe(UserRole::Administrator)
        ->and($administrator->isAdministrator())->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('manage-blog'))->toBeTrue();
});

test('readers are not granted administrator abilities', function (): void {
    $reader = User::factory()->reader()->create();

    expect($reader->role)->toBe(UserRole::Reader)
        ->and($reader->isReader())->toBeTrue()
        ->and($reader->isAdministrator())->toBeFalse()
        ->and(Gate::forUser($reader)->allows('manage-blog'))->toBeFalse();
});

test('the database defaults new records to reader while factories remain administrator first', function (): void {
    $factoryAdministrator = User::factory()->create();
    $readerId = DB::table('users')->insertGetId([
        'name' => 'Database Reader',
        'email' => 'database-reader@example.com',
        'password' => Hash::make('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($factoryAdministrator->role)->toBe(UserRole::Administrator)
        ->and(User::query()->findOrFail($readerId)->role)->toBe(UserRole::Reader);
});

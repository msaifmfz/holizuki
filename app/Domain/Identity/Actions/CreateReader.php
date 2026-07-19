<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Concerns\PasswordValidationRules;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use UnexpectedValueException;

class CreateReader
{
    use PasswordValidationRules;

    /** @param array<array-key, mixed> $input */
    public function handle(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'newsletter' => ['sometimes', 'boolean'],
            'return_to' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ])->validate();

        $name = $validated['name'] ?? null;
        $email = $validated['email'] ?? null;
        $password = $validated['password'] ?? null;
        if (! is_string($name) || ! is_string($email) || ! is_string($password)) {
            throw new UnexpectedValueException('Validated reader credentials must be strings.');
        }

        $user = new User([
            'name' => Str::of($name)->trim()->toString(),
            'email' => Str::of($email)->trim()->lower()->toString(),
            'password' => $password,
        ]);
        $user->role = UserRole::Reader;
        $user->save();

        return $user;
    }
}

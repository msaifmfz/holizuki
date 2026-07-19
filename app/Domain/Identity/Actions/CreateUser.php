<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Concerns\PasswordValidationRules;
use App\Domain\Identity\Concerns\ProfileValidationRules;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateUser
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create an administrator account.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): User
    {
        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->email_verified_at = now();
        $user->role = UserRole::Administrator;
        $user->save();

        return $user;
    }
}

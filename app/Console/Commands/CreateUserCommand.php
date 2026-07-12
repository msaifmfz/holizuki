<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Signature('user:create')]
#[Description('Create an administrator account')]
class CreateUserCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CreateUser $createUser): int
    {
        $name = Str::of($this->promptForString('Name'))->trim()->toString();
        $email = Str::of($this->promptForString('Email address'))->trim()->lower()->toString();
        $password = $this->promptForString('Password', secret: true);
        $passwordConfirmation = $this->promptForString('Confirm password', secret: true);

        try {
            $user = $createUser->handle([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $this->components->info("Administrator {$user->email} created successfully.");

        return self::SUCCESS;
    }

    private function promptForString(string $question, bool $secret = false): string
    {
        $answer = $secret ? $this->secret($question) : $this->ask($question);

        return is_string($answer) ? $answer : '';
    }
}

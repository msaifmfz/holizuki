<?php

declare(strict_types=1);

namespace App\Http\Auth\Actions;

use App\Domain\Community\Actions\StartSubscription;
use App\Domain\Identity\Actions\CreateReader;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class RegisterReader implements CreatesNewUsers
{
    public function __construct(
        private readonly CreateReader $createReader,
        private readonly StartSubscription $startSubscription,
    ) {}

    /** @param array<array-key, mixed> $input */
    public function create(array $input): User
    {
        $this->ensureIsNotRateLimited();

        return DB::transaction(function () use ($input): User {
            $reader = $this->createReader->handle($input);

            if (in_array($input['newsletter'] ?? false, [true, 1, '1'], true)) {
                $this->startSubscription->handle(
                    email: $reader->email,
                    sourceMethod: 'registration',
                    sourceLocation: 'registration',
                );
            }

            return $reader;
        });
    }

    /**
     * Fortify exposes no limiter hook for registration and route caching
     * defeats runtime middleware attachment, so enforce the cap here.
     * Registration creates accounts and sends verification mail to
     * arbitrary addresses — without a cap it is an email-bomb vector.
     */
    private function ensureIsNotRateLimited(): void
    {
        $key = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new ThrottleRequestsException(
                'Too many registration attempts.',
                null,
                ['Retry-After' => (string) RateLimiter::availableIn($key)],
            );
        }

        RateLimiter::hit($key, 3600);
    }
}

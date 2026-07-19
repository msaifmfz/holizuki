<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AuthorActivityEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<AuthorActivityEvent> */
class AuthorActivityEventFactory extends Factory
{
    #[Override]
    protected $model = AuthorActivityEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'post_id' => null, 'event_id' => 'post_published', 'event_key' => (string) Str::uuid(), 'metadata' => null, 'occurred_at' => now(), 'expires_at' => now()->addMonths(24)];
    }
}

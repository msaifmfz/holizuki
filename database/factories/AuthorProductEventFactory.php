<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AuthorProductEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<AuthorProductEvent> */
class AuthorProductEventFactory extends Factory
{
    #[Override]
    protected $model = AuthorProductEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'event_id' => 'dashboard_open', 'deduplication_key' => (string) Str::uuid(), 'context_key' => null, 'metadata' => null, 'occurred_at' => now(), 'expires_at' => now()->addMonths(24)];
    }
}

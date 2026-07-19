<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inbox\Models\ContactSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ContactSubmission>
 */
class ContactSubmissionFactory extends Factory
{
    #[Override]
    protected $model = ContactSubmission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the submission has been read.
     */
    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}

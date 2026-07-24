<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<AssistantSession>
 */
class AssistantSessionFactory extends Factory
{
    #[Override]
    protected $model = AssistantSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'claude_session_id' => fake()->uuid(),
            'status' => AssistantSessionStatus::Idle,
            'active_run_token' => null,
            'resume_ready' => false,
            'conversation_model' => null,
            'outline_markdown' => null,
            'outline_ready' => false,
            'outline_version' => 0,
            'turn_started_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => AssistantSessionStatus::Running,
            'active_run_token' => fake()->uuid(),
            'turn_started_at' => now(),
        ]);
    }
}

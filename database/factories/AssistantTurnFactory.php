<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<AssistantTurn>
 */
class AssistantTurnFactory extends Factory
{
    #[Override]
    protected $model = AssistantTurn::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_session_id' => AssistantSession::factory(),
            'task_type' => AssistantTaskType::Chat,
            'status' => AssistantTurnStatus::Completed,
            'user_prompt' => fake()->sentence(),
            'assistant_message' => fake()->paragraph(),
            'context' => null,
            'snapshot_draft' => '',
            'snapshot_meta' => [],
            'cost_usd' => null,
            'duration_ms' => fake()->numberBetween(500, 60_000),
            'error' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => AssistantTurnStatus::Running,
            'assistant_message' => null,
            'duration_ms' => null,
        ]);
    }
}

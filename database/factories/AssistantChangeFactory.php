<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Assistant\Models\AssistantChange;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<AssistantChange>
 */
class AssistantChangeFactory extends Factory
{
    #[Override]
    protected $model = AssistantChange::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_turn_id' => AssistantTurn::factory(),
            'post_id' => Post::factory(),
            'type' => AssistantChangeType::Excerpt,
            'status' => AssistantChangeStatus::Proposed,
            'payload' => ['old' => fake()->sentence(), 'new' => fake()->sentence()],
            'base_lock_version' => 1,
            'decided_at' => null,
        ];
    }

    public function body(): static
    {
        return $this->state(fn (): array => [
            'type' => AssistantChangeType::Body,
            'payload' => [
                'old_blocks' => "Old paragraph.\n",
                'new_blocks' => "New paragraph.\n",
                'anchor_before' => null,
                'anchor_after' => null,
            ],
        ]);
    }
}

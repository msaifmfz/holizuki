<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<AuthoringArtifact> */
final class AuthoringArtifactFactory extends Factory
{
    #[Override]
    protected $model = AuthoringArtifact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'post_id' => Post::factory(),
            'assistant_authoring_task_id' => AuthoringTask::factory(),
            'parent_id' => null,
            'type' => AuthoringArtifactType::Plan,
            'group_id' => (string) Str::uuid(),
            'position' => 1,
            'base_lock_version' => 0,
            'payload' => [
                'title' => fake()->sentence(5),
                'angle' => fake()->sentence(),
                'sections' => [fake()->sentence(3), fake()->sentence(3)],
                'estimated_length' => 'medium',
                'source_ids' => [],
            ],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'type' => AuthoringArtifactType::Draft,
            'payload' => [
                'title' => fake()->sentence(5),
                'excerpt' => fake()->paragraph(),
                'body_markdown' => "## Introduction\n\n".fake()->paragraph(),
                'citations' => [],
            ],
        ]);
    }
}

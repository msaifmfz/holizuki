<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<AuthoringTask> */
final class AuthoringTaskFactory extends Factory
{
    #[Override]
    protected $model = AuthoringTask::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'request_id' => (string) Str::uuid(),
            'post_id' => Post::factory(),
            'requested_by_id' => User::factory(),
            'assistant_turn_id' => null,
            'kind' => AuthoringTaskKind::Plans,
            'status' => AuthoringTaskStatus::Completed,
            'base_lock_version' => 0,
            'input_hash' => hash('sha256', fake()->uuid()),
            'context' => [],
            'attempts' => 1,
            'error_code' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'status' => AuthoringTaskStatus::Queued,
            'attempts' => 0,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => AuthoringTaskStatus::Failed,
            'error_code' => 'execution_error',
            'error_message' => 'The task failed.',
            'finished_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Models\Comment;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<Comment> */
class CommentFactory extends Factory
{
    #[Override]
    protected $model = Comment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $body = fake()->paragraph();

        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory()->reader(),
            'body' => e($body),
            'body_hash' => hash('sha256', $body),
            'status' => CommentStatus::Pending,
            'edit_deadline_at' => now()->addMinutes(15),
            'moderated_by_id' => null,
            'moderation_reason' => null,
            'submitted_at' => now(),
            'approved_at' => null,
            'rejected_at' => null,
            'deleted_at' => null,
            'body_erased_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CommentStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => CommentStatus::Rejected,
            'rejected_at' => now(),
        ]);
    }
}

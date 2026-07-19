<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PostRevision>
 */
class PostRevisionFactory extends Factory
{
    #[Override]
    protected $model = PostRevision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'editor_id' => User::factory(),
            'revision_number' => 1,
            'event' => PostRevisionEvent::Saved,
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(),
            'excerpt' => fake()->paragraph(),
            'body' => ['type' => 'doc', 'content' => []],
            'featured_image_caption' => fake()->optional()->sentence(),
        ];
    }
}

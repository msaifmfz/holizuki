<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostRevisionEvent;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostRevision>
 */
class PostRevisionFactory extends Factory
{
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

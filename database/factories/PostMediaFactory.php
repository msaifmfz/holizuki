<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostMedia>
 */
class PostMediaFactory extends Factory
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
            'path' => 'posts/'.fake()->randomNumber().'/inline/'.fake()->uuid().'.webp',
            'width' => fake()->numberBetween(640, 2000),
            'height' => fake()->numberBetween(480, 1600),
        ];
    }
}

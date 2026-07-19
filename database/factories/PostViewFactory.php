<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostView>
 */
class PostViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory()->published(),
            'viewed_on' => fake()->dateTimeBetween('-30 days'),
            'visitor_hash' => hash('sha256', fake()->unique()->uuid()),
        ];
    }
}

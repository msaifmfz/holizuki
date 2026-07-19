<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Models\PostView;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PostView>
 */
class PostViewFactory extends Factory
{
    #[Override]
    protected $model = PostView::class;

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

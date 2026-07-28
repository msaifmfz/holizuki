<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Publishing\Models\PostDraft;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PostDraft>
 */
final class PostDraftFactory extends Factory
{
    #[Override]
    protected $model = PostDraft::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'slug' => fake()->unique()->slug(),
            'excerpt' => fake()->paragraph(),
            'body' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'text' => fake()->paragraph(),
                    ]],
                ]],
            ],
            'featured_image_path' => 'posts/featured-image.webp',
            'featured_image_alt' => fake()->sentence(),
            'featured_image_caption' => fake()->optional()->sentence(),
        ];
    }
}

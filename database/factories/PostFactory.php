<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\RebuildPostMetadata;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    #[Override]
    protected $model = Post::class;

    #[Override]
    public function configure(): static
    {
        return $this->afterCreating(function (Post $post): void {
            resolve(RebuildPostMetadata::class)->handle($post);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'updated_by_id' => null,
            'category_id' => Category::factory(),
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
            'status' => PostStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Published,
            'published_at' => now(),
            'slug_locked_at' => now(),
            'content_updated_at' => now(),
        ]);
    }

    public function withSeoOverrides(): static
    {
        return $this->state(fn (): array => [
            'seo_title' => fake()->sentence(5),
            'meta_description' => fake()->text(150),
            'canonical_url' => fake()->url(),
            'og_title' => fake()->sentence(5),
            'og_description' => fake()->text(150),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Draft,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function featured(): static
    {
        return $this->published()->state(fn (): array => [
            'featured_at' => now(),
        ]);
    }
}

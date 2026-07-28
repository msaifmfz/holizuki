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
            'snapshot' => [
                'version' => 1,
                'author_id' => null,
                'category_id' => null,
                'title' => fake()->sentence(),
                'slug' => fake()->unique()->slug(),
                'slug_is_manual' => false,
                'excerpt' => fake()->paragraph(),
                'body' => ['type' => 'doc', 'content' => []],
                'featured_image_path' => null,
                'featured_image_alt' => null,
                'featured_image_caption' => fake()->optional()->sentence(),
                'seo_title' => null,
                'meta_description' => null,
                'canonical_url' => null,
                'og_title' => null,
                'og_description' => null,
                'og_image_path' => null,
                'noindex' => false,
                'tags' => [],
            ],
        ];
    }
}

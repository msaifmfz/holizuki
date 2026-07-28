<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Publishing\Enums\PostMediaOrigin;
use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostMediaStatus;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PostMedia>
 */
class PostMediaFactory extends Factory
{
    #[Override]
    protected $model = PostMedia::class;

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
            'display_name' => fake()->words(3, true),
            'alt_text' => fake()->sentence(),
            'caption' => fake()->optional()->sentence(),
            'role' => PostMediaRole::Unassigned,
            'placement' => PostMediaPlacement::Unassigned,
            'origin' => PostMediaOrigin::InlineEditor,
            'status' => PostMediaStatus::Ready,
            'story_priority' => PostMediaStoryPriority::Required,
            'story_notes' => null,
        ];
    }

    public function libraryUpload(): static
    {
        return $this->state(fn (): array => [
            'path' => 'posts/'.fake()->randomNumber().'/media/'.fake()->uuid().'.webp',
            'origin' => PostMediaOrigin::LibraryUpload,
            'story_priority' => PostMediaStoryPriority::Optional,
        ]);
    }
}

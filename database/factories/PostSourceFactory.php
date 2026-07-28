<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostSourceKind;
use App\Domain\Publishing\Enums\PostSourceVisibility;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PostSource>
 */
class PostSourceFactory extends Factory
{
    #[Override]
    protected $model = PostSource::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'created_by_id' => User::factory(),
            'kind' => PostSourceKind::Note,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'visibility' => PostSourceVisibility::Private,
            'approved_at' => null,
        ];
    }

    public function url(): static
    {
        return $this->state(fn (): array => [
            'kind' => PostSourceKind::Url,
            'url' => fake()->url(),
            'content' => null,
        ]);
    }

    public function document(): static
    {
        return $this->state(fn (): array => [
            'kind' => PostSourceKind::Document,
            'content' => null,
            'path' => 'posts/'.fake()->randomNumber().'/sources/'.fake()->uuid().'.pdf',
            'original_name' => 'research.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1000, 50_000),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'approved_at' => now(),
        ]);
    }
}

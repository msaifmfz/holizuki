<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsUrlAlias;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsUrlAlias> */
class AnalyticsUrlAliasFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsUrlAlias::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $postId = fake()->unique()->numberBetween(1000, 999999);

        return ['path' => '/posts/'.fake()->unique()->slug(), 'post_id' => Post::factory(), 'content_key' => 'post:'.$postId, 'is_canonical' => true, 'retired_at' => null];
    }
}

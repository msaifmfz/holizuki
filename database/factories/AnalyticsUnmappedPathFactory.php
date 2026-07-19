<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsUnmappedPath;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsUnmappedPath> */
class AnalyticsUnmappedPathFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsUnmappedPath::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['path' => '/'.fake()->unique()->slug(), 'readers' => 1, 'page_views' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()];
    }
}

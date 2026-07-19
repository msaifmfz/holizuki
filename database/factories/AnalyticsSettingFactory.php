<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsSetting;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsSetting> */
class AnalyticsSettingFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['key' => fake()->unique()->slug(2), 'value' => ['enabled' => true], 'updated_by_id' => User::factory()];
    }
}

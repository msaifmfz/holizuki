<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsSnapshotPreparation;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<AnalyticsSnapshotPreparation> */
class AnalyticsSnapshotPreparationFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsSnapshotPreparation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['preparation_key' => hash('sha256', (string) Str::uuid()), 'requested_by_id' => User::factory(), 'scope_key' => 'site', 'starts_on' => now()->subDays(27), 'ends_on' => now(), 'status' => 'queued', 'sanitized_error' => null, 'completed_at' => null];
    }
}

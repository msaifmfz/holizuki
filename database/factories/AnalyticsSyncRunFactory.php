<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Enums\SyncStatus;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<AnalyticsSyncRun> */
class AnalyticsSyncRunFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsSyncRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['run_id' => (string) Str::uuid(), 'command' => 'test', 'status' => SyncStatus::Succeeded, 'starts_on' => now()->subDay(), 'ends_on' => now(), 'attempt' => 1, 'request_count' => 1, 'page_count' => 1, 'row_count' => 1, 'quota' => null, 'sanitized_error' => null, 'started_at' => now()->subMinute(), 'completed_at' => now()];
    }
}

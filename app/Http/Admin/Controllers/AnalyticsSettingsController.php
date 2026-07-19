<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Analytics\Models\AnalyticsSetting;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Identity\Models\User;
use App\Http\Admin\Requests\UpdateAnalyticsSettingsRequest;
use App\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = AnalyticsSetting::query()->get()->keyBy('key');
        $runs = AnalyticsSyncRun::query()
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->map(fn (AnalyticsSyncRun $run): array => [
                'id' => $run->run_id,
                'command' => $run->command,
                'status' => $run->status->value,
                'from' => $run->starts_on?->toDateString(),
                'to' => $run->ends_on?->toDateString(),
                'rows' => $run->row_count,
                'error' => $run->sanitized_error,
                'completedAt' => $run->completed_at?->toISOString(),
            ])->all();

        return Inertia::render('dashboard/analytics/settings', [
            'environment' => [
                'collectionEnabled' => config()->boolean('analytics.collection_enabled'),
                'dashboardEnabled' => config()->boolean('analytics.dashboard_enabled'),
                'measurementId' => $this->masked(config('analytics.measurement_id')),
                'propertyId' => $this->masked(config('analytics.property_id')),
                'streamId' => $this->masked(config('analytics.stream_id')),
                'credentialsConfigured' => is_string(config('analytics.service_account_base64')) && config('analytics.service_account_base64') !== '',
                'timezone' => config()->string('app.timezone'),
            ],
            'settings' => [
                'materialGapPoints' => $this->integerSetting(
                    $settings->get('material_gap_points'),
                    config()->integer('analytics.material_gap_points'),
                ),
                'showExploratoryInsights' => $this->booleanSetting(
                    $settings->get('show_exploratory_insights'),
                    true,
                ),
            ],
            'manualCorrections' => [
                'Disable Google Signals in GA Admin > Data collection and modification > Data collection.',
                'Disable enhanced-measurement outbound clicks, downloads, history, site search, forms, scroll, and video in the web stream.',
            ],
            'syncHealth' => [
                'lastSuccessfulRefresh' => AnalyticsSyncRun::query()
                    ->where('status', 'succeeded')
                    ->latest('completed_at')
                    ->value('completed_at'),
                'lastError' => AnalyticsSyncRun::query()
                    ->where('status', 'failed')
                    ->latest('completed_at')
                    ->value('sanitized_error'),
            ],
            'runs' => $runs,
        ]);
    }

    public function update(UpdateAnalyticsSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $settings = [
            'material_gap_points' => $request->integer('material_gap_points'),
            'show_exploratory_insights' => $request->boolean('show_exploratory_insights'),
        ];

        foreach ($settings as $key => $value) {
            AnalyticsSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => ['value' => $value], 'updated_by_id' => $user->id],
            );
        }

        return back()->with('success', 'Analytics display settings updated.');
    }

    private function masked(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return str_repeat('•', max(4, strlen($value) - 4)).substr($value, -4);
    }

    private function integerSetting(mixed $setting, int $default): int
    {
        if (! $setting instanceof AnalyticsSetting) {
            return $default;
        }

        $value = $setting->value['value'] ?? null;

        return is_int($value) ? $value : $default;
    }

    private function booleanSetting(mixed $setting, bool $default): bool
    {
        if (! $setting instanceof AnalyticsSetting) {
            return $default;
        }

        $value = $setting->value['value'] ?? null;

        return is_bool($value) ? $value : $default;
    }
}

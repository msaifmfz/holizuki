<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Gateways;

use App\Domain\Analytics\Exceptions\AnalyticsConfigurationException;
use Google\Analytics\Admin\V1alpha\Client\AnalyticsAdminServiceClient as V1alphaAnalyticsAdminServiceClient;
use Google\Analytics\Admin\V1beta\Client\AnalyticsAdminServiceClient;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use JsonException;

class GoogleAnalyticsClientFactory
{
    private ?BetaAnalyticsDataClient $reportingClient = null;

    private ?AnalyticsAdminServiceClient $adminClient = null;

    private ?V1alphaAnalyticsAdminServiceClient $adminDiagnosticsClient = null;

    public function reporting(): BetaAnalyticsDataClient
    {
        return $this->reportingClient ??= new BetaAnalyticsDataClient($this->options());
    }

    public function admin(): AnalyticsAdminServiceClient
    {
        return $this->adminClient ??= new AnalyticsAdminServiceClient($this->options());
    }

    public function adminDiagnostics(): V1alphaAnalyticsAdminServiceClient
    {
        return $this->adminDiagnosticsClient ??= new V1alphaAnalyticsAdminServiceClient($this->options());
    }

    /** @return array{credentials: array<string, mixed>, transport: string} */
    private function options(): array
    {
        $encoded = config('analytics.service_account_base64');

        if (! is_string($encoded) || $encoded === '') {
            throw new AnalyticsConfigurationException('Analytics service-account credentials are not configured.');
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new AnalyticsConfigurationException('Analytics service-account credentials are not valid base64.');
        }

        try {
            $credentials = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AnalyticsConfigurationException('Analytics service-account credentials are not valid JSON.', $exception->getCode(), previous: $exception);
        }

        if (! is_array($credentials)) {
            throw new AnalyticsConfigurationException('Analytics service-account credentials must decode to an object.');
        }

        $validatedCredentials = [];
        foreach ($credentials as $key => $value) {
            if (! is_string($key)) {
                throw new AnalyticsConfigurationException('Analytics service-account credentials must decode to a JSON object.');
            }

            $validatedCredentials[$key] = $value;
        }

        return ['credentials' => $validatedCredentials, 'transport' => 'rest'];
    }
}

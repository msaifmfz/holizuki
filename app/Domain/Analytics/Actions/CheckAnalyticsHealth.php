<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Contracts\AnalyticsAdminGateway;
use App\Domain\Analytics\Exceptions\AnalyticsConfigurationException;
use App\Domain\Analytics\ValueObjects\AnalyticsHealthResult;
use Throwable;

class CheckAnalyticsHealth
{
    public function __construct(private readonly AnalyticsAdminGateway $admin) {}

    public function handle(): AnalyticsHealthResult
    {
        $errors = $this->configurationErrors();

        if ($errors !== []) {
            return new AnalyticsHealthResult(false, $errors, [], []);
        }

        try {
            $property = $this->admin->inspect();
        } catch (Throwable $exception) {
            report($exception);

            return new AnalyticsHealthResult(false, [$this->sanitizedError($exception)], [], []);
        }

        if ($property->propertyId !== config('analytics.property_id')) {
            $errors[] = 'The configured GA property does not match the inspected property.';
        }

        if ($property->streamId !== config('analytics.stream_id')) {
            $errors[] = 'The configured GA web stream does not match the inspected stream.';
        }

        if ($property->measurementId !== config('analytics.measurement_id')) {
            $errors[] = 'The configured GA measurement ID does not match the web stream.';
        }

        if ($property->timezone !== config()->string('app.timezone')) {
            $errors[] = 'The GA property timezone must match the application timezone.';
        }

        $configuredDimensions = config()->array('analytics.custom_dimensions');
        $requiredDimensions = [];
        foreach ($configuredDimensions as $dimension) {
            if (! is_string($dimension)) {
                throw new AnalyticsConfigurationException('Analytics custom dimensions must contain only strings.');
            }

            $requiredDimensions[] = $dimension;
        }

        $missingDimensions = array_values(array_diff($requiredDimensions, $property->customDimensions));
        if ($missingDimensions !== []) {
            $errors[] = 'Missing GA custom dimensions: '.implode(', ', $missingDimensions).'.';
        }

        if (! in_array('sign_up', $property->keyEvents, true)) {
            $errors[] = 'The sign_up event is not configured as a GA key event.';
        }

        if ($property->retentionMonths !== 14) {
            $errors[] = 'GA event retention must be set to 14 months.';
        }

        if (! $property->googleSignalsDisabled) {
            $errors[] = 'Google Signals must be disabled for this property.';
        }

        if ($property->enhancedMeasurementStreamEnabled || $property->enabledEnhancedMeasurements !== []) {
            $enabledMeasurements = $property->enabledEnhancedMeasurements === []
                ? 'the enhanced-measurement stream'
                : implode(', ', $property->enabledEnhancedMeasurements);
            $errors[] = 'Automatic enhanced measurement must be disabled. Enabled: '.$enabledMeasurements.'.';
        }

        return new AnalyticsHealthResult(
            healthy: $errors === [],
            errors: $errors,
            warnings: $property->manualCorrections,
            manualCorrections: $property->manualCorrections,
            property: $property,
        );
    }

    /** @return list<string> */
    private function configurationErrors(): array
    {
        $errors = [];

        foreach (['measurement_id', 'property_id', 'stream_id', 'service_account_base64'] as $key) {
            if (! is_string(config('analytics.'.$key)) || config('analytics.'.$key) === '') {
                $errors[] = 'Analytics '.str_replace('_', ' ', $key).' is not configured.';
            }
        }

        if (! app()->isProduction() && config()->boolean('analytics.collection_enabled') && ! config()->boolean('analytics.allow_non_production_collection')) {
            $errors[] = 'Analytics collection is blocked outside production unless explicitly enabled for a non-production property.';
        }

        return $errors;
    }

    private function sanitizedError(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AnalyticsConfigurationException => $exception->getMessage(),
            default => 'Google Analytics could not be reached with the configured credentials.',
        };
    }
}

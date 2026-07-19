<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Gateways;

use App\Domain\Analytics\Contracts\AnalyticsAdminGateway;
use App\Domain\Analytics\Exceptions\AnalyticsConfigurationException;
use App\Domain\Analytics\ValueObjects\AnalyticsPropertyState;
use App\Domain\Analytics\ValueObjects\AnalyticsReconcileResult;
use Google\Analytics\Admin\V1alpha\Client\AnalyticsAdminServiceClient as V1alphaAnalyticsAdminServiceClient;
use Google\Analytics\Admin\V1alpha\EnhancedMeasurementSettings;
use Google\Analytics\Admin\V1alpha\GetEnhancedMeasurementSettingsRequest;
use Google\Analytics\Admin\V1alpha\GetGoogleSignalsSettingsRequest;
use Google\Analytics\Admin\V1alpha\GoogleSignalsState;
use Google\Analytics\Admin\V1beta\Client\AnalyticsAdminServiceClient;
use Google\Analytics\Admin\V1beta\CreateCustomDimensionRequest;
use Google\Analytics\Admin\V1beta\CreateKeyEventRequest;
use Google\Analytics\Admin\V1beta\CustomDimension;
use Google\Analytics\Admin\V1beta\CustomDimension\DimensionScope;
use Google\Analytics\Admin\V1beta\DataRetentionSettings\RetentionDuration;
use Google\Analytics\Admin\V1beta\GetDataRetentionSettingsRequest;
use Google\Analytics\Admin\V1beta\GetDataStreamRequest;
use Google\Analytics\Admin\V1beta\GetPropertyRequest;
use Google\Analytics\Admin\V1beta\KeyEvent;
use Google\Analytics\Admin\V1beta\ListCustomDimensionsRequest;
use Google\Analytics\Admin\V1beta\ListKeyEventsRequest;
use Google\Analytics\Admin\V1beta\UpdateDataRetentionSettingsRequest;
use Google\Protobuf\FieldMask;
use Illuminate\Support\Str;
use UnexpectedValueException;

class GoogleAnalyticsAdminGateway implements AnalyticsAdminGateway
{
    /** @var list<string> */
    private const array MANUAL_CORRECTIONS = [
        'Disable Google Signals in Admin > Data collection and modification > Data collection.',
        'Disable enhanced-measurement outbound clicks, downloads, history, site search, forms, scroll, and video in the web stream.',
    ];

    public function __construct(private readonly GoogleAnalyticsClientFactory $clients) {}

    public function inspect(): AnalyticsPropertyState
    {
        $client = $this->clients->admin();
        $propertyId = $this->propertyId();
        $streamId = $this->streamId();
        $propertyName = AnalyticsAdminServiceClient::propertyName($propertyId);
        $property = $client->getProperty(GetPropertyRequest::build($propertyName), $this->callOptions());
        $stream = $client->getDataStream(GetDataStreamRequest::build(
            AnalyticsAdminServiceClient::dataStreamName($propertyId, $streamId),
        ), $this->callOptions());
        $retention = $client->getDataRetentionSettings(GetDataRetentionSettingsRequest::build(
            AnalyticsAdminServiceClient::dataRetentionSettingsName($propertyId),
        ), $this->callOptions());
        $diagnostics = $this->privacyDiagnostics($propertyId, $streamId);

        return new AnalyticsPropertyState(
            propertyId: $propertyId,
            streamId: $streamId,
            measurementId: $stream->getWebStreamData()?->getMeasurementId() ?? '',
            timezone: $property->getTimeZone(),
            customDimensions: $this->customDimensions($client, $propertyName),
            keyEvents: $this->keyEvents($client, $propertyName),
            retentionMonths: $retention->getEventDataRetention() === RetentionDuration::FOURTEEN_MONTHS ? 14 : 2,
            manualCorrections: self::MANUAL_CORRECTIONS,
            googleSignalsDisabled: $diagnostics['googleSignalsDisabled'],
            enhancedMeasurementStreamEnabled: $diagnostics['enhancedMeasurementStreamEnabled'],
            enabledEnhancedMeasurements: $diagnostics['enabledEnhancedMeasurements'],
        );
    }

    public function reconcile(array $dimensions): AnalyticsReconcileResult
    {
        $client = $this->clients->admin();
        $propertyId = $this->propertyId();
        $propertyName = AnalyticsAdminServiceClient::propertyName($propertyId);
        $existingDimensions = $this->customDimensions($client, $propertyName);
        $createdDimensions = [];

        foreach ($dimensions as $dimension) {
            if (in_array($dimension, $existingDimensions, true)) {
                continue;
            }

            $client->createCustomDimension(CreateCustomDimensionRequest::build(
                $propertyName,
                new CustomDimension([
                    'parameter_name' => $dimension,
                    'display_name' => Str::headline($dimension),
                    'description' => 'Holizuki privacy-safe event dimension.',
                    'scope' => DimensionScope::EVENT,
                ]),
            ), $this->callOptions());
            $createdDimensions[] = $dimension;
        }

        $keyEventCreated = false;
        if (! in_array('sign_up', $this->keyEvents($client, $propertyName), true)) {
            $client->createKeyEvent(CreateKeyEventRequest::build(
                $propertyName,
                new KeyEvent(['event_name' => 'sign_up']),
            ), $this->callOptions());
            $keyEventCreated = true;
        }

        $retention = $client->getDataRetentionSettings(GetDataRetentionSettingsRequest::build(
            AnalyticsAdminServiceClient::dataRetentionSettingsName($propertyId),
        ), $this->callOptions());
        $retentionUpdated = false;

        if ($retention->getEventDataRetention() !== RetentionDuration::FOURTEEN_MONTHS) {
            $retention->setEventDataRetention(RetentionDuration::FOURTEEN_MONTHS);
            $client->updateDataRetentionSettings(UpdateDataRetentionSettingsRequest::build(
                $retention,
                new FieldMask(['paths' => ['event_data_retention']]),
            ), $this->callOptions());
            $retentionUpdated = true;
        }

        return new AnalyticsReconcileResult($createdDimensions, $keyEventCreated, $retentionUpdated);
    }

    /** @return list<string> */
    private function customDimensions(AnalyticsAdminServiceClient $client, string $propertyName): array
    {
        $dimensions = [];
        $response = $client->listCustomDimensions(ListCustomDimensionsRequest::build($propertyName), $this->callOptions());

        foreach ($response->iterateAllElements() as $dimension) {
            if (! $dimension instanceof CustomDimension) {
                throw new UnexpectedValueException('GA returned an invalid custom dimension.');
            }
            $dimensions[] = $dimension->getParameterName();
        }

        return $dimensions;
    }

    /** @return list<string> */
    private function keyEvents(AnalyticsAdminServiceClient $client, string $propertyName): array
    {
        $events = [];
        $response = $client->listKeyEvents(ListKeyEventsRequest::build($propertyName), $this->callOptions());

        foreach ($response->iterateAllElements() as $event) {
            if (! $event instanceof KeyEvent) {
                throw new UnexpectedValueException('GA returned an invalid key event.');
            }
            $events[] = $event->getEventName();
        }

        return $events;
    }

    /**
     * The alpha client is deliberately read-only here. Reconciliation only uses
     * beta mutations so these diagnostics can never alter privacy settings.
     *
     * @return array{
     *     googleSignalsDisabled: bool,
     *     enhancedMeasurementStreamEnabled: bool,
     *     enabledEnhancedMeasurements: list<string>
     * }
     */
    private function privacyDiagnostics(string $propertyId, string $streamId): array
    {
        $client = $this->clients->adminDiagnostics();
        $signals = $client->getGoogleSignalsSettings(GetGoogleSignalsSettingsRequest::build(
            V1alphaAnalyticsAdminServiceClient::googleSignalsSettingsName($propertyId),
        ), $this->callOptions());
        $enhancedMeasurement = $client->getEnhancedMeasurementSettings(
            GetEnhancedMeasurementSettingsRequest::build(
                V1alphaAnalyticsAdminServiceClient::enhancedMeasurementSettingsName($propertyId, $streamId),
            ),
            $this->callOptions(),
        );

        return [
            'googleSignalsDisabled' => $signals->getState() === GoogleSignalsState::GOOGLE_SIGNALS_DISABLED,
            'enhancedMeasurementStreamEnabled' => $enhancedMeasurement->getStreamEnabled(),
            'enabledEnhancedMeasurements' => $this->enabledEnhancedMeasurements($enhancedMeasurement),
        ];
    }

    /** @return list<string> */
    private function enabledEnhancedMeasurements(EnhancedMeasurementSettings $settings): array
    {
        $measurements = [
            'scrolls' => $settings->getScrollsEnabled(),
            'outbound clicks' => $settings->getOutboundClicksEnabled(),
            'site search' => $settings->getSiteSearchEnabled(),
            'video engagement' => $settings->getVideoEngagementEnabled(),
            'file downloads' => $settings->getFileDownloadsEnabled(),
            'history page changes' => $settings->getPageChangesEnabled(),
            'form interactions' => $settings->getFormInteractionsEnabled(),
        ];

        return array_keys(array_filter($measurements));
    }

    /** @return array{timeoutMillis: int} */
    private function callOptions(): array
    {
        return ['timeoutMillis' => config()->integer('analytics.request_timeout_seconds') * 1000];
    }

    private function propertyId(): string
    {
        $propertyId = config('analytics.property_id');

        if (! is_string($propertyId) || $propertyId === '') {
            throw new AnalyticsConfigurationException('Analytics property ID is not configured.');
        }

        return $propertyId;
    }

    private function streamId(): string
    {
        $streamId = config('analytics.stream_id');

        if (! is_string($streamId) || $streamId === '') {
            throw new AnalyticsConfigurationException('Analytics stream ID is not configured.');
        }

        return $streamId;
    }
}

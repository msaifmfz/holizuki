<?php

declare(strict_types=1);

return [
    'collection_enabled' => (bool) env('ANALYTICS_COLLECTION_ENABLED', false),
    'dashboard_enabled' => (bool) env('ANALYTICS_DASHBOARD_ENABLED', false),
    'allow_non_production_collection' => (bool) env('ANALYTICS_ALLOW_NON_PRODUCTION_COLLECTION', false),
    'measurement_id' => env('ANALYTICS_MEASUREMENT_ID'),
    'property_id' => env('ANALYTICS_PROPERTY_ID'),
    'stream_id' => env('ANALYTICS_STREAM_ID'),
    'service_account_base64' => env('ANALYTICS_SERVICE_ACCOUNT_BASE64'),
    'consent_version' => env('ANALYTICS_CONSENT_VERSION', '2026-07-19'),
    'consent_days' => 180,
    'request_timeout_seconds' => 20,
    'realtime_cache_seconds' => 60,
    'realtime_stale_seconds' => 600,
    'snapshot_cache_version' => 1,
    'material_gap_points' => 15,
    'medium_confidence_min_gap_points' => 15,
    'meaningful_reader_seconds' => 30,
    'meaningful_progress_percent' => 50,
    'custom_dimensions' => [
        'content_key',
        'category_slug',
        'word_count_band',
        'publication_age_band',
        'content_source',
        'content_location',
        'source_content_key',
    ],
];

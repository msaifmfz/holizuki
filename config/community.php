<?php

declare(strict_types=1);

$emailHashKey = env('COMMUNITY_EMAIL_HASH_KEY');

return [
    'consent_version' => env('COMMUNITY_CONSENT_VERSION', '2026-07-19'),
    'email_hash_key' => is_string($emailHashKey) && $emailHashKey !== ''
        ? $emailHashKey
        : config('app.key'),
    'confirmation_hours' => 48,
    'unconfirmed_retention_days' => 7,
    'rejected_comment_retention_days' => 90,
    'deleted_comment_body_retention_days' => 30,
    'comment_edit_minutes' => 15,
    'comment_duplicate_hours' => 24,
    'comment_max_length' => 2000,
    'sharing_methods' => [
        'native',
        'copy',
        'email',
        'x',
        'linkedin',
        'facebook',
        'reddit',
        'bluesky',
        'whatsapp',
    ],
];

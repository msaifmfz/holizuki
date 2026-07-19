<?php

declare(strict_types=1);

return [
    'revision_limit' => (int) env('BLOG_REVISION_LIMIT', 50),
    'maximum_featured_posts' => 3,
    'popular_window_days' => 30,
    'view_retention_days' => 90,

    /*
     * Whether AI crawlers may fetch public content. The policy is deliberate
     * rather than accidental: 'allow' welcomes AI search and training bots,
     * 'block' disallows the bots listed below while leaving traditional
     * search engines untouched.
     */
    'ai_crawlers' => [
        'policy' => env('BLOG_AI_CRAWLER_POLICY', 'allow'),
        'bots' => [
            'GPTBot',
            'OAI-SearchBot',
            'ChatGPT-User',
            'ClaudeBot',
            'Claude-SearchBot',
            'Claude-User',
            'anthropic-ai',
            'Google-Extended',
            'CCBot',
            'PerplexityBot',
            'Bytespider',
            'Applebot-Extended',
            'meta-externalagent',
        ],
    ],
];

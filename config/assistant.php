<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Writing Assistant
    |--------------------------------------------------------------------------
    |
    | The assistant drives a headless Claude Code CLI against a per-post
    | workspace directory. Authentication uses the site owner's Claude
    | subscription: either a CLAUDE_CODE_OAUTH_TOKEN environment variable or
    | credentials stored under the persisted assistant home directory.
    |
    */

    'enabled' => env('ASSISTANT_ENABLED', true),

    'binary' => env('ASSISTANT_CLAUDE_BIN', 'claude'),

    // Becomes HOME for the CLI process so ~/.claude (sessions, credentials)
    // lives on the persisted storage volume.
    'home' => env('ASSISTANT_HOME', storage_path('app/assistant/home')),

    'workspaces' => env('ASSISTANT_WORKSPACES', storage_path('app/ai-workspaces')),

    'models' => [
        'chat' => env('ASSISTANT_MODEL_CHAT', 'claude-opus-4-8'),
        'transform' => env('ASSISTANT_MODEL_TRANSFORM', 'claude-sonnet-4-5'),
        'metadata' => env('ASSISTANT_MODEL_METADATA', 'claude-haiku-4-5'),
        'outline' => env('ASSISTANT_MODEL_OUTLINE', 'claude-opus-4-8'),
        'images' => env('ASSISTANT_MODEL_IMAGES', 'claude-sonnet-4-5'),
    ],

    'allowed_tools' => ['Read', 'Write', 'Edit', 'Glob', 'Grep'],

    'disallowed_tools' => ['Bash', 'WebFetch', 'WebSearch', 'Task', 'NotebookEdit'],

    'max_turns' => [
        'chat' => 25,
        'transform' => 8,
        'metadata' => 8,
        'outline' => 25,
        'images' => 15,
    ],

    // Hard ceiling for a single agent turn, in seconds.
    'turn_timeout' => (int) env('ASSISTANT_TURN_TIMEOUT', 300),

    // Per-author cap on how many agent turns can be started per minute across
    // all posts (the per-post busy guard already serializes a single post).
    'rate_limit' => (int) env('ASSISTANT_RATE_LIMIT', 30),

    'prune_after_days' => (int) env('ASSISTANT_PRUNE_AFTER_DAYS', 30),

];

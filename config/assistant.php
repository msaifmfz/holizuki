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
    | subscription through a CLAUDE_CODE_OAUTH_TOKEN. The child process is
    | launched with a deliberately minimal environment so application and
    | infrastructure secrets cannot leak into the agent runtime.
    |
    */

    'enabled' => env('ASSISTANT_ENABLED', true),

    'binary' => env('ASSISTANT_CLAUDE_BIN', 'claude'),

    'claude_version' => env('ASSISTANT_CLAUDE_VERSION', '2.1.218'),

    'oauth_token' => env('CLAUDE_CODE_OAUTH_TOKEN'),

    // Becomes HOME for the CLI process so session transcripts live on the
    // persisted storage volume. Authentication itself comes only from OAuth.
    'home' => env('ASSISTANT_HOME', storage_path('app/assistant/home')),

    'workspaces' => env('ASSISTANT_WORKSPACES', storage_path('app/ai-workspaces')),

    'models' => [
        'chat' => env('ASSISTANT_MODEL_CHAT', 'claude-sonnet-4-6'),
        'transform' => env('ASSISTANT_MODEL_TRANSFORM', 'claude-sonnet-4-6'),
        'metadata' => env('ASSISTANT_MODEL_METADATA', 'claude-haiku-4-5'),
        'outline' => env('ASSISTANT_MODEL_OUTLINE', 'claude-sonnet-4-6'),
        'images' => env('ASSISTANT_MODEL_IMAGES', 'claude-sonnet-4-6'),
        'plans' => env('ASSISTANT_MODEL_PLANS', 'claude-sonnet-4-6'),
        'draft' => env('ASSISTANT_MODEL_DRAFT', 'claude-sonnet-4-6'),
        'research' => env('ASSISTANT_MODEL_RESEARCH', 'claude-sonnet-4-6'),
    ],

    'disallowed_tools' => ['Bash', 'WebFetch', 'WebSearch', 'Task', 'NotebookEdit'],

    'max_turns' => [
        'chat' => (int) env('ASSISTANT_MAX_TURNS_CHAT', 12),
        'transform' => (int) env('ASSISTANT_MAX_TURNS_TRANSFORM', 6),
        'metadata' => (int) env('ASSISTANT_MAX_TURNS_METADATA', 6),
        'outline' => (int) env('ASSISTANT_MAX_TURNS_OUTLINE', 12),
        'images' => (int) env('ASSISTANT_MAX_TURNS_IMAGES', 8),
        'plans' => (int) env('ASSISTANT_MAX_TURNS_PLANS', 8),
        'draft' => (int) env('ASSISTANT_MAX_TURNS_DRAFT', 12),
        'research' => (int) env('ASSISTANT_MAX_TURNS_RESEARCH', 12),
    ],

    'turn_timeout' => (int) env('ASSISTANT_TURN_TIMEOUT', 300),
    'startup_timeout' => (int) env('ASSISTANT_STARTUP_TIMEOUT', 30),
    'idle_timeout' => (int) env('ASSISTANT_IDLE_TIMEOUT', 150),
    'termination_grace' => (int) env('ASSISTANT_TERMINATION_GRACE', 3),
    'heartbeat_interval' => (int) env('ASSISTANT_HEARTBEAT_INTERVAL', 10),
    'cancellation_poll_interval' => (int) env('ASSISTANT_CANCELLATION_POLL_INTERVAL', 1),

    'rate_limit' => (int) env('ASSISTANT_RATE_LIMIT', 6),
    'global_lock_key' => env('ASSISTANT_GLOBAL_LOCK_KEY', 'assistant:global-run'),
    'global_lock_seconds' => (int) env('ASSISTANT_GLOBAL_LOCK_SECONDS', 600),
    'execution_grace_seconds' => (int) env('ASSISTANT_EXECUTION_GRACE_SECONDS', 120),
    'workspace_lock_key' => env('ASSISTANT_WORKSPACE_LOCK_KEY', 'assistant:workspace'),
    'workspace_lock_seconds' => (int) env('ASSISTANT_WORKSPACE_LOCK_SECONDS', 600),

    'limits' => [
        'stdout_bytes' => (int) env('ASSISTANT_MAX_STDOUT_BYTES', 16 * 1024 * 1024),
        'stderr_bytes' => (int) env('ASSISTANT_MAX_STDERR_BYTES', 64 * 1024),
        'line_bytes' => (int) env('ASSISTANT_MAX_LINE_BYTES', 4 * 1024 * 1024),
        'event_text_bytes' => (int) env('ASSISTANT_MAX_EVENT_TEXT_BYTES', 128 * 1024),
        'narration_bytes' => (int) env('ASSISTANT_MAX_NARRATION_BYTES', 128 * 1024),
        'draft_bytes' => (int) env('ASSISTANT_MAX_DRAFT_BYTES', 2 * 1024 * 1024),
        'meta_bytes' => (int) env('ASSISTANT_MAX_META_BYTES', 64 * 1024),
        'outline_bytes' => (int) env('ASSISTANT_MAX_OUTLINE_BYTES', 64 * 1024),
        'diff_blocks' => (int) env('ASSISTANT_MAX_DIFF_BLOCKS', 500),
        // The 20 photos a post may hold, plus its featured image.
        'image_count' => (int) env('ASSISTANT_MAX_IMAGE_COUNT', 21),
        'image_bytes' => (int) env('ASSISTANT_MAX_IMAGE_BYTES', 100 * 1024 * 1024),
        'source_bytes' => (int) env('ASSISTANT_MAX_SOURCE_BYTES', 100 * 1024 * 1024),
        // A generated article body. Well past any realistic long-form draft,
        // and low enough that the structural checks over it stay cheap.
        'draft_markdown_bytes' => (int) env('ASSISTANT_MAX_DRAFT_MARKDOWN_BYTES', 256 * 1024),
        'authoring_output_bytes' => (int) env('ASSISTANT_MAX_AUTHORING_OUTPUT_BYTES', 4 * 1024 * 1024),
    ],

    'authoring_job_timeout' => (int) env('ASSISTANT_AUTHORING_JOB_TIMEOUT', 360),

    'process' => [
        'path' => env('ASSISTANT_PROCESS_PATH', '/usr/local/bin:/usr/bin:/bin'),
        'tmpdir' => env('ASSISTANT_PROCESS_TMPDIR', sys_get_temp_dir()),
        'locale' => env('ASSISTANT_PROCESS_LOCALE', 'C.UTF-8'),
        'api_timeout_ms' => (int) env('ASSISTANT_API_TIMEOUT_MS', 120_000),
        'max_retries' => (int) env('ASSISTANT_MAX_RETRIES', 2),
        'poll_microseconds' => (int) env('ASSISTANT_PROCESS_POLL_MICROSECONDS', 10_000),
    ],

    'availability' => [
        'cache_key' => env('ASSISTANT_AVAILABILITY_CACHE_KEY', 'assistant:availability'),
        'rate_limit_fallback_seconds' => (int) env('ASSISTANT_RATE_LIMIT_COOLDOWN', 900),
        'transient_cooldown_seconds' => (int) env('ASSISTANT_TRANSIENT_COOLDOWN', 60),
    ],

    'prune_after_days' => (int) env('ASSISTANT_PRUNE_AFTER_DAYS', 30),

];

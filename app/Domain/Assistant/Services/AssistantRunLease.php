<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use Illuminate\Support\Facades\Cache;

class AssistantRunLease
{
    public function acquire(string $runToken): bool
    {
        return Cache::lock(
            $this->key(),
            $this->durationSeconds(),
            $runToken,
        )->get() === true;
    }

    public function release(string $runToken): void
    {
        Cache::restoreLock($this->key(), $runToken)->release();
    }

    public function durationSeconds(): int
    {
        return max(
            config()->integer('assistant.global_lock_seconds', 600),
            config()->integer('assistant.turn_timeout', 300)
                + config()->integer('assistant.execution_grace_seconds', 120),
        );
    }

    private function key(): string
    {
        return config()->string('assistant.global_lock_key', 'assistant:global-run');
    }
}

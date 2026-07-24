<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\Cache;

class AssistantWorkspaceLease
{
    public function acquire(Post|int $post, string $ownerToken): bool
    {
        return Cache::lock(
            $this->key($post),
            config()->integer('assistant.workspace_lock_seconds', 600),
            $ownerToken,
        )->get() === true;
    }

    public function release(Post|int $post, string $ownerToken): void
    {
        Cache::restoreLock($this->key($post), $ownerToken)->release();
    }

    private function key(Post|int $post): string
    {
        $prefix = config()->string('assistant.workspace_lock_key', 'assistant:workspace');

        return $prefix.':'.($post instanceof Post ? $post->id : $post);
    }
}

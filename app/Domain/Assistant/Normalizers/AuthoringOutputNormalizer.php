<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Normalizers;

use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\ValueObjects\AssistantOutput;
use App\Domain\Assistant\ValueObjects\AuthoringArtifactPayload;
use App\Domain\Publishing\Models\Post;

/**
 * Turns one kind of raw assistant output into the artifacts it may be stored
 * as, rejecting anything that does not match what the task actually asked for.
 *
 * Implementations validate; they never persist.
 */
interface AuthoringOutputNormalizer
{
    /** @return list<AuthoringArtifactPayload> */
    public function normalize(AuthoringTask $task, Post $post, AssistantOutput $output): array;
}

<?php

declare(strict_types=1);

namespace App\Domain\Community\Events;

final readonly class CommentApproved
{
    public function __construct(
        public int $commentId,
        public int $postId,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Community\Enums;

enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Deleted = 'deleted';
}

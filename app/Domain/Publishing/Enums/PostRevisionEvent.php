<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostRevisionEvent: string
{
    case Saved = 'saved';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Unpublished = 'unpublished';
    case ImageChanged = 'image_changed';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ConflictOverwrite = 'conflict_overwrite';
}

<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum PostMediaOrigin: string
{
    case InlineEditor = 'inline_editor';
    case LibraryUpload = 'library_upload';
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PostEditConflictException extends RuntimeException
{
    public function __construct(private readonly Post $post)
    {
        parent::__construct('This post was changed by another editor.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'conflict' => [
                'lock_version' => $this->post->lock_version,
                'updated_at' => $this->post->updated_at?->toISOString(),
                'last_editor' => $this->post->lastEditor?->name,
            ],
        ], 409);
    }
}

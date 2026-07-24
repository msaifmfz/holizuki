<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AssistantBusyException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $retryAfter,
    ) {
        parent::__construct($message);
    }

    public static function forPost(): self
    {
        return new self('The assistant is already working on this post.', 'post_busy', 5);
    }

    public static function globally(): self
    {
        return new self('The assistant is already handling another request.', 'assistant_busy', 5);
    }

    public function render(Request $request): JsonResponse
    {
        return response()
            ->json([
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'retry_at' => null,
            ], 429)
            ->header('Retry-After', (string) $this->retryAfter);
    }
}

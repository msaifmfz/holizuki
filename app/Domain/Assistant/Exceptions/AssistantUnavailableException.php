<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AssistantUnavailableException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly ?string $retryAt,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    /**
     * @param array{
     *     available: bool,
     *     status: string,
     *     code: string|null,
     *     message: string|null,
     *     retry_at: string|null
     * } $state
     */
    public static function fromState(array $state): self
    {
        return new self(
            errorCode: $state['code'] ?? 'assistant_unavailable',
            message: $state['message'] ?? 'The assistant is unavailable.',
            retryAt: $state['retry_at'],
            status: $state['status'] === 'cooldown' ? 429 : 503,
        );
    }

    public function render(Request $request): JsonResponse
    {
        $response = response()->json([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'retry_at' => $this->retryAt,
        ], $this->status);

        if ($this->status === 429 && $this->retryAt !== null) {
            $retryAfter = max(1, (int) ceil(now()->diffInSeconds($this->retryAt, false)));
            $response->header('Retry-After', (string) $retryAfter);
        }

        return $response;
    }
}

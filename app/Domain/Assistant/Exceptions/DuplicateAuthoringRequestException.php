<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use App\Domain\Assistant\Models\AuthoringTask;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class DuplicateAuthoringRequestException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $taskId)
    {
        parent::__construct('This request ID was already used for a different guided authoring request.');
    }

    public static function forTask(AuthoringTask $task): self
    {
        return new self($task->public_id);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'duplicate_authoring_request',
            'message' => $this->getMessage(),
            'task_id' => $this->taskId,
        ], 409);
    }
}

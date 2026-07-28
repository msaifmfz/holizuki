<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use App\Domain\Assistant\Models\AuthoringTask;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class AuthoringTaskBusyException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $taskId)
    {
        parent::__construct('Another guided authoring task is already queued or running for this post.');
    }

    public static function forTask(AuthoringTask $task): self
    {
        return new self($task->public_id);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'authoring_task_busy',
            'message' => $this->getMessage(),
            'task_id' => $this->taskId,
        ], 409);
    }
}

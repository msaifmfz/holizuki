<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Assistant\Models\AuthoringTask;
use Illuminate\Support\Facades\DB;

final class FailAuthoringTask
{
    public function handle(AuthoringTask $task, string $errorCode, string $errorMessage): bool
    {
        return DB::transaction(function () use ($task, $errorCode, $errorMessage): bool {
            /** @var AuthoringTask $lockedTask */
            $lockedTask = AuthoringTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if (! $lockedTask->status->isActive()) {
                return false;
            }

            $lockedTask->forceFill([
                'status' => AuthoringTaskStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'finished_at' => now(),
            ])->save();
            $task->setRawAttributes($lockedTask->getAttributes(), true);

            return true;
        });
    }
}

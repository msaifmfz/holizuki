<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Models\AuthoringTask;
use Illuminate\Support\Facades\DB;
use LogicException;

final class StartAuthoringTask
{
    public function handle(AuthoringTask $task, AssistantTurn $turn): AuthoringTask
    {
        return DB::transaction(function () use ($task, $turn): AuthoringTask {
            /** @var AuthoringTask $lockedTask */
            $lockedTask = AuthoringTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($lockedTask->status !== AuthoringTaskStatus::Queued) {
                throw new LogicException('Only queued authoring tasks may start.');
            }

            $lockedTask->forceFill([
                'status' => AuthoringTaskStatus::Running,
                'assistant_turn_id' => $turn->id,
                'attempts' => $lockedTask->attempts + 1,
                'error_code' => null,
                'error_message' => null,
                'started_at' => now(),
                'finished_at' => null,
            ])->save();

            $task->setRawAttributes($lockedTask->getAttributes(), true);

            return $task;
        });
    }
}

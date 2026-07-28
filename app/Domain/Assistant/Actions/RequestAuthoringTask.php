<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Assistant\Exceptions\AuthoringTaskBusyException;
use App\Domain\Assistant\Exceptions\DuplicateAuthoringRequestException;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\ValueObjects\AuthoringTaskRequestResult;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RequestAuthoringTask
{
    /** @param array<string, mixed> $context */
    public function handle(
        Post $post,
        User $requester,
        AuthoringTaskKind $kind,
        string $requestId,
        string $inputHash,
        array $context,
    ): AuthoringTaskRequestResult {
        try {
            return DB::transaction(function () use (
                $post,
                $requester,
                $kind,
                $requestId,
                $inputHash,
                $context,
            ): AuthoringTaskRequestResult {
                /** @var Post $lockedPost */
                $lockedPost = Post::query()->withTrashed()->whereKey($post->id)->lockForUpdate()->firstOrFail();
                $requestTask = AuthoringTask::query()->where('request_id', $requestId)->first();

                if ($requestTask instanceof AuthoringTask) {
                    if (
                        $requestTask->post_id !== $lockedPost->id
                        || $requestTask->kind !== $kind
                        || $requestTask->input_hash !== $inputHash
                    ) {
                        throw DuplicateAuthoringRequestException::forTask($requestTask);
                    }

                    return $this->reuse($requestTask, cached: true);
                }

                $activeTask = AuthoringTask::query()
                    ->where('post_id', $lockedPost->id)
                    ->active()
                    ->first();

                if ($activeTask instanceof AuthoringTask) {
                    throw AuthoringTaskBusyException::forTask($activeTask);
                }

                $task = AuthoringTask::create([
                    'public_id' => (string) Str::uuid(),
                    'request_id' => $requestId,
                    'post_id' => $lockedPost->id,
                    'requested_by_id' => $requester->id,
                    'kind' => $kind,
                    'status' => AuthoringTaskStatus::Queued,
                    'base_lock_version' => $lockedPost->lock_version,
                    'input_hash' => $inputHash,
                    'context' => $context,
                ]);

                return new AuthoringTaskRequestResult(
                    task: $task,
                    shouldDispatch: true,
                    cached: false,
                    resumed: false,
                );
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Two identical requests raced past the read above. Whichever lost
            // still deserves the winner's answer rather than a server error.
            $requestTask = AuthoringTask::query()->where('request_id', $requestId)->first();

            if (
                $requestTask instanceof AuthoringTask
                && $requestTask->post_id === $post->id
                && $requestTask->kind === $kind
                && $requestTask->input_hash === $inputHash
            ) {
                return $this->reuse($requestTask, cached: true);
            }

            $activeTask = AuthoringTask::query()
                ->where('post_id', $post->id)
                ->active()
                ->first();

            if ($activeTask instanceof AuthoringTask) {
                throw AuthoringTaskBusyException::forTask($activeTask);
            }

            throw $exception;
        }
    }

    private function reuse(AuthoringTask $task, bool $cached): AuthoringTaskRequestResult
    {
        if ($task->status !== AuthoringTaskStatus::Failed) {
            return new AuthoringTaskRequestResult(
                task: $task,
                shouldDispatch: false,
                cached: $cached,
                resumed: false,
            );
        }

        $task->forceFill([
            'status' => AuthoringTaskStatus::Queued,
            'assistant_turn_id' => null,
            'error_code' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        return new AuthoringTaskRequestResult(
            task: $task,
            shouldDispatch: true,
            cached: false,
            resumed: true,
        );
    }
}

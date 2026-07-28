<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Jobs;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Actions\FailAuthoringTask;
use App\Domain\Assistant\Actions\StartAssistantTurn;
use App\Domain\Assistant\Actions\StartAuthoringTask;
use App\Domain\Assistant\Actions\StoreAuthoringArtifacts;
use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Assistant\Exceptions\AssistantBusyException;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Assistant\Services\AgentRequestFactory;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\AuthoringOutputManager;
use App\Domain\Assistant\Services\AuthoringPromptFactory;
use App\Domain\Assistant\Services\AuthoringSourceWorkspace;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Backoff([5, 15, 30])]
#[FailOnTimeout]
#[Tries(50)]
final class RunAuthoringTask implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(
        public readonly int $taskId,
        public readonly int $postId,
    ) {
        $this->timeout = config()->integer('assistant.authoring_job_timeout', 360);
        // Its own connection so this job's long retry_after does not hold up
        // reclaiming every other queue's jobs.
        $this->onConnection('assistant');
        $this->onQueue('assistant');
        $this->afterCommit();
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('assistant-authoring-post:'.$this->postId)
                ->releaseAfter(15)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(
        AssistantAvailability $availability,
        StartAssistantTurn $startTurn,
        StartAuthoringTask $startTask,
        AuthoringSourceWorkspace $sources,
        AuthoringOutputManager $output,
        AuthoringPromptFactory $prompts,
        AgentRequestFactory $agentRequests,
        AgentRunner $runner,
        CompleteAssistantTurn $completeTurn,
        StoreAuthoringArtifacts $storeArtifacts,
        FailAssistantTurn $failTurn,
        FailAuthoringTask $failTask,
        AssistantRunLease $runLease,
        WorkspaceManager $workspace,
    ): void {
        $task = AuthoringTask::query()->find($this->taskId);

        if (! $task instanceof AuthoringTask || $task->status !== AuthoringTaskStatus::Queued) {
            return;
        }

        /** @var Post $post */
        $post = $task->post()->firstOrFail();
        $turn = null;

        try {
            if (
                $task->kind === AuthoringTaskKind::Research
                && ! PostAuthoringJourney::query()
                    ->whereBelongsTo($post)
                    ->where('web_research_enabled', true)
                    ->exists()
            ) {
                $failTask->handle(
                    $task,
                    'web_research_disabled',
                    'Web research was disabled before this task started.',
                );

                return;
            }

            $availability->ensureCanStart();
            $prompt = $prompts->build($task);
            $turn = $startTurn->handle(
                $post,
                $task->kind->assistantTaskType(),
                'Queued guided authoring '.$task->kind->value.' task.',
                (string) Str::uuid(),
                [
                    'authoring_task_id' => $task->public_id,
                    'authoring_media_ids' => array_values(array_filter(
                        is_array($task->context['media_ids'] ?? null)
                            ? $task->context['media_ids']
                            : [],
                        is_int(...),
                    )),
                    'source_domains' => $task->context['source_domains'] ?? [],
                ],
            );
            $startTask->handle($task, $turn);
            $sources->materialize($post, $turn, $task);
            $output->prepare($post, $turn);

            [$result, $error, $narration] = $this->runAgent(
                $runner,
                $agentRequests->forTurn($post, $turn, $prompt),
            );
            $terminalError = $result?->isError === true ? $result : $error;

            if ($result === null || $result->isError || $terminalError instanceof AgentEvent) {
                $terminalError ??= AgentEvent::error(
                    'The assistant did not finish this request.',
                    AgentFailureCode::ProtocolError->value,
                );
                $failureCode = AgentFailureCode::tryFrom((string) $terminalError->code)
                    ?? AgentFailureCode::Unknown;
                $failTurn->handle(
                    $turn,
                    $failureCode->message(),
                    $failureCode === AgentFailureCode::Cancelled
                        ? AssistantTurnStatus::Cancelled
                        : AssistantTurnStatus::Failed,
                    $failureCode,
                );
                $failTask->handle($task, $failureCode->value, $failureCode->message());

                return;
            }

            $artifactOutput = $output->read($post, $turn);
            $completeTurn->handle(
                $turn,
                $post,
                $narration === [] ? $result->text : implode("\n\n", $narration),
                $result->costUsd,
                $result->durationMs,
                $result->subtype,
                $result->stopReason,
                $result->usage,
            );
            $turn->refresh();

            if ($turn->status !== AssistantTurnStatus::Completed) {
                $failTask->handle(
                    $task,
                    $turn->error_code ?? 'invalid_assistant_output',
                    $turn->error_message ?? 'The assistant output could not be reviewed safely.',
                );

                return;
            }

            $storeArtifacts->handle($task, $post, $artifactOutput);
        } catch (AssistantBusyException) {
            if ($task->fresh()?->status === AuthoringTaskStatus::Queued) {
                $this->release(15);

                return;
            }

            $failTask->handle($task, 'assistant_busy', 'The assistant was busy before this task could start.');
        } catch (Throwable $exception) {
            report($exception);

            if ($turn instanceof AssistantTurn && $turn->fresh()?->status?->isActive() === true) {
                $failTurn->handle(
                    $turn,
                    'The queued guided authoring task could not be completed.',
                    errorCode: 'execution_error',
                );
            }

            $failTask->handle(
                $task,
                'execution_error',
                'The guided authoring task could not be completed. You can retry it safely.',
            );
        } finally {
            if ($turn instanceof AssistantTurn) {
                try {
                    $workspace->destroyRun($post, $turn->run_token);
                } catch (Throwable $exception) {
                    report($exception);
                }

                try {
                    $runLease->release($turn->run_token);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        AuthoringTask::query()
            ->whereKey($this->taskId)
            ->whereIn('status', [
                AuthoringTaskStatus::Queued,
                AuthoringTaskStatus::Running,
            ])
            ->update([
                'status' => AuthoringTaskStatus::Failed,
                'error_code' => 'queue_failed',
                'error_message' => 'The queued guided authoring task stopped unexpectedly. It is safe to retry.',
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

        Log::error('A queued guided authoring task failed.', [
            'task_id' => $this->taskId,
            'post_id' => $this->postId,
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return array{AgentEvent|null, AgentEvent|null, list<string>}
     */
    private function runAgent(AgentRunner $runner, AgentRequest $request): array
    {
        $result = null;
        $error = null;
        $narration = [];
        $narrationBytes = 0;

        foreach ($runner->stream($request) as $event) {
            if ($event->type === AgentEvent::TYPE_TEXT) {
                $remaining = config()->integer('assistant.limits.narration_bytes', 128 * 1024)
                    - $narrationBytes;

                if ($remaining > 0) {
                    $text = mb_strcut((string) $event->text, 0, $remaining, 'UTF-8');

                    if ($text !== '') {
                        $narration[] = $text;
                        $narrationBytes += strlen($text);
                    }
                }
            } elseif ($event->type === AgentEvent::TYPE_RESULT) {
                $result = $event;
            } elseif ($event->type === AgentEvent::TYPE_ERROR) {
                $error = $event;
            }
        }

        return [$result, $error, $narration];
    }
}

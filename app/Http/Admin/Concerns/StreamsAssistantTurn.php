<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\Services\AssistantAvailability;
use App\Domain\Assistant\Services\AssistantRunLease;
use App\Domain\Assistant\Services\WorkspaceManager;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Runs one agent turn and relays it to the browser as server-sent events:
 * `narration` (assistant text), `activity` (tool use), then either `change`
 * frames plus `done`, or a single `error`. The FrankenPHP worker holds the
 * connection open for the duration of the turn.
 */
trait StreamsAssistantTurn
{
    use SerializesAssistantChanges;

    protected function streamAssistantTurn(
        AgentRunner $runner,
        CompleteAssistantTurn $completeTurn,
        FailAssistantTurn $failTurn,
        AssistantRunLease $runLease,
        AssistantAvailability $availability,
        WorkspaceManager $workspace,
        Post $post,
        AssistantTurn $turn,
        Closure $makeAgentRequest,
    ): StreamedResponse {
        return response()->stream(function () use (
            $runner,
            $completeTurn,
            $failTurn,
            $runLease,
            $availability,
            $workspace,
            $post,
            $turn,
            $makeAgentRequest,
        ): void {
            set_time_limit(0);

            $narration = [];
            $narrationBytes = 0;
            $result = null;
            $error = null;
            $disconnected = false;

            try {
                $this->emitAssistantFrame('started', $turn->id);
                /** @var AgentRequest $agentRequest */
                $agentRequest = $makeAgentRequest();
                $stream = $runner->stream($agentRequest);

                foreach ($stream as $event) {
                    if (connection_aborted() === 1) {
                        $disconnected = true;

                        break;
                    }

                    match ($event->type) {
                        AgentEvent::TYPE_TEXT => $this->relayNarration($turn, $event, $narration, $narrationBytes),
                        AgentEvent::TYPE_TOOL_USE => $this->emitAssistantFrame('activity', $turn->id, [
                            'tool' => $event->tool,
                            'target' => $event->target,
                        ]),
                        AgentEvent::TYPE_HEARTBEAT, AgentEvent::TYPE_RETRY => $this->emitAssistantFrame(
                            'heartbeat',
                            $turn->id,
                        ),
                        AgentEvent::TYPE_RESULT => $result = $event,
                        AgentEvent::TYPE_ERROR => $error = $event,
                        default => null,
                    };
                }

                unset($stream);

                if ($disconnected) {
                    $failTurn->handle(
                        $turn,
                        'The assistant stream was closed.',
                        AssistantTurnStatus::Cancelled,
                        'cancelled',
                    );

                    return;
                }

                $terminalError = $result?->isError === true ? $result : $error;

                if ($result === null || $result->isError || $terminalError !== null) {
                    $terminalError ??= AgentEvent::error(
                        'The assistant did not finish this request.',
                        AgentFailureCode::ProtocolError->value,
                    );
                    $failureCode = AgentFailureCode::tryFrom((string) $terminalError->code)
                        ?? AgentFailureCode::Unknown;
                    $availabilityState = $availability->state();
                    $retryAt = $this->retryAt($terminalError->retryAt)
                        ?? $this->retryAt($availabilityState['retry_at']);
                    $failTurn->handle(
                        $turn,
                        $failureCode->message(),
                        $failureCode === AgentFailureCode::Cancelled
                            ? AssistantTurnStatus::Cancelled
                            : AssistantTurnStatus::Failed,
                        $failureCode,
                        $retryAt,
                    );
                    $turn->refresh();
                    $this->emitTurnError($turn);

                    return;
                }

                $changes = $completeTurn->handle(
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

                if ($turn->status === AssistantTurnStatus::Cancelling) {
                    $failTurn->handle(
                        $turn,
                        AgentFailureCode::Cancelled->message(),
                        AssistantTurnStatus::Cancelled,
                        AgentFailureCode::Cancelled,
                    );
                    $turn->refresh();
                }

                if ($turn->status !== AssistantTurnStatus::Completed) {
                    $this->emitTurnError($turn);

                    return;
                }

                foreach ($changes as $change) {
                    $this->emitAssistantFrame('change', $turn->id, [
                        'change' => $this->assistantChangeData($change),
                    ]);
                }

                $this->emitAssistantFrame('done', $turn->id, [
                    'turn' => [
                        'id' => $turn->id,
                        'status' => $turn->status->value,
                        'assistant_message' => $turn->assistant_message,
                        'duration_ms' => $turn->duration_ms,
                    ],
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $failTurn->handle(
                    $turn,
                    'The assistant crashed while finishing this turn.',
                    errorCode: 'execution_error',
                );
                $turn->refresh();

                if (connection_aborted() !== 1) {
                    $this->emitTurnError($turn);
                }
            } finally {
                if (isset($stream)) {
                    unset($stream);
                }

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
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  list<string>  $narration
     */
    private function relayNarration(
        AssistantTurn $turn,
        AgentEvent $event,
        array &$narration,
        int &$narrationBytes,
    ): void {
        $remaining = config()->integer('assistant.limits.narration_bytes', 128 * 1024) - $narrationBytes;

        if ($remaining <= 0) {
            return;
        }

        $text = mb_strcut((string) $event->text, 0, $remaining, 'UTF-8');

        if ($text === '') {
            return;
        }

        $narrationBytes += strlen($text);
        $narration[] = $text;
        $this->emitAssistantFrame('narration', $turn->id, ['text' => $text]);
    }

    /** @param array<string, mixed> $data */
    private function emitAssistantFrame(string $event, int $turnId, array $data = []): void
    {
        echo 'event: '.$event."\n".'data: '.json_encode(
            ['turn_id' => $turnId, ...$data],
            JSON_INVALID_UTF8_SUBSTITUTE,
        )."\n\n";

        if (app()->runningUnitTests()) {
            return;
        }

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    private function emitTurnError(AssistantTurn $turn): void
    {
        $this->emitAssistantFrame('error', $turn->id, [
            'code' => $turn->error_code ?? 'assistant_failed',
            'message' => $turn->error_message ?? 'The assistant could not finish this request.',
            'retry_at' => $turn->retry_at?->toISOString(),
        ]);
    }

    private function retryAt(?string $retryAt): ?CarbonInterface
    {
        if ($retryAt === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($retryAt);
        } catch (Throwable) {
            return null;
        }
    }
}

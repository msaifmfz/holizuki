<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Assistant\Actions\CompleteAssistantTurn;
use App\Domain\Assistant\Actions\FailAssistantTurn;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Services\AgentRunner;
use App\Domain\Assistant\ValueObjects\AgentEvent;
use App\Domain\Assistant\ValueObjects\AgentRequest;
use App\Domain\Publishing\Models\Post;
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
        Post $post,
        AssistantTurn $turn,
        AgentRequest $agentRequest,
    ): StreamedResponse {
        return response()->stream(function () use ($runner, $completeTurn, $failTurn, $post, $turn, $agentRequest): void {
            set_time_limit(0);

            $narration = [];
            $result = null;
            $errorMessage = null;

            try {
                foreach ($runner->stream($agentRequest) as $event) {
                    if (connection_aborted() === 1) {
                        $failTurn->handle($turn, 'The author closed the stream.', AssistantTurnStatus::Cancelled);

                        return;
                    }

                    match ($event->type) {
                        AgentEvent::TYPE_TEXT => $this->relayNarration($event, $narration),
                        AgentEvent::TYPE_TOOL_USE => $this->emitAssistantFrame('activity', ['tool' => $event->tool, 'target' => $event->target]),
                        AgentEvent::TYPE_RESULT => $result = $event,
                        AgentEvent::TYPE_ERROR => $errorMessage = $event->text,
                        default => null,
                    };
                }
            } catch (Throwable $exception) {
                report($exception);
                $failTurn->handle($turn, 'The assistant crashed mid-turn.');
                $this->emitAssistantFrame('error', ['message' => 'The assistant crashed mid-turn. Try again.']);

                return;
            }

            if ($result === null || $result->isError) {
                $raw = $result !== null ? $result->text : $errorMessage;
                $failTurn->handle($turn, $raw ?? 'The assistant did not finish.');
                $this->emitAssistantFrame('error', ['message' => $this->friendlyAgentError($raw)]);

                return;
            }

            $changes = $completeTurn->handle(
                $turn,
                $post,
                $narration === [] ? $result->text : implode("\n\n", $narration),
                $result->costUsd,
                $result->durationMs,
            );

            $turn->refresh();

            if ($turn->status === AssistantTurnStatus::Failed) {
                $this->emitAssistantFrame('error', ['message' => (string) $turn->error]);

                return;
            }

            foreach ($changes as $change) {
                $this->emitAssistantFrame('change', ['change' => $this->assistantChangeData($change)]);
            }

            $this->emitAssistantFrame('done', [
                'turn' => [
                    'id' => $turn->id,
                    'status' => $turn->status->value,
                    'assistant_message' => $turn->assistant_message,
                    'duration_ms' => $turn->duration_ms,
                ],
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** @param list<string> $narration */
    private function relayNarration(AgentEvent $event, array &$narration): void
    {
        $narration[] = (string) $event->text;
        $this->emitAssistantFrame('narration', ['text' => $event->text]);
    }

    /** @param array<string, mixed> $data */
    private function emitAssistantFrame(string $event, array $data): void
    {
        // Substitute rather than fail on any malformed UTF-8 in agent output,
        // so one bad byte can't emit `data: false` and break the frame.
        echo 'event: '.$event."\n".'data: '.json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";

        if (app()->runningUnitTests()) {
            return;
        }

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    private function friendlyAgentError(?string $raw): string
    {
        $raw = (string) $raw;

        if (str_contains($raw, 'rate limit') || str_contains($raw, 'usage limit')) {
            return 'The AI is cooling down — the subscription usage limit was reached. Try again in a while.';
        }

        if (str_contains($raw, 'not logged in') || str_contains($raw, 'authentication') || str_contains($raw, 'OAuth')) {
            return 'The AI is not signed in on the server. Check the Claude Code credentials.';
        }

        return 'The assistant could not finish this request. Try again.';
    }
}

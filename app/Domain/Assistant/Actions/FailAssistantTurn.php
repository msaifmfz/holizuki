<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AgentFailureCode;
use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FailAssistantTurn
{
    public function handle(
        AssistantTurn $turn,
        string $errorMessage,
        AssistantTurnStatus $status = AssistantTurnStatus::Failed,
        string|AgentFailureCode $errorCode = 'assistant_failed',
        ?CarbonInterface $retryAt = null,
    ): bool {
        return DB::transaction(function () use ($turn, $errorMessage, $status, $errorCode, $retryAt): bool {
            $errorCodeValue = $errorCode instanceof AgentFailureCode ? $errorCode->value : $errorCode;
            /** @var AssistantSession $session */
            $session = AssistantSession::query()
                ->whereKey($turn->assistant_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var AssistantTurn $lockedTurn */
            $lockedTurn = AssistantTurn::query()->whereKey($turn->id)->lockForUpdate()->firstOrFail();

            if (
                ! $lockedTurn->status->isActive()
                || $session->active_run_token !== $lockedTurn->run_token
            ) {
                return false;
            }

            $lockedTurn->forceFill([
                'status' => $status,
                'error_code' => $errorCodeValue,
                'error_message' => $errorMessage,
                'retry_at' => $retryAt,
                'finished_at' => now(),
            ])->save();

            $sessionUpdates = [
                'status' => AssistantSessionStatus::Idle,
                'active_run_token' => null,
                'turn_started_at' => null,
            ];

            $failureCode = AgentFailureCode::tryFrom($errorCodeValue);

            if (
                $failureCode === AgentFailureCode::SessionNotFound
                || (
                    $lockedTurn->task_type->usesPersistentSession()
                    && $failureCode?->invalidatesConversation() === true
                )
                || Str::contains(Str::lower($errorMessage), 'no conversation found')
            ) {
                $sessionUpdates['claude_session_id'] = (string) Str::uuid();
                $sessionUpdates['resume_ready'] = false;
                $sessionUpdates['conversation_model'] = null;
            }

            $session->forceFill($sessionUpdates)->save();
            $turn->setRawAttributes($lockedTurn->getAttributes(), true);

            return true;
        });
    }
}

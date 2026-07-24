<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantSession;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\DB;

class RequestAssistantCancellation
{
    public function handle(Post $post, ?string $requestId = null): ?AssistantTurn
    {
        return DB::transaction(function () use ($post, $requestId): ?AssistantTurn {
            $session = AssistantSession::query()
                ->where('post_id', $post->id)
                ->lockForUpdate()
                ->first();

            if ($session === null || $session->active_run_token === null) {
                return null;
            }

            $turn = $session->turns()
                ->where('run_token', $session->active_run_token)
                ->lockForUpdate()
                ->first();

            if ($turn === null || ! $turn->status->isActive()) {
                return null;
            }

            if ($requestId !== null && $turn->request_id !== $requestId) {
                return null;
            }

            if ($turn->status === AssistantTurnStatus::Running) {
                $turn->forceFill([
                    'status' => AssistantTurnStatus::Cancelling,
                    'cancel_requested_at' => now(),
                ])->save();
            }

            $session->forceFill(['status' => AssistantSessionStatus::Cancelling])->save();

            return $turn;
        });
    }
}

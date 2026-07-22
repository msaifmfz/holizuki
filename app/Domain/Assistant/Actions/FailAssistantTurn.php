<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Assistant\Models\AssistantTurn;
use Illuminate\Support\Str;

class FailAssistantTurn
{
    public function handle(AssistantTurn $turn, string $error, AssistantTurnStatus $status = AssistantTurnStatus::Failed): void
    {
        $turn->forceFill(['status' => $status, 'error' => $error])->save();

        $session = $turn->session;

        if ($session === null) {
            return;
        }

        $updates = [
            'status' => AssistantSessionStatus::Idle,
            'turn_started_at' => null,
        ];

        // A lost Claude Code session file (pruned, or the volume was
        // replaced) makes --resume fail permanently. Rotate the session id
        // so the next message starts a fresh conversation instead of
        // erroring forever.
        if (str_contains($error, 'No conversation found')) {
            $updates['claude_session_id'] = (string) Str::uuid();
        }

        $session->forceFill($updates)->save();
    }
}

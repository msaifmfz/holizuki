<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Assistant\Models\AssistantChange;

trait SerializesAssistantChanges
{
    /** @return array<string, mixed> */
    protected function assistantChangeData(AssistantChange $change): array
    {
        return [
            'id' => $change->id,
            'type' => $change->type->value,
            'status' => $change->status->value,
            'payload' => $change->payload,
            'turn_id' => $change->assistant_turn_id,
        ];
    }
}

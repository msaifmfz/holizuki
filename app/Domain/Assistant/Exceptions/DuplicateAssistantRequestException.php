<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use App\Domain\Assistant\Models\AssistantTurn;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DuplicateAssistantRequestException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly int $turnId,
        public readonly string $status,
    ) {
        parent::__construct('This assistant request has already been submitted.');
    }

    public static function forTurn(AssistantTurn $turn): self
    {
        return new self($turn->id, $turn->status->value);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'duplicate_request',
            'message' => $this->getMessage(),
            'turn_id' => $this->turnId,
            'status' => $this->status,
        ], 409);
    }
}

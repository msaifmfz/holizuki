<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OutlineNotReadyException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('Create and approve an outline before drafting the article.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'outline_not_ready',
            'message' => $this->getMessage(),
        ], 422);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class StaleAuthoringArtifactException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('This suggestion was generated from an older draft. Generate a fresh suggestion before selecting it.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 'stale_authoring_artifact',
            'message' => $this->getMessage(),
        ], 409);
    }
}

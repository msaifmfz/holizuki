<?php

declare(strict_types=1);

namespace App\Http\Auth\Responses;

use App\Http\Auth\Support\PublicReturnPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse;

class RoleAwareRegisterResponse implements RegisterResponse
{
    public function __construct(private readonly PublicReturnPath $returnPath) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect($this->returnPath->resolve($request));
    }
}

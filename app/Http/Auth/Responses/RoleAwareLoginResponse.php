<?php

declare(strict_types=1);

namespace App\Http\Auth\Responses;

use App\Domain\Identity\Models\User;
use App\Http\Auth\Support\PublicReturnPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse;

class RoleAwareLoginResponse implements LoginResponse
{
    public function __construct(private readonly PublicReturnPath $returnPath) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();

        return redirect($user instanceof User && $user->isAdministrator()
            ? route('dashboard', absolute: false)
            : $this->returnPath->resolve($request));
    }
}

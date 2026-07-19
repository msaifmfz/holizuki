<?php

declare(strict_types=1);

namespace App\Http\Auth\Responses;

use App\Domain\Identity\Models\User;
use App\Http\Auth\Support\PublicReturnPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;

class RoleAwareVerifyEmailResponse implements VerifyEmailResponse
{
    public function __construct(private readonly PublicReturnPath $returnPath) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();
        if ($user instanceof User && $user->isAdministrator()) {
            return redirect(route('dashboard', absolute: false).'?verified=1');
        }

        return redirect($this->returnPath->resolve($request));
    }
}

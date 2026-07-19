<?php

declare(strict_types=1);

namespace App\Http\Auth\Responses;

use App\Domain\Identity\Models\User;
use App\Http\Auth\Support\PublicReturnPath;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Http\Responses\RedirectAsIntended;
use Override;

class RoleAwareRedirectAsIntended extends RedirectAsIntended
{
    public function __construct(
        string $name,
        private readonly PublicReturnPath $returnPath,
    ) {
        parent::__construct($name);
    }

    #[Override]
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        return redirect($user instanceof User && $user->isAdministrator()
            ? route('dashboard', absolute: false)
            : $this->returnPath->resolve($request));
    }
}

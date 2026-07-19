<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Identity\Models\User;
use App\Http\Controller;
use App\Http\Public\Requests\ReaderAccountDeleteRequest;
use App\Http\Public\Requests\ReaderPasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The minimal reader self-service surface. Readers are blocked from the
 * settings portal and the Fortify user/* endpoints, so password changes and
 * account deletion live here on the public layout.
 */
class ReaderAccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isReader(), 403);

        return Inertia::render('public/account', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function updatePassword(ReaderPasswordUpdateRequest $request): RedirectResponse
    {
        $request->authenticatedUser()->update([
            'password' => $request->string('password')->toString(),
        ]);

        return back()->with('success', 'Password updated.');
    }

    public function destroy(ReaderAccountDeleteRequest $request): RedirectResponse
    {
        $user = $request->authenticatedUser();

        Auth::logout();
        $user->delete();
        event(new UserDeleted($user, []));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

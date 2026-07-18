<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileAvatarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use RuntimeException;

class ProfileAvatarController extends Controller
{
    public function store(UpdateProfileAvatarRequest $request): RedirectResponse
    {
        $user = $request->authenticatedUser();
        $avatar = $request->file('avatar');

        if (! $avatar instanceof UploadedFile) {
            throw new RuntimeException('The avatar upload is missing.');
        }

        $path = $avatar->store('avatars/'.$user->id, 'public');

        if ($path === false) {
            throw new RuntimeException('The avatar could not be stored.');
        }

        $previous = $user->avatar_path;
        $user->avatar_path = $path;
        $user->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar updated.')]);

        return to_route('profile.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar removed.')]);

        return to_route('profile.edit');
    }
}

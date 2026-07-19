<?php

declare(strict_types=1);

namespace App\Http\Settings\Controllers;

use App\Domain\Identity\Events\AuthorProfileUpdated;
use App\Domain\Identity\Events\UserDeleted;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Http\Controller;
use App\Http\Settings\Requests\ProfileDeleteRequest;
use App\Http\Settings\Requests\ProfileUpdateRequest;
use App\Support\Concerns\ResolvesUniqueSlug;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use ResolvesUniqueSlug;

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->authenticatedUser();

        $validated = $request->validated();
        $socialLinks = is_array($validated['social_links'] ?? null)
            ? array_filter($validated['social_links'], static fn (mixed $value): bool => $value !== null && $value !== '')
            : [];
        $validated['social_links'] = $socialLinks === [] ? null : $socialLinks;

        $user->fill($validated);

        if ($user->author_slug === null || trim($user->author_slug) === '') {
            $user->author_slug = $this->resolveUniqueSlug($user->name, User::class, $user->id, 'author_slug');
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('name')) {
            event(new AuthorProfileUpdated($user));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->authenticatedUser();

        /** @var list<int> $postIds */
        $postIds = Post::query()->where('author_id', $user->id)->pluck('id')->all();
        $avatarPath = $user->avatar_path;

        Auth::logout();

        $user->delete();

        if ($avatarPath !== null) {
            Storage::disk('public')->delete($avatarPath);
        }

        event(new UserDeleted($user, $postIds));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Posts\RebuildPostMetadata;
use App\Concerns\ResolvesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Post;
use App\Models\User;
use App\Support\PublicCache;
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
    public function update(ProfileUpdateRequest $request, RebuildPostMetadata $rebuildPostMetadata): RedirectResponse
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
            $rebuildPostMetadata->handleQuery(Post::query()->where('author_id', $user->id));
            PublicCache::flush();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, RebuildPostMetadata $rebuildPostMetadata): RedirectResponse
    {
        $user = $request->authenticatedUser();

        $postIds = Post::query()->where('author_id', $user->id)->pluck('id');
        $avatarPath = $user->avatar_path;

        Auth::logout();

        $user->delete();

        if ($avatarPath !== null) {
            Storage::disk('public')->delete($avatarPath);
        }

        $rebuildPostMetadata->handleQuery(Post::query()->whereKey($postIds));
        PublicCache::flush();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

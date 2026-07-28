<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Identity\Actions\SaveAuthoringPreferences;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Actions\SavePostAuthoringJourney;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\SerializesPostAuthoringJourney;
use App\Http\Admin\Requests\UpdatePostAuthoringJourneyRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostAuthoringJourneyController extends Controller
{
    use SerializesPostAuthoringJourney;

    public function show(Request $request, Post $post, SavePostAuthoringJourney $saveJourney): JsonResponse
    {
        Gate::authorize('update', $post);
        $user = $request->user();
        $defaults = $user instanceof User ? $user->authoringPreferences() : [];

        return response()->json([
            'journey' => $this->postAuthoringJourneyData(
                $saveJourney->preview($post, $defaults),
            ),
        ]);
    }

    public function update(
        UpdatePostAuthoringJourneyRequest $request,
        Post $post,
        SavePostAuthoringJourney $saveJourney,
        SaveAuthoringPreferences $savePreferences,
    ): JsonResponse {
        $attributes = $request->validated();
        $user = $request->authenticatedUser();
        // `save_as_default` describes the author, not the post.
        unset($attributes['save_as_default']);
        $journey = $saveJourney->handle(
            $post,
            $attributes,
            $user->authoringPreferences(),
        );

        // Tuning one post's voice should not silently retune every other post,
        // so the author says when a choice becomes their default.
        if ($request->boolean('save_as_default')) {
            $savePreferences->handle($user, $this->preferenceAttributes($attributes));
        }

        return response()->json([
            'journey' => $this->postAuthoringJourneyData($journey),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{personality?: string, custom_instructions?: string|null}
     */
    private function preferenceAttributes(array $attributes): array
    {
        $preferences = [];
        $personality = $attributes['personality'] ?? null;

        if (is_string($personality)) {
            $preferences['personality'] = $personality;
        }

        if (array_key_exists('custom_instructions', $attributes)) {
            $customInstructions = $attributes['custom_instructions'];

            if ($customInstructions === null || is_string($customInstructions)) {
                $preferences['custom_instructions'] = $customInstructions;
            }
        }

        return $preferences;
    }
}

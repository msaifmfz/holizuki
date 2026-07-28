<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\SelectAuthoringImagePassage;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Services\AuthoringImageFlowReadiness;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\SerializesAuthoringImageCompositions;
use App\Http\Admin\Requests\Assistant\UpdateAuthoringImageCompositionRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;

final class AuthoringImageCompositionController extends Controller
{
    use SerializesAuthoringImageCompositions;

    public function update(
        UpdateAuthoringImageCompositionRequest $request,
        Post $post,
        AuthoringImageComposition $composition,
        SelectAuthoringImagePassage $selectPassage,
        AuthoringImageFlowReadiness $imageFlowReadiness,
    ): JsonResponse {
        abort_unless($composition->post_id === $post->id, 404);
        $selectedPassageId = $request->validated('selected_passage_id');
        // Scoped to the post so a passage id from elsewhere reads as missing
        // rather than as one this author may not touch. The action re-checks
        // ownership under lock regardless.
        $passage = is_string($selectedPassageId)
            ? AuthoringArtifact::query()
                ->whereBelongsTo($post)
                ->where('public_id', $selectedPassageId)
                ->firstOrFail()
            : null;
        $updatedComposition = $selectPassage->handle(
            $post,
            $composition,
            $passage,
            $request->string('passage_markdown')->toString(),
            $request->authenticatedUser(),
        );

        return response()->json([
            'image_composition' => $this->authoringImageCompositionData(
                $updatedComposition,
                $imageFlowReadiness->passageIsStale($post, $updatedComposition),
            ),
        ]);
    }
}

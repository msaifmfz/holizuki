<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers\Assistant;

use App\Domain\Assistant\Actions\SyncAuthoringImageFlow;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Assistant\Services\AuthoringImageFlowReadiness;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\SerializesAuthoringImageCompositions;
use App\Http\Admin\Requests\Assistant\SyncAuthoringImageFlowRequest;
use App\Http\Controller;
use Illuminate\Http\JsonResponse;

final class AuthoringImageFlowController extends Controller
{
    use SerializesAuthoringImageCompositions;

    public function update(
        SyncAuthoringImageFlowRequest $request,
        Post $post,
        AuthoringArtifact $artifact,
        SyncAuthoringImageFlow $syncFlow,
        AuthoringImageFlowReadiness $imageFlowReadiness,
    ): JsonResponse {
        /** @var list<array{id?: string|null, usage: string, section_heading?: string|null, media_ids: list<int>}> $blocks */
        $blocks = $request->validated('blocks');
        $compositions = $syncFlow->handle(
            $post,
            $artifact,
            $request->authenticatedUser(),
            $blocks,
        );

        return response()->json([
            'image_compositions' => $compositions
                ->map(fn (AuthoringImageComposition $composition): array => $this
                    ->authoringImageCompositionData(
                        $composition,
                        $imageFlowReadiness->passageIsStale($post, $composition),
                    ))
                ->all(),
        ]);
    }
}

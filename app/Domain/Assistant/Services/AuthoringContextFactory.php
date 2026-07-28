<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Domain\Publishing\Enums\PostSourceVisibility;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use App\Domain\Publishing\Models\PostSource;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonException;
use LogicException;

final readonly class AuthoringContextFactory
{
    public function __construct(
        private AuthoringStoryPhotoEligibility $storyPhotos,
        private AuthoringContextCache $cache,
        private AuthoringPhotoFlowContext $photoFlow,
    ) {}

    /** @return array<string, mixed> */
    public function forPlans(Post $post): array
    {
        return $this->cache->remember($post, 'plans', [], fn (): array => [
            ...$this->journeyContext($post),
            ...$this->sourceContext($post),
        ]);
    }

    /** @return array<string, mixed> */
    public function forImageGuidance(Post $post, AuthoringArtifact $plan): array
    {
        $this->ensurePlan($post, $plan);

        return $this->cache->remember(
            $post,
            'image_guidance',
            [$plan->id],
            fn (): array => [
                ...$this->journeyContext($post),
                ...$this->sourceContext($post),
                ...$this->storyMediaGuidanceContext($post),
                'plan_artifact_id' => $plan->id,
                'plan_id' => $plan->public_id,
                'plan_payload' => $plan->payload,
                'plan_payload_hash' => $this->hashArray($plan->payload),
            ],
        );
    }

    /** @return array<string, mixed> */
    public function forDraft(Post $post, AuthoringArtifact $plan): array
    {
        $this->ensurePlan($post, $plan);

        return $this->cache->remember($post, 'draft', [$plan->id], fn (): array => [
            ...$this->journeyContext($post),
            ...$this->sourceContext($post),
            ...$this->storyMediaContext($post),
            'plan_artifact_id' => $plan->id,
            'plan_id' => $plan->public_id,
            'plan_payload' => $plan->payload,
            'plan_payload_hash' => $this->hashArray($plan->payload),
            ...$this->photoFlow->forDraft($post, $plan),
        ]);
    }

    /** @return array<string, mixed> */
    public function forImagePassages(
        Post $post,
        AuthoringImageComposition $composition,
    ): array {
        return $this->cache->remember(
            $post,
            'image_passages',
            [$composition->id],
            fn (): array => $this->buildImagePassagesContext($post, $composition),
        );
    }

    /** @return array<string, mixed> */
    private function buildImagePassagesContext(
        Post $post,
        AuthoringImageComposition $composition,
    ): array {
        $composition->loadMissing(['plan', 'media', 'selectedPassage']);
        $plan = $composition->plan;

        if (
            $composition->post_id !== $post->id
            || ! $plan instanceof AuthoringArtifact
            || $plan->post_id !== $post->id
            || $composition->usage !== AuthoringImageCompositionUsage::Include
            || ! $this->storyPhotos->compositionIsEligible($post, $composition)
        ) {
            throw new LogicException('The image composition is not eligible for story photo writing.');
        }

        $mediaIds = [];

        foreach ($composition->media as $media) {
            $mediaIds[] = $media->id;
        }

        $guidanceInputHash = $this->hash(
            AuthoringTaskKind::ImageGuidance,
            $this->forImageGuidance($post, $plan),
        );
        $guidanceByMediaId = $this->freshImageGuidanceByMediaId(
            $post,
            $plan,
            $guidanceInputHash,
        );

        $guidance = [];

        foreach ($mediaIds as $mediaId) {
            if (isset($guidanceByMediaId[$mediaId])) {
                $guidance[] = $guidanceByMediaId[$mediaId];
            }
        }

        $compositionData = $this->photoFlow->compositionData($composition);
        unset(
            $compositionData['selected_passage_id'],
            $compositionData['passage_markdown'],
        );

        return [
            ...$this->journeyContext($post),
            ...$this->sourceContext($post),
            ...$this->storyMediaContextForIds($post, $mediaIds),
            'plan_artifact_id' => $plan->id,
            'plan_id' => $plan->public_id,
            'plan_payload' => $plan->payload,
            'plan_payload_hash' => $this->hashArray($plan->payload),
            'composition_id' => $composition->id,
            'composition_public_id' => $composition->public_id,
            'composition' => $compositionData,
            'image_guidance' => $guidance,
            ...$this->photoFlow->forPassages(
                $post,
                $plan,
                $composition,
                $guidanceByMediaId,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function forResearch(Post $post): array
    {
        return $this->cache->remember($post, 'research', [], fn (): array => [
            ...$this->journeyContext($post),
            ...$this->sourceContext($post),
        ]);
    }

    /** @param array<string, mixed> $context */
    public function hash(AuthoringTaskKind $kind, array $context): string
    {
        return hash('sha256', $kind->value.':'.$this->canonicalJson($context));
    }

    /** @return array<string, mixed> */
    private function journeyContext(Post $post): array
    {
        return $this->cache->remember($post, 'journey', [], function () use ($post): array {
            $journey = PostAuthoringJourney::query()->whereBelongsTo($post)->first();

            if (! $journey instanceof PostAuthoringJourney) {
                throw new LogicException('The post does not have an authoring journey.');
            }

            return [
                'journey_id' => $journey->id,
                'brief' => $journey->brief,
                'personality' => $journey->personality->value,
                'custom_instructions' => $journey->custom_instructions,
            ];
        });
    }

    /**
     * @param  list<int>  $mediaIds
     * @return array<string, mixed>
     */
    private function storyMediaContextForIds(Post $post, array $mediaIds): array
    {
        $media = [];

        foreach ($this->storyMediaItems($post) as $item) {
            if (in_array($item['id'], $mediaIds, true)) {
                $media[] = $item;
            }
        }

        return [
            'media' => $media,
            'media_ids' => Arr::pluck($media, 'id'),
        ];
    }

    /** @return array<string, mixed> */
    private function storyMediaGuidanceContext(Post $post): array
    {
        $media = [];

        foreach ($this->storyMediaItems($post) as $item) {
            $media[] = [
                'id' => $item['id'],
                'story_priority' => $item['story_priority'],
                'story_notes' => $item['story_notes'],
                'used_in_draft' => $item['used_in_draft'],
            ];
        }

        return [
            'media' => $media,
            'media_ids' => Arr::pluck($media, 'id'),
        ];
    }

    /** @return array<string, mixed> */
    private function storyMediaContext(Post $post): array
    {
        $media = $this->storyMediaItems($post);

        return [
            'media' => $media,
            'media_ids' => Arr::pluck($media, 'id'),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     updated_at: string|null,
     *     display_name: string|null,
     *     alt_text: string|null,
     *     caption: string|null,
     *     story_priority: string,
     *     story_notes: string|null,
     *     used_in_draft: bool
     * }>
     */
    private function storyMediaItems(Post $post): array
    {
        return $this->cache->remember($post, 'story_media', [], function () use ($post): array {
            $draft = $post->draft()->firstOrFail();
            $usedMediaIds = $draft->body?->referencedMediaIds() ?? [];
            $storyMedia = $this->storyPhotos->forPost($post);
            $items = [];

            foreach ($storyMedia as $item) {
                $items[] = [
                    'id' => $item->id,
                    'updated_at' => $item->updated_at?->toISOString(),
                    'display_name' => $item->display_name,
                    'alt_text' => $item->alt_text,
                    'caption' => $item->caption,
                    'story_priority' => in_array($item->id, $usedMediaIds, true)
                        ? PostMediaStoryPriority::Required->value
                        : $item->story_priority->value,
                    'story_notes' => $item->story_notes,
                    'used_in_draft' => in_array($item->id, $usedMediaIds, true),
                ];
            }

            return $items;
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function freshImageGuidanceByMediaId(
        Post $post,
        AuthoringArtifact $plan,
        string $inputHash,
    ): array {
        return $this->cache->remember(
            $post,
            'fresh_image_guidance',
            [$plan->id, $inputHash],
            function () use ($post, $plan, $inputHash): array {
                $guidanceArtifacts = AuthoringArtifact::query()
                    ->whereBelongsTo($post)
                    ->where('parent_id', $plan->id)
                    ->where('type', AuthoringArtifactType::ImageGuidance)
                    ->where('base_lock_version', $post->lock_version)
                    ->whereHas(
                        'task',
                        fn (Builder $query) => $query
                            ->where('kind', AuthoringTaskKind::ImageGuidance)
                            ->where('input_hash', $inputHash),
                    )
                    ->latest('id')
                    ->limit(100)
                    ->get();
                $guidanceByMediaId = [];

                foreach ($guidanceArtifacts as $artifact) {
                    $mediaId = $artifact->payload['media_id'] ?? null;

                    if (is_int($mediaId) && ! isset($guidanceByMediaId[$mediaId])) {
                        $guidanceByMediaId[$mediaId] = $artifact->payload;
                    }
                }

                return $guidanceByMediaId;
            },
        );
    }

    private function ensurePlan(Post $post, AuthoringArtifact $plan): void
    {
        if (
            $plan->post_id !== $post->id
            || $plan->type !== AuthoringArtifactType::Plan
        ) {
            throw new LogicException('The selected authoring plan does not belong to this post.');
        }
    }

    /** @return array<string, mixed> */
    private function sourceContext(Post $post): array
    {
        return $this->cache->remember($post, 'sources', [], function () use ($post): array {
            $approvedSources = PostSource::query()
                ->whereBelongsTo($post)
                ->whereNotNull('approved_at')
                ->oldest('id')
                ->get(['id', 'kind', 'url', 'visibility', 'updated_at']);

            $sources = $approvedSources
                ->map(fn (PostSource $source): array => [
                    'id' => $source->id,
                    'kind' => $source->kind->value,
                    'updated_at' => $source->updated_at?->toISOString(),
                ])
                ->all();

            $domains = $approvedSources
                ->map(static function (PostSource $source): ?string {
                    if ($source->url === null) {
                        return null;
                    }

                    $host = parse_url($source->url, PHP_URL_HOST);

                    return is_string($host) ? Str::lower($host) : null;
                })
                ->filter(static fn (?string $domain): bool => $domain !== null)
                ->unique()
                ->values()
                ->all();

            return [
                'sources' => $sources,
                'source_ids' => Arr::pluck($sources, 'id'),
                'citable_source_ids' => $approvedSources
                    ->filter(static fn (PostSource $source): bool => $source->visibility
                        === PostSourceVisibility::Public)
                    ->map(static fn (PostSource $source): int => $source->id)
                    ->values()
                    ->all(),
                'source_domains' => $domains,
            ];
        });
    }

    /** @param array<string, mixed> $value */
    private function hashArray(array $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        $normalized = $this->normalize($value);

        try {
            return json_encode(
                $normalized,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new LogicException('The authoring context could not be encoded.', $exception->getCode(), previous: $exception);
        }
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}

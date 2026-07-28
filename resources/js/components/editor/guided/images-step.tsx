import {
    ArrowDown,
    ArrowUp,
    ImageIcon,
    Images,
    Layers3,
    LoaderCircle,
    Save,
    Sparkles,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ImageFlowState } from '@/lib/authoring-flow';
import {
    compositionFlowItems,
    defaultFlowItems,
    flowFingerprint,
    mediaIdsMatch,
    orderFlowItemsByPlan,
    reindexFlowItems,
    resolveIncomingFlowState,
} from '@/lib/authoring-flow';
import { cn } from '@/lib/utils';
import type {
    AuthoringImageComposition,
    AuthoringImageFlowItem,
    AuthoringImageGroupSuggestion,
    AuthoringImageGuidance,
    AuthoringImagePassage,
    AuthoringImageUsage,
    AuthoringMediaData,
    AuthoringPlanOption,
} from '@/types';
import { PassageComposer } from './images/passage-composer';
import type { CompositionUpdate } from './images/passage-composer';
import type { StoryMediaEdits } from './images/story-media-edits';
import { StoryPhotoCard } from './images/story-photo-card';

export type { StoryMediaEdits };

const acceptedImageTypes = 'image/jpeg,image/png,image/webp';

export default function ImagesStep({
    plan,
    media,
    guidance,
    groupSuggestions,
    compositions,
    passages,
    featuredImageUrl,
    uploading,
    reviewing,
    savingFlow,
    generatingPassagesFor,
    savingPassage,
    authoringBusy,
    assistantAvailable,
    error,
    onUpload,
    onChange,
    onRemove,
    onReview,
    onPendingChangesChange,
    onSaveFlow,
    onGeneratePassages,
    onSavePassage,
}: {
    plan: AuthoringPlanOption | null;
    media: AuthoringMediaData[];
    guidance: Record<number, AuthoringImageGuidance | undefined>;
    groupSuggestions: AuthoringImageGroupSuggestion[];
    compositions: AuthoringImageComposition[];
    passages: AuthoringImagePassage[];
    featuredImageUrl: string | null;
    uploading: boolean;
    reviewing: boolean;
    savingFlow: boolean;
    generatingPassagesFor: string | null;
    savingPassage: boolean;
    authoringBusy: boolean;
    assistantAvailable: boolean;
    error: string | null;
    onUpload: (files: File[]) => Promise<void> | void;
    onChange: (
        media: AuthoringMediaData,
        updates: Partial<StoryMediaEdits>,
    ) => Promise<boolean | void> | boolean | void;
    onRemove: (media: AuthoringMediaData) => Promise<void> | void;
    onReview: (plan: AuthoringPlanOption) => Promise<void> | void;
    onPendingChangesChange: (pending: boolean) => void;
    onSaveFlow: (
        plan: AuthoringPlanOption,
        flow: AuthoringImageFlowItem[],
    ) => Promise<boolean | void> | boolean | void;
    onGeneratePassages: (
        composition: AuthoringImageComposition,
    ) => Promise<unknown> | void;
    onSavePassage: (
        composition: AuthoringImageComposition,
        update: CompositionUpdate,
    ) => Promise<boolean | void> | boolean | void;
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [dirtyPassageIds, setDirtyPassageIds] = useState<Set<string>>(
        () => new Set(),
    );
    const storyMedia = useMemo(
        () =>
            media.filter(
                (item) =>
                    item.url !== featuredImageUrl &&
                    item.role !== 'hero' &&
                    item.placement !== 'featured',
            ),
        [featuredImageUrl, media],
    );
    const remainingPhotoSlots = Math.max(0, 20 - media.length);
    const flowSignature = compositions
        .map(
            (composition) =>
                `${composition.id}:${composition.usage}:${composition.section_heading ?? ''}:${composition.position}:${composition.media.map((item) => item.id).join(',')}`,
        )
        .join('|');
    const mediaSignature = storyMedia.map((item) => item.id).join('|');
    const guidanceSignature = Object.values(guidance)
        .map(
            (item) =>
                `${item?.id ?? ''}:${item?.usage ?? ''}:${item?.section_heading ?? ''}`,
        )
        .join('|');
    const assignedMediaIds = new Set(
        compositions.flatMap((composition) =>
            composition.media.map((item) => item.id),
        ),
    );
    const unassignedStoryMedia = storyMedia.filter(
        (item) => !assignedMediaIds.has(item.id),
    );
    const incomingFlowSignature = `${plan?.id ?? ''}::${flowSignature}::${mediaSignature}::${guidanceSignature}`;
    const incomingFlowItems = [
        ...compositionFlowItems(compositions),
        ...defaultFlowItems(unassignedStoryMedia, guidance).map(
            (item, index) => ({
                ...item,
                position: compositions.length + index,
            }),
        ),
    ];
    const incomingFlowState: ImageFlowState = {
        source: incomingFlowSignature,
        planId: plan?.id ?? null,
        items: incomingFlowItems,
        baselineItems: incomingFlowItems,
    };
    const [flowState, setFlowState] = useState<ImageFlowState>(
        () => incomingFlowState,
    );
    let resolvedFlowState = flowState;

    if (flowState.source !== incomingFlowSignature) {
        resolvedFlowState = resolveIncomingFlowState(
            flowState,
            incomingFlowState,
            storyMedia.map((item) => item.id),
        );
        setFlowState(resolvedFlowState);
    }

    const flowItems = resolvedFlowState.items;
    const updateFlowItems = (
        update: (current: AuthoringImageFlowItem[]) => AuthoringImageFlowItem[],
    ): void => {
        setFlowState((current) => {
            const active = resolveIncomingFlowState(
                current,
                incomingFlowState,
                storyMedia.map((item) => item.id),
            );

            return {
                ...active,
                items: update(active.items),
            };
        });
    };

    const findFlowItem = (mediaId: number): AuthoringImageFlowItem => {
        const item = flowItems.find((candidate) =>
            candidate.media_ids.includes(mediaId),
        ) ?? {
            usage: 'include',
            section_heading: null,
            position: flowItems.length,
            media_ids: [mediaId],
        };

        return storyMedia.find((mediaItem) => mediaItem.id === mediaId)
            ?.story_priority === 'required'
            ? { ...item, usage: 'include' }
            : item;
    };

    const changeFlowItem = (
        mediaId: number,
        updates: Pick<AuthoringImageFlowItem, 'usage' | 'section_heading'>,
    ): void => {
        const normalizedUpdates = {
            ...updates,
            section_heading:
                updates.usage === 'exclude' ? null : updates.section_heading,
        };

        updateFlowItems((current) => {
            const matching = current.find((item) =>
                item.media_ids.includes(mediaId),
            );

            if (!matching) {
                return [
                    ...current,
                    {
                        ...normalizedUpdates,
                        position: current.length,
                        media_ids: [mediaId],
                    },
                ];
            }

            if (
                matching.media_ids.length > 1 &&
                normalizedUpdates.usage === 'exclude'
            ) {
                return current
                    .flatMap((item) => {
                        if (item !== matching) {
                            return item;
                        }

                        const separated = item.media_ids.map((id, offset) => {
                            const isSelected = id === mediaId;
                            const isRequired =
                                storyMedia.find(
                                    (candidate) => candidate.id === id,
                                )?.story_priority === 'required';
                            const usage: AuthoringImageUsage =
                                isSelected && !isRequired
                                    ? 'exclude'
                                    : 'include';

                            return {
                                usage,
                                section_heading:
                                    usage === 'exclude'
                                        ? null
                                        : item.section_heading,
                                position: item.position + offset,
                                media_ids: [id],
                            };
                        });
                        const retainedIndex = separated.findIndex(
                            (candidate) => candidate.usage === 'include',
                        );

                        return separated.map((candidate, index) =>
                            index === retainedIndex && item.id
                                ? { ...candidate, id: item.id }
                                : candidate,
                        );
                    })
                    .sort((left, right) => left.position - right.position)
                    .map((item, index) => ({ ...item, position: index }));
            }

            return current.map((item) =>
                item === matching
                    ? {
                          ...item,
                          ...normalizedUpdates,
                          usage: item.media_ids.some(
                              (id) =>
                                  storyMedia.find(
                                      (candidate) => candidate.id === id,
                                  )?.story_priority === 'required',
                          )
                              ? 'include'
                              : normalizedUpdates.usage,
                      }
                    : item,
            );
        });
    };

    const acceptGroup = (suggestion: AuthoringImageGroupSuggestion): void => {
        updateFlowItems((current) => {
            const next: AuthoringImageFlowItem[] = [];
            let insertedSuggestion = false;

            for (const item of [...current].sort(
                (left, right) => left.position - right.position,
            )) {
                const overlaps = item.media_ids.some((id) =>
                    suggestion.media_ids.includes(id),
                );

                if (!overlaps) {
                    next.push(item);
                    continue;
                }

                for (const mediaId of item.media_ids) {
                    if (suggestion.media_ids.includes(mediaId)) {
                        if (!insertedSuggestion) {
                            next.push({
                                usage: 'include',
                                section_heading: suggestion.section_heading,
                                position: item.position,
                                media_ids: suggestion.media_ids,
                            });
                            insertedSuggestion = true;
                        }

                        continue;
                    }

                    next.push({
                        usage: item.usage,
                        section_heading: item.section_heading,
                        position: item.position,
                        media_ids: [mediaId],
                    });
                }
            }

            if (!insertedSuggestion) {
                next.push({
                    usage: 'include',
                    section_heading: suggestion.section_heading,
                    position: current.length,
                    media_ids: suggestion.media_ids,
                });
            }

            return reindexFlowItems(next);
        });
    };

    const separateGroup = (item: AuthoringImageFlowItem): void => {
        updateFlowItems((current) =>
            current
                .flatMap((candidate) =>
                    candidate === item
                        ? candidate.media_ids.map((mediaId, offset) => ({
                              id: offset === 0 ? candidate.id : undefined,
                              usage: candidate.usage,
                              section_heading: candidate.section_heading,
                              position: candidate.position + offset,
                              media_ids: [mediaId],
                          }))
                        : candidate,
                )
                .sort((left, right) => left.position - right.position)
                .map((candidate, index) => ({
                    ...candidate,
                    position: index,
                })),
        );
    };

    const moveFlowItem = (
        item: AuthoringImageFlowItem,
        offset: -1 | 1,
    ): void => {
        updateFlowItems((current) => {
            const sectionItems = normalizedFlow.filter(
                (candidate) =>
                    candidate.usage === 'include' &&
                    candidate.section_heading === item.section_heading,
            );
            const sectionIndex = sectionItems.findIndex((candidate) =>
                mediaIdsMatch(candidate, item),
            );
            const target = sectionItems[sectionIndex + offset];

            if (!target) {
                return current;
            }

            const currentIndex = current.findIndex((candidate) =>
                mediaIdsMatch(candidate, item),
            );
            const targetIndex = current.findIndex((candidate) =>
                mediaIdsMatch(candidate, target),
            );

            if (
                currentIndex < 0 ||
                targetIndex < 0 ||
                targetIndex >= current.length
            ) {
                return current;
            }

            const reordered = [...current];
            [reordered[currentIndex], reordered[targetIndex]] = [
                reordered[targetIndex],
                reordered[currentIndex],
            ];

            return reindexFlowItems(reordered);
        });
    };

    const mediaById = new Map(storyMedia.map((item) => [item.id, item]));
    const normalizedFlow = orderFlowItemsByPlan(
        flowItems
            .filter((item) =>
                item.media_ids.some((mediaId) => mediaById.has(mediaId)),
            )
            .map((item) => ({
                ...item,
                usage: item.media_ids.some(
                    (id) => mediaById.get(id)?.story_priority === 'required',
                )
                    ? ('include' as const)
                    : item.usage,
                media_ids: item.media_ids.filter((id) => mediaById.has(id)),
            })),
        plan?.sections ?? [],
    );
    const persistedFlow = orderFlowItemsByPlan(
        compositionFlowItems(compositions)
            .filter((item) =>
                item.media_ids.some((mediaId) => mediaById.has(mediaId)),
            )
            .map((item) => ({
                ...item,
                media_ids: item.media_ids.filter((id) => mediaById.has(id)),
            })),
        plan?.sections ?? [],
    );
    const canMoveFlowItem = (
        item: AuthoringImageFlowItem,
        offset: -1 | 1,
    ): boolean => {
        if (item.usage !== 'include') {
            return false;
        }

        const sectionItems = normalizedFlow.filter(
            (candidate) =>
                candidate.usage === 'include' &&
                candidate.section_heading === item.section_heading,
        );
        const sectionIndex = sectionItems.findIndex((candidate) =>
            mediaIdsMatch(candidate, item),
        );

        return sectionItems[sectionIndex + offset] !== undefined;
    };
    const flowMediaIds = normalizedFlow.flatMap((item) => item.media_ids);
    const flowMediaIdCounts = new Map<number, number>();

    flowMediaIds.forEach((id) =>
        flowMediaIdCounts.set(id, (flowMediaIdCounts.get(id) ?? 0) + 1),
    );

    const flowValidationMessages: string[] = [];

    if (
        storyMedia.some((item) => (flowMediaIdCounts.get(item.id) ?? 0) !== 1)
    ) {
        flowValidationMessages.push(
            'Every story photo must appear exactly once in the flow.',
        );
    }

    if (
        normalizedFlow.some(
            (item) =>
                item.usage === 'include' &&
                !plan?.sections.some(
                    (section) => section.heading === item.section_heading,
                ),
        )
    ) {
        flowValidationMessages.push(
            'Choose an article section for every included photo.',
        );
    }

    if (
        storyMedia
            .filter((item) => item.story_priority === 'required')
            .some(
                (required) =>
                    !normalizedFlow.some(
                        (item) =>
                            item.usage === 'include' &&
                            item.media_ids.includes(required.id),
                    ),
            )
    ) {
        flowValidationMessages.push(
            'Photos marked required must stay included.',
        );
    }

    if (
        normalizedFlow.some(
            (item) => item.usage === 'exclude' && item.media_ids.length !== 1,
        )
    ) {
        flowValidationMessages.push(
            'Separate a photo sequence before leaving one of its photos out.',
        );
    }

    const currentGroupKeys = new Set(
        groupSuggestions
            .filter((suggestion) => suggestion.status !== 'stale')
            .map((suggestion) => suggestion.media_ids.join('|')),
    );
    const persistedGroupKeys = new Set(
        compositions
            .filter((composition) => composition.media.length > 1)
            .map(
                (composition) =>
                    `${composition.id}:${composition.media
                        .map((item) => item.id)
                        .join('|')}`,
            ),
    );

    if (
        normalizedFlow.some(
            (item) =>
                item.media_ids.length > 1 &&
                !currentGroupKeys.has(item.media_ids.join('|')) &&
                !(
                    item.id &&
                    persistedGroupKeys.has(
                        `${item.id}:${item.media_ids.join('|')}`,
                    )
                ),
        )
    ) {
        flowValidationMessages.push(
            'Keep an older photo sequence separate, or choose a current AI sequence.',
        );
    }

    if (dirtyPassageIds.size > 0) {
        flowValidationMessages.push(
            'Save your passage edits before saving a different photo flow.',
        );
    }

    const flowValid = flowValidationMessages.length === 0;
    const flowDirty =
        flowFingerprint(normalizedFlow) !== flowFingerprint(persistedFlow);
    const hasPendingChanges = flowDirty || dirtyPassageIds.size > 0;
    const groupingWouldReplaceSavedWriting = (
        suggestion: AuthoringImageGroupSuggestion,
    ): boolean =>
        compositions.some((composition) => {
            if (
                composition.passage_markdown.trim() === '' ||
                !composition.media.some((item) =>
                    suggestion.media_ids.includes(item.id),
                )
            ) {
                return false;
            }

            return (
                composition.media.map((item) => item.id).join('|') !==
                suggestion.media_ids.join('|')
            );
        });
    const handlePassageDirtyChange = useCallback(
        (compositionId: string, dirty: boolean): void => {
            setDirtyPassageIds((current) => {
                const next = new Set(current);

                if (dirty) {
                    next.add(compositionId);
                } else {
                    next.delete(compositionId);
                }

                if (
                    next.size === current.size &&
                    [...next].every((id) => current.has(id))
                ) {
                    return current;
                }

                return next;
            });
        },
        [],
    );

    useEffect(() => {
        onPendingChangesChange(hasPendingChanges);
    }, [hasPendingChanges, onPendingChangesChange]);

    useEffect(
        () => () => onPendingChangesChange(false),
        [onPendingChangesChange],
    );

    return (
        <div className="grid gap-6">
            <Card>
                <CardHeader className="sm:flex-row sm:items-start sm:justify-between">
                    <div className="grid gap-1.5">
                        <CardTitle>
                            Build the story around your photos
                        </CardTitle>
                        <CardDescription>
                            These are photos inside the article, not its hero
                            image. Add what you know, then let the assistant
                            suggest where each photo fits and what to write.
                        </CardDescription>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={
                            storyMedia.length === 0 ||
                            uploading ||
                            reviewing ||
                            authoringBusy ||
                            !assistantAvailable ||
                            plan === null
                        }
                        onClick={() => {
                            if (plan) {
                                void onReview(plan);
                            }
                        }}
                    >
                        {reviewing ? (
                            <LoaderCircle className="animate-spin" />
                        ) : (
                            <Sparkles />
                        )}
                        {reviewing
                            ? 'Planning photo flow…'
                            : 'Ask AI to plan the photo flow'}
                    </Button>
                </CardHeader>
                <CardContent className="grid gap-4">
                    {!plan && (
                        <div
                            role="alert"
                            className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                        >
                            Choose an article plan first so photo suggestions
                            can match its sections.
                        </div>
                    )}
                    <button
                        type="button"
                        className="flex min-h-40 w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-5 text-center text-sm text-muted-foreground transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        disabled={
                            uploading ||
                            authoringBusy ||
                            remainingPhotoSlots === 0
                        }
                        onClick={() => fileInputRef.current?.click()}
                    >
                        {uploading ? (
                            <LoaderCircle className="size-8 animate-spin" />
                        ) : (
                            <Images className="size-8" />
                        )}
                        <span className="font-medium text-foreground">
                            {uploading
                                ? 'Uploading story photos…'
                                : 'Choose JPG, PNG, or WebP photos'}
                        </span>
                        <span>
                            {remainingPhotoSlots > 0
                                ? `Up to 5 MB each. ${remainingPhotoSlots} photo ${remainingPhotoSlots === 1 ? 'slot' : 'slots'} remaining.`
                                : 'This post has reached its 20-photo limit.'}
                        </span>
                    </button>
                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        accept={acceptedImageTypes}
                        aria-label="Story photos"
                        className="sr-only"
                        disabled={
                            uploading ||
                            authoringBusy ||
                            remainingPhotoSlots === 0
                        }
                        onChange={(event) => {
                            const files = Array.from(event.target.files ?? []);

                            if (files.length > 0 && remainingPhotoSlots > 0) {
                                void onUpload(
                                    files.slice(0, remainingPhotoSlots),
                                );
                            }

                            event.target.value = '';
                        }}
                    />
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                </CardContent>
            </Card>

            {groupSuggestions.length > 0 && (
                <section
                    aria-labelledby="photo-group-suggestions"
                    className="grid gap-3"
                >
                    <div>
                        <h3
                            id="photo-group-suggestions"
                            className="font-semibold"
                        >
                            Suggested photo sequences
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Group related photos when they tell one moment
                            better together.
                        </p>
                    </div>
                    <div className="grid gap-3 lg:grid-cols-2">
                        {groupSuggestions.map((suggestion) => {
                            const protectsSavedWriting =
                                groupingWouldReplaceSavedWriting(suggestion);

                            return (
                                <Card key={suggestion.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle className="text-base">
                                                    {suggestion.title}
                                                </CardTitle>
                                                <CardDescription>
                                                    {suggestion.section_heading ??
                                                        'Choose a section'}
                                                </CardDescription>
                                            </div>
                                            <Badge variant="secondary">
                                                {suggestion.media_ids.length}{' '}
                                                photos
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="grid gap-3">
                                        <div className="flex gap-2 overflow-x-auto">
                                            {suggestion.media_ids.map(
                                                (mediaId) => {
                                                    const item =
                                                        mediaById.get(mediaId);

                                                    return item ? (
                                                        <img
                                                            key={mediaId}
                                                            src={item.url}
                                                            alt=""
                                                            className="size-20 rounded-lg object-cover"
                                                        />
                                                    ) : null;
                                                },
                                            )}
                                        </div>
                                        {suggestion.narrative_role && (
                                            <p className="text-sm">
                                                {suggestion.narrative_role}
                                            </p>
                                        )}
                                        <p className="text-sm text-muted-foreground">
                                            {suggestion.rationale}
                                        </p>
                                        {protectsSavedWriting && (
                                            <p className="text-sm text-amber-700 dark:text-amber-300">
                                                Keep these photos separate to
                                                protect passages you already
                                                wrote.
                                            </p>
                                        )}
                                    </CardContent>
                                    <CardFooter>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={
                                                suggestion.status === 'stale' ||
                                                protectsSavedWriting
                                            }
                                            onClick={() =>
                                                acceptGroup(suggestion)
                                            }
                                        >
                                            <Layers3 />
                                            Use this sequence
                                        </Button>
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>
                </section>
            )}

            <section
                aria-labelledby="story-photo-library"
                className="grid gap-4"
            >
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h3 id="story-photo-library" className="font-semibold">
                            Story photos
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Your notes keep the writing accurate and personal.
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {storyMedia.length}{' '}
                        {storyMedia.length === 1 ? 'photo' : 'photos'}
                    </Badge>
                </div>

                {storyMedia.length === 0 ? (
                    <div className="flex min-h-40 flex-col items-center justify-center gap-2 rounded-lg border bg-muted/30 p-6 text-center text-sm text-muted-foreground">
                        <ImageIcon className="size-7" />
                        No story photos yet. You can continue without them.
                    </div>
                ) : (
                    <div className="grid gap-5">
                        {storyMedia.map((item) => (
                            <StoryPhotoCard
                                key={item.id}
                                media={item}
                                guidance={guidance[item.id] ?? null}
                                flowItem={findFlowItem(item.id)}
                                sections={plan?.sections ?? []}
                                removalDisabled={
                                    uploading ||
                                    savingFlow ||
                                    savingPassage ||
                                    authoringBusy
                                }
                                onChange={onChange}
                                onFlowChange={changeFlowItem}
                                onRemove={onRemove}
                            />
                        ))}
                    </div>
                )}
            </section>

            {storyMedia.length > 0 && plan && (
                <section
                    aria-labelledby="article-photo-flow"
                    className="grid gap-4"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3
                                id="article-photo-flow"
                                className="font-semibold"
                            >
                                Article photo flow
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                This order follows your selected plan. Save it
                                before asking for passage options.
                            </p>
                        </div>
                        <Button
                            type="button"
                            disabled={
                                uploading ||
                                savingFlow ||
                                authoringBusy ||
                                !flowValid ||
                                !flowDirty
                            }
                            onClick={() =>
                                void onSaveFlow(plan, normalizedFlow)
                            }
                        >
                            {savingFlow ? (
                                <LoaderCircle className="animate-spin" />
                            ) : (
                                <Save />
                            )}
                            {savingFlow
                                ? 'Saving flow…'
                                : flowDirty
                                  ? 'Save photo flow'
                                  : 'Photo flow saved'}
                        </Button>
                    </div>

                    {flowDirty && (
                        <p
                            role="status"
                            className="rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm"
                        >
                            Save this photo flow before writing passages or
                            continuing to the draft.
                        </p>
                    )}

                    {flowDirty && !flowValid && (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm"
                        >
                            <p className="font-medium">
                                Finish these photo-flow details:
                            </p>
                            <ul className="mt-1 list-disc space-y-1 pl-5 text-muted-foreground">
                                {flowValidationMessages.map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="grid gap-2">
                        {normalizedFlow.map((item, index) => {
                            const items = item.media_ids
                                .map((id) => mediaById.get(id))
                                .filter(
                                    (
                                        candidate,
                                    ): candidate is AuthoringMediaData =>
                                        candidate !== undefined,
                                );

                            return (
                                <div
                                    key={item.id ?? item.media_ids.join('-')}
                                    className={cn(
                                        'grid gap-3 rounded-xl border bg-card p-3 sm:grid-cols-[2rem_1fr_auto] sm:items-center',
                                        item.usage === 'exclude' &&
                                            'opacity-60',
                                    )}
                                >
                                    <span className="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                        {index + 1}
                                    </span>
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex -space-x-3">
                                            {items.map((mediaItem) => (
                                                <img
                                                    key={mediaItem.id}
                                                    src={mediaItem.url}
                                                    alt=""
                                                    className="size-12 rounded-lg border-2 border-card object-cover"
                                                />
                                            ))}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {items
                                                    .map(
                                                        (mediaItem) =>
                                                            mediaItem.display_name,
                                                    )
                                                    .join(' + ')}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {item.usage === 'exclude'
                                                    ? 'Not used'
                                                    : (item.section_heading ??
                                                      'Choose a section')}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div
                                            className="flex items-center"
                                            role="group"
                                            aria-label={`Reorder ${items
                                                .map(
                                                    (mediaItem) =>
                                                        mediaItem.display_name,
                                                )
                                                .join(' and ')}`}
                                        >
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Move ${items
                                                    .map(
                                                        (mediaItem) =>
                                                            mediaItem.display_name,
                                                    )
                                                    .join(' and ')} up`}
                                                disabled={
                                                    !canMoveFlowItem(item, -1)
                                                }
                                                onClick={() =>
                                                    moveFlowItem(item, -1)
                                                }
                                            >
                                                <ArrowUp />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Move ${items
                                                    .map(
                                                        (mediaItem) =>
                                                            mediaItem.display_name,
                                                    )
                                                    .join(' and ')} down`}
                                                disabled={
                                                    !canMoveFlowItem(item, 1)
                                                }
                                                onClick={() =>
                                                    moveFlowItem(item, 1)
                                                }
                                            >
                                                <ArrowDown />
                                            </Button>
                                        </div>
                                        <Badge
                                            variant={
                                                item.usage === 'include'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {item.usage}
                                        </Badge>
                                        {item.media_ids.length > 1 && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    separateGroup(item)
                                                }
                                            >
                                                <X />
                                                Keep separate
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>
            )}

            {compositions.some(
                (composition) => composition.usage === 'include',
            ) && (
                <section
                    aria-labelledby="photo-passages"
                    className="grid gap-4"
                >
                    <div>
                        <h3 id="photo-passages" className="font-semibold">
                            Write around each photo
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Choose one of three suggestions, edit it, or write
                            your own. Saved words will be preserved in the
                            article draft.
                        </p>
                    </div>
                    <div className="grid gap-4">
                        {compositions
                            .filter(
                                (composition) =>
                                    composition.usage === 'include',
                            )
                            .map((composition) => (
                                <PassageComposer
                                    key={composition.id}
                                    composition={composition}
                                    passages={passages.filter(
                                        (passage) =>
                                            passage.composition_id ===
                                            composition.id,
                                    )}
                                    generating={
                                        generatingPassagesFor === composition.id
                                    }
                                    authoringBusy={authoringBusy}
                                    saving={savingPassage}
                                    assistantAvailable={assistantAvailable}
                                    flowSaved={!flowDirty && !uploading}
                                    onGenerate={() =>
                                        onGeneratePassages(composition)
                                    }
                                    onDirtyChange={handlePassageDirtyChange}
                                    onSave={onSavePassage}
                                />
                            ))}
                    </div>
                </section>
            )}

            {!assistantAvailable && (
                <p className="text-sm text-muted-foreground">
                    The assistant is unavailable. You can still choose each
                    photo’s section and write its passage yourself.
                </p>
            )}
        </div>
    );
}

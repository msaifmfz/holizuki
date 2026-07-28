import { useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useMemo, useRef, useState } from 'react';
import { index } from '@/actions/App/Http/Admin/Controllers/Assistant/AuthoringArtifactController';
import {
    apply as applyDraftRoute,
    select as selectDraftRoute,
    store as generateDraftRoute,
} from '@/actions/App/Http/Admin/Controllers/Assistant/AuthoringDraftController';
import { update as updateImageCompositionRoute } from '@/actions/App/Http/Admin/Controllers/Assistant/AuthoringImageCompositionController';
import { update as saveImageFlowRoute } from '@/actions/App/Http/Admin/Controllers/Assistant/AuthoringImageFlowController';
import { store as generateImageGuidanceRoute } from '@/actions/App/Http/Admin/Controllers/Assistant/AuthoringImageGuidanceController';
import { store as generateImagePassagesRoute } from '@/actions/App/Http/Admin/Controllers/Assistant/AuthoringImagePassageController';
import {
    select as selectPlanRoute,
    store as generatePlansRoute,
} from '@/actions/App/Http/Admin/Controllers/Assistant/AuthoringPlanController';
import { store as researchRoute } from '@/actions/App/Http/Admin/Controllers/Assistant/AuthoringResearchController';
import type {
    ArtifactIndexResponse,
    ApplyDraftResponse,
    AuthoringTask,
    CompositionUpdate,
    EnqueueResponse,
    ImageCompositionResponse,
    ImageFlowResponse,
    SelectionResponse,
} from '@/lib/authoring-artifacts';
import {
    artifactsForPlan,
    artifactsMatchingLatestGroup,
    authoringTaskError,
    draftFromArtifact,
    imageGroupFromArtifact,
    imageGuidanceFromArtifact,
    imagePassageFromArtifact,
    latestArtifactGroup,
    latestPassageArtifacts,
    normalizeComposition,
    planFromArtifact,
    researchFromArtifact,
} from '@/lib/authoring-artifacts';
import type {
    AuthoringDraftVariant,
    AuthoringImageComposition,
    AuthoringImageFlowItem,
    AuthoringImageGroupSuggestion,
    AuthoringImageGuidance,
    AuthoringImagePassage,
    AuthoringPlanOption,
} from '@/types';

const pollIntervalMs = { initial: 2_000, maximum: 15_000 } as const;

const emptyArtifactState: ArtifactIndexResponse = {
    tasks: [],
    plans: [],
    drafts: [],
    research_findings: [],
    image_guidance: [],
    image_group_suggestions: [],
    image_passages: [],
    image_compositions: [],
    selection: { plan_id: null, draft_id: null },
};

export function useAuthoringArtifacts(postId: number) {
    const [state, setState] =
        useState<ArtifactIndexResponse>(emptyArtifactState);
    const [hydrated, setHydrated] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [requestedDraftPlanId, setRequestedDraftPlanId] = useState<
        string | null
    >(null);
    const [requestedPassageCompositionId, setRequestedPassageCompositionId] =
        useState<string | null>(null);
    const listRequest = useHttp<Record<string, never>, ArtifactIndexResponse>(
        {},
    );
    const plansRequest = useHttp<Record<string, never>, EnqueueResponse>({});
    const guidanceRequest = useHttp<Record<string, never>, EnqueueResponse>({});
    const passagesRequest = useHttp<Record<string, never>, EnqueueResponse>({});
    const draftRequest = useHttp<Record<string, never>, EnqueueResponse>({});
    const researchRequest = useHttp<Record<string, never>, EnqueueResponse>({});
    const selectionRequest = useHttp<Record<string, never>, SelectionResponse>(
        {},
    );
    const flowRequest = useHttp<
        { blocks: AuthoringImageFlowItem[] },
        ImageFlowResponse
    >({ blocks: [] });
    const compositionRequest = useHttp<
        CompositionUpdate,
        ImageCompositionResponse
    >({ selected_passage_id: null, passage_markdown: '' });
    const applyRequest = useHttp<{ lock_version: number }, ApplyDraftResponse>({
        lock_version: 0,
    });
    const enqueueInFlight = useRef(false);

    const refresh = async (): Promise<void> => {
        try {
            const response = await listRequest.get(index.url(postId));
            setState({ ...emptyArtifactState, ...response });
            setError(null);
        } catch {
            setError('AI writing progress could not be loaded.');
        } finally {
            setHydrated(true);
        }
    };

    const hydrate = useEffectEvent(() => {
        void refresh();
    });

    useEffect(() => {
        const timeout = window.setTimeout(() => hydrate(), 0);

        return () => window.clearTimeout(timeout);
    }, []);

    const hasActiveTask = state.tasks.some((task) =>
        ['queued', 'running'].includes(task.status),
    );
    const enqueueRequestProcessing =
        plansRequest.processing ||
        guidanceRequest.processing ||
        passagesRequest.processing ||
        draftRequest.processing ||
        researchRequest.processing;

    const poll = useEffectEvent(() => {
        void refresh();
    });

    // Authoring tasks run for seconds to minutes. Poll quickly at first so a
    // fast task feels immediate, then ease off: every request re-checks every
    // artifact and photo block against a freshly built context, so a flat
    // two-second interval keeps that work running for the whole task.
    useEffect(() => {
        if (!hasActiveTask) {
            return;
        }

        let timeout = 0;
        let delay: number = pollIntervalMs.initial;

        const schedule = (): void => {
            timeout = window.setTimeout(() => {
                poll();
                delay = Math.min(delay * 2, pollIntervalMs.maximum);
                schedule();
            }, delay);
        };

        schedule();

        return () => window.clearTimeout(timeout);
    }, [hasActiveTask]);

    const enqueue = async (
        request: typeof plansRequest,
        url: string,
        failureMessage: string,
    ): Promise<AuthoringTask | null> => {
        if (hasActiveTask || enqueueInFlight.current) {
            return null;
        }

        enqueueInFlight.current = true;

        try {
            const response = await request.post(url);
            setState((current) => ({
                ...current,
                tasks: [
                    response.task,
                    ...current.tasks.filter(
                        (task) => task.id !== response.task.id,
                    ),
                ],
            }));
            setError(null);

            if (response.task.status === 'completed') {
                void refresh();
            }

            return response.task;
        } catch {
            setError(failureMessage);

            return null;
        } finally {
            enqueueInFlight.current = false;
        }
    };

    const generatePlans = (): Promise<AuthoringTask | null> =>
        enqueue(
            plansRequest,
            generatePlansRoute.url(postId),
            'The assistant could not start creating plans.',
        );

    const generateImageGuidance = (
        plan: AuthoringPlanOption,
    ): Promise<AuthoringTask | null> =>
        enqueue(
            guidanceRequest,
            generateImageGuidanceRoute.url({
                post: postId,
                artifact: plan.id,
            }),
            'The assistant could not start planning these story photos.',
        );

    const generateImagePassages = async (
        composition: AuthoringImageComposition,
    ): Promise<AuthoringTask | null> => {
        if (hasActiveTask || enqueueInFlight.current) {
            return null;
        }

        setRequestedPassageCompositionId(composition.id);
        const task = await enqueue(
            passagesRequest,
            generateImagePassagesRoute.url({
                post: postId,
                composition: composition.id,
            }),
            'The assistant could not start writing passage options.',
        );

        if (task === null) {
            setRequestedPassageCompositionId(null);
        }

        return task;
    };

    const generateDraft = async (
        plan: AuthoringPlanOption,
    ): Promise<AuthoringTask | null> => {
        if (hasActiveTask || enqueueInFlight.current) {
            return null;
        }

        setRequestedDraftPlanId(plan.id);
        const task = await enqueue(
            draftRequest,
            generateDraftRoute.url({
                post: postId,
                artifact: plan.id,
            }),
            'The assistant could not start drafting this plan.',
        );

        if (task === null) {
            setRequestedDraftPlanId(null);
        }

        return task;
    };

    const research = (): Promise<AuthoringTask | null> =>
        enqueue(
            researchRequest,
            researchRoute.url(postId),
            'The assistant could not start web research.',
        );

    const selectPlan = async (plan: AuthoringPlanOption): Promise<void> => {
        try {
            const response = await selectionRequest.post(
                selectPlanRoute.url({
                    post: postId,
                    artifact: plan.id,
                }),
            );
            setState((current) => ({
                ...current,
                selection: response.selection,
                plans: current.plans.map((artifact) => ({
                    ...artifact,
                    selected: artifact.id === response.selection.plan_id,
                })),
                drafts: current.drafts.map((artifact) => ({
                    ...artifact,
                    selected: false,
                })),
            }));
            setRequestedDraftPlanId(null);
            setRequestedPassageCompositionId(null);
            setError(null);
            await refresh();
        } catch {
            setError('The plan could not be selected.');

            throw new Error('The plan could not be selected.');
        }
    };

    const saveImageFlow = async (
        plan: AuthoringPlanOption,
        compositions: AuthoringImageFlowItem[],
    ): Promise<boolean> => {
        flowRequest.transform(() => ({ blocks: compositions }));

        try {
            const response = await flowRequest.put(
                saveImageFlowRoute.url({
                    post: postId,
                    artifact: plan.id,
                }),
            );
            const saved =
                response.image_compositions ?? response.compositions ?? [];
            setState((current) => ({
                ...current,
                image_compositions: saved,
            }));
            setError(null);
            await refresh();

            return true;
        } catch {
            const validationMessage = Object.values(flowRequest.errors)
                .flat()
                .find(
                    (message): message is string => typeof message === 'string',
                );
            setError(
                validationMessage ?? 'The story photo flow could not be saved.',
            );

            return false;
        }
    };

    const updateImageComposition = async (
        composition: AuthoringImageComposition,
        attributes: CompositionUpdate,
    ): Promise<boolean> => {
        compositionRequest.transform(() => attributes);

        try {
            const response = await compositionRequest.patch(
                updateImageCompositionRoute.url({
                    post: postId,
                    composition: composition.id,
                }),
            );
            const saved =
                response.image_composition ?? response.composition ?? null;

            if (saved !== null) {
                setState((current) => ({
                    ...current,
                    image_compositions: current.image_compositions.map(
                        (item) => (item.id === saved.id ? saved : item),
                    ),
                }));
            }

            setError(null);

            return true;
        } catch {
            setError('The selected photo passage could not be saved.');

            return false;
        }
    };

    const selectDraft = async (draft: AuthoringDraftVariant): Promise<void> => {
        try {
            const response = await selectionRequest.post(
                selectDraftRoute.url({
                    post: postId,
                    artifact: draft.id,
                }),
            );
            setState((current) => ({
                ...current,
                selection: response.selection,
                drafts: current.drafts.map((artifact) => ({
                    ...artifact,
                    selected: artifact.id === response.selection.draft_id,
                })),
            }));
            setError(null);
        } catch {
            setError('The draft could not be selected.');
        }
    };

    const applyDraft = async (
        draft: AuthoringDraftVariant,
        lockVersion: number,
    ): Promise<ApplyDraftResponse | null> => {
        applyRequest.transform(() => ({ lock_version: lockVersion }));

        try {
            const response = await applyRequest.post(
                applyDraftRoute.url({
                    post: postId,
                    artifact: draft.id,
                }),
            );
            setState((current) => ({
                ...current,
                selection: response.selection,
                drafts: current.drafts.map((artifact) => ({
                    ...artifact,
                    selected: artifact.id === response.selection.draft_id,
                })),
            }));
            setError(null);

            return response;
        } catch {
            setError(
                'This AI draft no longer matches your working copy. Create a new draft from the latest version.',
            );

            return null;
        }
    };

    const plans = useMemo(
        () => latestArtifactGroup(state.plans).map(planFromArtifact),
        [state.plans],
    );
    const selectedPlanId = state.selection.plan_id;
    const drafts = useMemo(
        () =>
            artifactsForPlan(state.drafts, selectedPlanId).map(
                draftFromArtifact,
            ),
        [selectedPlanId, state.drafts],
    );
    const researchFindings = useMemo(
        () => state.research_findings.map(researchFromArtifact),
        [state.research_findings],
    );
    const currentImageGuidanceArtifacts = useMemo(
        () =>
            latestArtifactGroup(
                artifactsForPlan(state.image_guidance, selectedPlanId),
            ),
        [selectedPlanId, state.image_guidance],
    );
    const imageGuidance = useMemo(
        () =>
            Object.fromEntries(
                currentImageGuidanceArtifacts
                    .map(imageGuidanceFromArtifact)
                    .filter(
                        (guidance): guidance is AuthoringImageGuidance =>
                            guidance !== null,
                    )
                    .map((guidance) => [guidance.media_id, guidance]),
            ),
        [currentImageGuidanceArtifacts],
    );
    const imageGroupSuggestions = useMemo(
        () =>
            artifactsMatchingLatestGroup(
                currentImageGuidanceArtifacts,
                artifactsForPlan(state.image_group_suggestions, selectedPlanId),
            )
                .map(imageGroupFromArtifact)
                .filter(
                    (suggestion): suggestion is AuthoringImageGroupSuggestion =>
                        suggestion !== null,
                ),
        [
            currentImageGuidanceArtifacts,
            selectedPlanId,
            state.image_group_suggestions,
        ],
    );
    const imagePassages = useMemo(
        () =>
            latestPassageArtifacts(
                artifactsForPlan(state.image_passages, selectedPlanId),
            )
                .map(imagePassageFromArtifact)
                .filter(
                    (passage): passage is AuthoringImagePassage =>
                        passage !== null,
                ),
        [selectedPlanId, state.image_passages],
    );
    const imageCompositions = useMemo(
        () =>
            state.image_compositions
                .filter((composition) => composition.plan_id === selectedPlanId)
                .map(normalizeComposition)
                .sort(
                    (left, right) =>
                        left.position - right.position ||
                        left.id.localeCompare(right.id),
                ),
        [selectedPlanId, state.image_compositions],
    );
    const activePlanTask = state.tasks.find(
        (task) =>
            task.kind === 'plans' &&
            ['queued', 'running'].includes(task.status),
    );
    const activeDraftTask = state.tasks.find(
        (task) =>
            task.kind === 'draft' &&
            ['queued', 'running'].includes(task.status),
    );
    const activeGuidanceTask = state.tasks.find(
        (task) =>
            task.kind === 'image_guidance' &&
            ['queued', 'running'].includes(task.status),
    );
    const activePassagesTask = state.tasks.find(
        (task) =>
            task.kind === 'image_passages' &&
            ['queued', 'running'].includes(task.status),
    );
    const activeResearchTask = state.tasks.find(
        (task) =>
            task.kind === 'research' &&
            ['queued', 'running'].includes(task.status),
    );

    return {
        hydrated,
        error,
        plansError: authoringTaskError(state.tasks, 'plans'),
        imagesError:
            authoringTaskError(state.tasks, 'image_guidance') ??
            authoringTaskError(state.tasks, 'image_passages'),
        draftError: authoringTaskError(state.tasks, 'draft'),
        researchError: authoringTaskError(state.tasks, 'research'),
        plans,
        drafts,
        researchFindings,
        imageGuidance,
        imageGroupSuggestions,
        imagePassages,
        imageCompositions,
        authoringBusy: hasActiveTask || enqueueRequestProcessing,
        generatingPlans:
            plansRequest.processing || activePlanTask !== undefined,
        reviewingImages:
            guidanceRequest.processing || activeGuidanceTask !== undefined,
        generatingPassages:
            passagesRequest.processing || activePassagesTask !== undefined,
        generatingPassagesFor:
            !passagesRequest.processing && activePassagesTask === undefined
                ? null
                : (activePassagesTask?.target_id ??
                  requestedPassageCompositionId),
        savingImageFlow: flowRequest.processing,
        savingImageComposition: compositionRequest.processing,
        generatingDraft:
            draftRequest.processing || activeDraftTask !== undefined,
        generatingDraftPlanId:
            activeDraftTask === undefined ? null : requestedDraftPlanId,
        researching:
            researchRequest.processing || activeResearchTask !== undefined,
        generatePlans,
        generateImageGuidance,
        saveImageFlow,
        generateImagePassages,
        updateImageComposition,
        generateDraft,
        research,
        selectPlan,
        selectDraft,
        applyDraft,
        refresh,
    };
}

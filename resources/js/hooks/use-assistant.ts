import { useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import AssistantCancelController from '@/actions/App/Http/Admin/Controllers/Assistant/AssistantCancelController';
import AssistantChangeController from '@/actions/App/Http/Admin/Controllers/Assistant/AssistantChangeController';
import AssistantChatController from '@/actions/App/Http/Admin/Controllers/Assistant/AssistantChatController';
import AssistantImageController from '@/actions/App/Http/Admin/Controllers/Assistant/AssistantImageController';
import AssistantMetadataController from '@/actions/App/Http/Admin/Controllers/Assistant/AssistantMetadataController';
import AssistantOutlineController from '@/actions/App/Http/Admin/Controllers/Assistant/AssistantOutlineController';
import AssistantStateController from '@/actions/App/Http/Admin/Controllers/Assistant/AssistantStateController';
import AssistantTransformController from '@/actions/App/Http/Admin/Controllers/Assistant/AssistantTransformController';
import { useSseStream } from '@/hooks/use-sse-stream';
import {
    assistantCooldownMessage,
    AssistantRequestError,
    sendAssistantCancellation,
} from '@/lib/assistant/sse';
import { threadFromTurns } from '@/lib/assistant/thread';
import type { TransformPreset } from '@/lib/assistant/transforms';
import { decodeHttpResponsePayload } from '@/lib/http-response';
import type {
    AcceptChangeResponse,
    AssistantChangeData,
    AssistantChangeType,
    AssistantChatEntry,
    AssistantStateResponse,
    AssistantStatus,
    AssistantStreamFrame,
} from '@/types';

const ACTIVE_STATUSES: AssistantStatus[] = ['running', 'cancelling'];

type AssistantRuntimeState = Pick<
    AssistantStateResponse,
    | 'enabled'
    | 'available'
    | 'status'
    | 'retry_at'
    | 'active_turn'
    | 'outline_ready'
>;

const INITIAL_RUNTIME_STATE: AssistantRuntimeState = {
    enabled: true,
    available: false,
    status: 'unavailable',
    retry_at: null,
    active_turn: null,
    outline_ready: false,
};

/**
 * Owns the editor's co-writer state and reconciles every local operation with
 * the canonical server state. Starts are single-flight and idempotent, while
 * proposal decisions remain visible until the server confirms them.
 */
export function useAssistant({
    postId,
    ensureSaved,
    onAccepted,
}: {
    postId: number;
    ensureSaved: () => Promise<boolean>;
    onAccepted: (post: AcceptChangeResponse['post']) => void;
}) {
    const ownedRequestRef = useRef<{
        postId: number;
        requestId: string;
    } | null>(null);
    const stream = useSseStream(() => {
        const ownedRequest = ownedRequestRef.current;

        if (ownedRequest !== null) {
            sendAssistantCancellation(
                AssistantCancelController.url(ownedRequest.postId),
                {
                    request_id: ownedRequest.requestId,
                },
            );
        }
    });
    const [runtime, setRuntime] = useState(INITIAL_RUNTIME_STATE);
    const [changes, setChanges] = useState<AssistantChangeData[]>([]);
    const [thread, setThread] = useState<AssistantChatEntry[]>([]);
    const [narration, setNarration] = useState<string | null>(null);
    const [hydrated, setHydrated] = useState(false);
    const [stateError, setStateError] = useState<string | null>(null);
    const [starting, setStarting] = useState(false);
    const [pendingDecisionIds, setPendingDecisionIds] = useState<Set<number>>(
        new Set(),
    );
    const [decisionErrors, setDecisionErrors] = useState<
        Record<number, string>
    >({});

    const stateRequest = useHttp<Record<string, never>, AssistantStateResponse>(
        {},
    );
    const acceptRequest = useHttp<Record<string, never>, AcceptChangeResponse>(
        {},
    );
    const rejectRequest = useHttp<
        Record<string, never>,
        { change: AssistantChangeData }
    >({});
    const cancelRequest = useHttp<
        Record<string, never>,
        { status: AssistantStatus }
    >({});

    const runtimeRef = useRef(runtime);
    const hydratedRef = useRef(false);
    const startingRef = useRef(false);
    const startGenerationRef = useRef(0);
    const mountedRef = useRef(true);
    const stateRequestRef = useRef<Promise<AssistantStateResponse> | null>(
        null,
    );
    const pendingDecisionIdsRef = useRef(new Set<number>());
    const liveEntryRef = useRef<string | null>(null);

    useEffect(() => {
        mountedRef.current = true;

        return () => {
            mountedRef.current = false;
            startGenerationRef.current += 1;
            startingRef.current = false;
        };
    }, []);

    const updateRuntime = (
        update:
            | AssistantRuntimeState
            | ((current: AssistantRuntimeState) => AssistantRuntimeState),
    ): void => {
        setRuntime((current) => {
            const next =
                typeof update === 'function' ? update(current) : update;
            runtimeRef.current = next;

            return next;
        });
    };

    const applyState = (
        response: AssistantStateResponse,
        withThread: boolean,
    ): void => {
        updateRuntime({
            enabled: response.enabled,
            available: response.available,
            status: response.status,
            retry_at: response.retry_at,
            active_turn: response.active_turn,
            outline_ready: response.outline_ready,
        });
        setChanges(response.changes);
        setStateError(null);

        if (withThread) {
            liveEntryRef.current = null;
            setThread(threadFromTurns(response.turns));
        }
    };

    const requestState = async (): Promise<AssistantStateResponse> => {
        if (stateRequestRef.current !== null) {
            return stateRequestRef.current;
        }

        let responseError: AssistantRequestError | null = null;
        const request = stateRequest.get(AssistantStateController.url(postId), {
            onHttpException: (response) => {
                responseError = errorFromHttpResponse(
                    response.status,
                    response.data,
                );

                return true;
            },
            onNetworkError: () => {
                responseError = new AssistantRequestError(
                    'The assistant status could not be loaded.',
                    'network_error',
                );

                return true;
            },
        });
        stateRequestRef.current = request;

        try {
            return await request;
        } catch (error) {
            throw responseError ?? normalizeError(error);
        } finally {
            if (stateRequestRef.current === request) {
                stateRequestRef.current = null;
            }
        }
    };

    const refreshState = async (withThread: boolean): Promise<boolean> => {
        try {
            const response = await requestState();

            if (!mountedRef.current) {
                return false;
            }

            applyState(response, withThread);

            return true;
        } catch (error) {
            if (!mountedRef.current) {
                return false;
            }

            const normalized = normalizeError(error);
            setStateError(normalized.message);

            if (!hydratedRef.current) {
                updateRuntime({
                    ...INITIAL_RUNTIME_STATE,
                    enabled: normalized.status !== 404,
                    status:
                        normalized.status === 404 ? 'disabled' : 'unavailable',
                });
            }

            return false;
        } finally {
            if (mountedRef.current) {
                hydratedRef.current = true;
                setHydrated(true);
            }
        }
    };

    const hydrateAssistant = useEffectEvent(() => {
        void refreshState(true);
    });

    useEffect(() => {
        hydrateAssistant();
    }, []);

    const pollAssistant = useEffectEvent(() => {
        void refreshState(true);
    });

    useEffect(() => {
        if (!ACTIVE_STATUSES.includes(runtime.status) || stream.streaming) {
            return;
        }

        const interval = window.setInterval(() => {
            pollAssistant();
        }, 2_000);

        return () => window.clearInterval(interval);
    }, [runtime.status, stream.streaming]);

    useEffect(() => {
        if (runtime.status !== 'cooldown') {
            return;
        }

        const retryTime =
            runtime.retry_at === null
                ? Number.NaN
                : new Date(runtime.retry_at).getTime();
        const delay = Number.isNaN(retryTime)
            ? 30_000
            : Math.max(1_000, retryTime - Date.now() + 250);
        const timeout = window.setTimeout(
            () => {
                pollAssistant();
            },
            Math.min(delay, 2_147_483_647),
        );

        return () => window.clearTimeout(timeout);
    }, [runtime.retry_at, runtime.status]);

    const upsertChange = (change: AssistantChangeData): void => {
        setChanges((current) => [
            ...current.filter(
                (existing) =>
                    existing.id !== change.id &&
                    (change.type === 'body'
                        ? existing.type !== 'body' ||
                          existing.turn_id === change.turn_id
                        : existing.type !== change.type),
            ),
            change,
        ]);
    };

    const removeChange = (id: number): void => {
        setChanges((current) =>
            current.filter((existing) => existing.id !== id),
        );
        setDecisionErrors((current) => {
            const next = { ...current };
            delete next[id];

            return next;
        });
    };

    const patchEntry = (
        key: string,
        patch: (entry: AssistantChatEntry) => AssistantChatEntry,
    ): void => {
        setThread((current) =>
            current.map((entry) => (entry.key === key ? patch(entry) : entry)),
        );
    };

    const applyStartedFrame = (
        frame: Extract<AssistantStreamFrame, { event: 'started' }>,
        taskType: string,
    ): void => {
        updateRuntime((current) => ({
            ...current,
            available: true,
            status: 'running',
            retry_at: null,
            active_turn: {
                id: frame.data.turn_id,
                task_type: taskType,
                started_at: new Date().toISOString(),
            },
        }));
    };

    const applyErrorFrame = (
        frame: Extract<AssistantStreamFrame, { event: 'error' }>,
    ): void => {
        const unavailableCodes = [
            'authentication_failed',
            'oauth_org_not_allowed',
            'billing_error',
            'model_not_found',
            'assistant_unavailable',
        ];
        const coolingDown =
            frame.data.retry_at !== undefined && frame.data.retry_at !== null;

        updateRuntime((current) => ({
            ...current,
            available:
                !unavailableCodes.includes(frame.data.code) && !coolingDown,
            status: unavailableCodes.includes(frame.data.code)
                ? 'unavailable'
                : coolingDown
                  ? 'cooldown'
                  : 'idle',
            retry_at: frame.data.retry_at ?? null,
            active_turn: null,
        }));
    };

    const ensureCanStart = async (): Promise<boolean> => {
        if (!hydratedRef.current && !(await refreshState(true))) {
            toast.error('The assistant status could not be loaded.');

            return false;
        }

        const current = runtimeRef.current;

        if (!current.enabled || current.status === 'disabled') {
            toast.error('The assistant is disabled.');

            return false;
        }

        if (!current.available || current.status === 'unavailable') {
            toast.error(
                stateError ??
                    'The assistant is unavailable. Check its server sign-in.',
            );

            return false;
        }

        if (current.status === 'cooldown') {
            toast.error(assistantCooldownMessage(current.retry_at));

            return false;
        }

        if (current.status !== 'idle' || stream.isActive()) {
            toast.error('The assistant is already working.');

            return false;
        }

        return true;
    };

    const startIsCurrent = (generation: number): boolean =>
        mountedRef.current && startGenerationRef.current === generation;

    const turnIsInProgress = (): boolean =>
        startingRef.current ||
        stream.isActive() ||
        ACTIVE_STATUSES.includes(runtimeRef.current.status);

    const prepareTurn = async (generation: number): Promise<boolean> => {
        if (!(await ensureCanStart())) {
            return false;
        }

        if (!startIsCurrent(generation)) {
            return false;
        }

        if (!(await ensureSaved())) {
            toast.error('Save the post before asking the assistant.');

            return false;
        }

        return startIsCurrent(generation);
    };

    const runConversationalTurn = async (
        url: string,
        body: Record<string, unknown>,
        displayText: string,
        taskType: 'chat' | 'outline',
    ): Promise<boolean> => {
        if (startingRef.current || pendingDecisionIdsRef.current.size > 0) {
            return false;
        }

        const generation = startGenerationRef.current + 1;
        startGenerationRef.current = generation;
        startingRef.current = true;
        setStarting(true);

        let prepared = false;

        try {
            prepared = await prepareTurn(generation);
        } catch (error) {
            toast.error(normalizeError(error).message);
        }

        if (
            !prepared ||
            !mountedRef.current ||
            startGenerationRef.current !== generation
        ) {
            if (
                mountedRef.current &&
                startGenerationRef.current === generation
            ) {
                startingRef.current = false;
                setStarting(false);
            }

            return false;
        }

        const requestId = crypto.randomUUID();
        ownedRequestRef.current = { postId, requestId };
        const assistantKey = `live-${requestId}-assistant`;
        liveEntryRef.current = assistantKey;
        setThread((current) => [
            ...current,
            {
                key: `live-${requestId}-user`,
                role: 'user',
                text: displayText,
                state: 'done',
                activity: null,
            },
            {
                key: assistantKey,
                role: 'assistant',
                text: '',
                state: 'streaming',
                activity: null,
            },
        ]);

        let succeeded = false;

        try {
            const completion = await stream.start(
                url,
                { ...body, request_id: requestId },
                (frame) => {
                    if (frame.event === 'started') {
                        applyStartedFrame(frame, taskType);
                    } else if (frame.event === 'narration') {
                        patchEntry(assistantKey, (entry) => ({
                            ...entry,
                            text:
                                entry.text === ''
                                    ? frame.data.text
                                    : `${entry.text}\n\n${frame.data.text}`,
                            activity: null,
                        }));
                    } else if (frame.event === 'activity') {
                        patchEntry(assistantKey, (entry) => ({
                            ...entry,
                            activity: frame.data.target
                                ? `${frame.data.tool} · ${frame.data.target}`
                                : frame.data.tool,
                        }));
                    } else if (frame.event === 'change') {
                        upsertChange(frame.data.change);
                    } else if (frame.event === 'error') {
                        applyErrorFrame(frame);
                        patchEntry(assistantKey, (entry) => ({
                            ...entry,
                            text: frame.data.message,
                            state: 'error',
                            activity: null,
                        }));
                    } else if (frame.event === 'done') {
                        patchEntry(assistantKey, (entry) => ({
                            ...entry,
                            state: 'done',
                            activity: null,
                        }));
                    }
                },
            );
            succeeded = completion.event === 'done';
        } catch (error) {
            const aborted = isAbortError(error);

            patchEntry(assistantKey, (entry) => ({
                ...entry,
                text:
                    entry.text === ''
                        ? aborted
                            ? 'Stopping…'
                            : normalizeError(error).message
                        : entry.text,
                state: aborted ? 'streaming' : 'error',
                activity: null,
            }));

            if (!aborted) {
                toast.error(normalizeError(error).message);
            }
        } finally {
            if (ownedRequestRef.current?.requestId === requestId) {
                ownedRequestRef.current = null;
            }

            if (
                mountedRef.current &&
                startGenerationRef.current === generation
            ) {
                startingRef.current = false;
                setStarting(false);
                await refreshState(true);
            }
        }

        return succeeded;
    };

    const sendChat = (message: string): Promise<boolean> =>
        runConversationalTurn(
            AssistantChatController.url(postId),
            { message },
            message,
            'chat',
        );

    const startOutline = (topic: string): Promise<boolean> =>
        runConversationalTurn(
            AssistantOutlineController.url(postId),
            { step: 'start', message: topic },
            topic,
            'outline',
        );

    const draftOutline = (): Promise<boolean> =>
        runConversationalTurn(
            AssistantOutlineController.url(postId),
            { step: 'draft', message: null },
            'Draft the article from the outline.',
            'outline',
        );

    const runOneShotTurn = async (
        url: string,
        body: Record<string, unknown>,
        workingMessage: string,
        taskType: 'images' | 'metadata' | 'transform',
    ): Promise<boolean> => {
        if (startingRef.current || pendingDecisionIdsRef.current.size > 0) {
            return false;
        }

        const generation = startGenerationRef.current + 1;
        startGenerationRef.current = generation;
        startingRef.current = true;
        setStarting(true);

        let prepared = false;

        try {
            prepared = await prepareTurn(generation);
        } catch (error) {
            toast.error(normalizeError(error).message);
        }

        if (
            !prepared ||
            !mountedRef.current ||
            startGenerationRef.current !== generation
        ) {
            if (
                mountedRef.current &&
                startGenerationRef.current === generation
            ) {
                startingRef.current = false;
                setStarting(false);
            }

            return false;
        }

        const requestId = crypto.randomUUID();
        ownedRequestRef.current = { postId, requestId };
        setNarration(workingMessage);
        let succeeded = false;

        try {
            const completion = await stream.start(
                url,
                { ...body, request_id: requestId },
                (frame) => {
                    if (frame.event === 'started') {
                        applyStartedFrame(frame, taskType);
                    } else if (frame.event === 'narration') {
                        setNarration(frame.data.text);
                    } else if (frame.event === 'change') {
                        upsertChange(frame.data.change);
                    } else if (frame.event === 'error') {
                        applyErrorFrame(frame);
                        toast.error(frame.data.message);
                    } else if (frame.event === 'done') {
                        const message =
                            frame.data.turn.assistant_message?.trim();

                        toast.success(
                            message
                                ? previewMessage(message)
                                : 'The AI review is ready.',
                        );
                    }
                },
            );
            succeeded = completion.event === 'done';
        } catch (error) {
            if (!isAbortError(error)) {
                toast.error(normalizeError(error).message);
            }
        } finally {
            if (ownedRequestRef.current?.requestId === requestId) {
                ownedRequestRef.current = null;
            }

            if (
                mountedRef.current &&
                startGenerationRef.current === generation
            ) {
                startingRef.current = false;
                setStarting(false);
                setNarration(null);
                await refreshState(false);
            }
        }

        return succeeded;
    };

    const reviewImages = (): Promise<boolean> =>
        runOneShotTurn(
            AssistantImageController.url(postId),
            {},
            'The AI is reviewing your images…',
            'images',
        );

    const generateMetadata = (fields: string[]): Promise<boolean> =>
        runOneShotTurn(
            AssistantMetadataController.url(postId),
            { fields },
            'The AI is reading your draft…',
            'metadata',
        );

    const transform = (
        selection: string,
        preset: TransformPreset,
        instruction?: string,
    ): Promise<boolean> =>
        runOneShotTurn(
            AssistantTransformController.url(postId),
            { selection, preset, instruction: instruction ?? null },
            'The AI is working on your selection…',
            'transform',
        );

    const markDecisionPending = (id: number, pending: boolean): void => {
        const next = new Set(pendingDecisionIdsRef.current);

        if (pending) {
            next.add(id);
        } else {
            next.delete(id);
        }

        pendingDecisionIdsRef.current = next;
        setPendingDecisionIds(next);
    };

    const accept = async (change: AssistantChangeData): Promise<boolean> => {
        if (pendingDecisionIdsRef.current.size > 0 || turnIsInProgress()) {
            return false;
        }

        markDecisionPending(change.id, true);
        setDecisionErrors((current) => {
            const next = { ...current };
            delete next[change.id];

            return next;
        });

        try {
            if (!(await ensureSaved())) {
                throw new AssistantRequestError(
                    'Save the post before accepting this suggestion.',
                    'post_save_failed',
                );
            }

            let responseError: AssistantRequestError | null = null;
            const response = await acceptRequest
                .post(
                    AssistantChangeController.accept.url({
                        post: postId,
                        change: change.id,
                    }),
                    {
                        onHttpException: (httpResponse) => {
                            responseError = errorFromHttpResponse(
                                httpResponse.status,
                                httpResponse.data,
                            );

                            return true;
                        },
                        onNetworkError: () => {
                            responseError = new AssistantRequestError(
                                'The suggestion could not be accepted. Try again.',
                                'network_error',
                            );

                            return true;
                        },
                    },
                )
                .catch((error: unknown) => {
                    throw responseError ?? normalizeError(error);
                });

            onAccepted(response.post);
            removeChange(change.id);

            return true;
        } catch (error) {
            const normalized = normalizeError(error);
            setDecisionErrors((current) => ({
                ...current,
                [change.id]: normalized.message,
            }));
            toast.error(normalized.message);
            await refreshState(false);

            return false;
        } finally {
            markDecisionPending(change.id, false);
        }
    };

    const reject = async (change: AssistantChangeData): Promise<boolean> => {
        if (pendingDecisionIdsRef.current.size > 0 || turnIsInProgress()) {
            return false;
        }

        markDecisionPending(change.id, true);
        setDecisionErrors((current) => {
            const next = { ...current };
            delete next[change.id];

            return next;
        });

        try {
            let responseError: AssistantRequestError | null = null;
            await rejectRequest
                .post(
                    AssistantChangeController.reject.url({
                        post: postId,
                        change: change.id,
                    }),
                    {
                        onHttpException: (httpResponse) => {
                            responseError = errorFromHttpResponse(
                                httpResponse.status,
                                httpResponse.data,
                            );

                            return true;
                        },
                        onNetworkError: () => {
                            responseError = new AssistantRequestError(
                                'The suggestion could not be dismissed. Try again.',
                                'network_error',
                            );

                            return true;
                        },
                    },
                )
                .catch((error: unknown) => {
                    throw responseError ?? normalizeError(error);
                });
            removeChange(change.id);

            return true;
        } catch (error) {
            const normalized = normalizeError(error);
            setDecisionErrors((current) => ({
                ...current,
                [change.id]: normalized.message,
            }));
            toast.error(normalized.message);
            await refreshState(false);

            return false;
        } finally {
            markDecisionPending(change.id, false);
        }
    };

    const cancel = async (): Promise<void> => {
        const localStreamActive = stream.isActive();
        const cancellingPreflight =
            startingRef.current &&
            !localStreamActive &&
            runtimeRef.current.active_turn === null;

        startGenerationRef.current += 1;
        startingRef.current = false;
        setStarting(false);
        setNarration(null);

        if (cancellingPreflight) {
            return;
        }

        const cancelledLocalStream = stream.cancel();

        if (!cancelledLocalStream && runtimeRef.current.active_turn === null) {
            return;
        }

        updateRuntime((current) => ({
            ...current,
            status: 'cancelling',
        }));

        const liveKey = liveEntryRef.current;

        if (liveKey !== null) {
            patchEntry(liveKey, (entry) => ({
                ...entry,
                text: entry.text === '' ? 'Stopping…' : entry.text,
                state: 'streaming',
                activity: 'Stopping…',
            }));
        }

        try {
            let responseError: AssistantRequestError | null = null;
            await cancelRequest
                .post(AssistantCancelController.url(postId), {
                    onHttpException: (response) => {
                        responseError = errorFromHttpResponse(
                            response.status,
                            response.data,
                        );

                        return true;
                    },
                    onNetworkError: () => {
                        responseError = new AssistantRequestError(
                            'The assistant could not be stopped. Its timeout is still active.',
                            'network_error',
                        );

                        return true;
                    },
                })
                .catch((error: unknown) => {
                    throw responseError ?? normalizeError(error);
                });
        } catch (error) {
            toast.error(normalizeError(error).message);
        } finally {
            await refreshState(true);
        }
    };

    const suggestions = useMemo(() => {
        const map: Partial<Record<AssistantChangeType, AssistantChangeData>> =
            {};

        for (const change of changes) {
            if (change.type !== 'body') {
                map[change.type] = change;
            }
        }

        return map;
    }, [changes]);

    const bodyChanges = useMemo(
        () => changes.filter((change) => change.type === 'body'),
        [changes],
    );

    const generating =
        starting ||
        stream.streaming ||
        ACTIVE_STATUSES.includes(runtime.status);
    const canGenerate =
        hydrated &&
        runtime.enabled &&
        runtime.available &&
        runtime.status === 'idle' &&
        !starting &&
        !stream.streaming &&
        pendingDecisionIds.size === 0;

    return {
        thread,
        suggestions,
        bodyChanges,
        narration,
        sendChat,
        startOutline,
        draftOutline,
        reviewImages,
        outlineReady: runtime.outline_ready,
        generateMetadata,
        transform,
        accept,
        reject,
        cancel,
        refresh: () => refreshState(true),
        generating,
        cancelling: runtime.status === 'cancelling',
        deciding: pendingDecisionIds.size > 0,
        pendingCount: changes.length,
        isDeciding: (changeId: number) =>
            generating ||
            pendingDecisionIds.has(changeId) ||
            pendingDecisionIds.size > 0,
        decisionError: (changeId: number) => decisionErrors[changeId] ?? null,
        hydrated,
        enabled: runtime.enabled,
        available: runtime.available,
        status: runtime.status,
        retryAt: runtime.retry_at,
        activeTurn: runtime.active_turn,
        stateError,
        canGenerate,
    };
}

function errorFromHttpResponse(
    status: number,
    data: unknown,
): AssistantRequestError {
    const payload = decodeHttpResponsePayload(data);

    return new AssistantRequestError(
        typeof payload.message === 'string'
            ? payload.message
            : defaultHttpError(status),
        typeof payload.code === 'string' ? payload.code : `http_${status}`,
        status,
        typeof payload.retry_at === 'string' ? payload.retry_at : null,
        typeof payload.turn_id === 'number' ? payload.turn_id : null,
    );
}

function normalizeError(error: unknown): AssistantRequestError {
    if (error instanceof AssistantRequestError) {
        return error;
    }

    if (error instanceof Error && error.message !== '') {
        return new AssistantRequestError(error.message);
    }

    return new AssistantRequestError('The assistant request failed.');
}

function defaultHttpError(status: number): string {
    if (status === 409) {
        return 'The assistant state changed. Refresh and try again.';
    }

    if (status === 419) {
        return 'Your session expired. Reload and try again.';
    }

    if (status === 422) {
        return 'This suggestion no longer matches the post.';
    }

    if (status === 429) {
        return 'The assistant is busy or cooling down. Try again shortly.';
    }

    if (status >= 500) {
        return 'The assistant is unavailable. Try again shortly.';
    }

    return 'The assistant request failed.';
}

function isAbortError(error: unknown): boolean {
    return (
        (error instanceof DOMException && error.name === 'AbortError') ||
        (error instanceof Error && error.name === 'AbortError')
    );
}

function previewMessage(message: string): string {
    return message.length <= 240 ? message : `${message.slice(0, 239)}…`;
}

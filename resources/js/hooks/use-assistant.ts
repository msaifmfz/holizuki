import { useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useMemo, useState } from 'react';
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
import { outlineReadyFromTurns, threadFromTurns } from '@/lib/assistant/thread';
import type { TransformPreset } from '@/lib/assistant/transforms';
import type {
    AcceptChangeResponse,
    AssistantChangeData,
    AssistantChangeType,
    AssistantChatEntry,
    AssistantStateResponse,
    AssistantStreamFrame,
} from '@/types';

/**
 * All assistant state for the editor: the co-writer conversation, streamed
 * turns, and the pending-change store shared by body change cards and field
 * suggestion chips. `ensureSaved` runs before every turn so the AI works
 * from the author's latest words; `onAccepted` folds an accepted change back
 * into the form.
 */
export function useAssistant({
    postId,
    ensureSaved,
    onAccepted,
}: {
    postId: number;
    ensureSaved: () => Promise<boolean>;
    onAccepted: (
        change: AssistantChangeData,
        post: AcceptChangeResponse['post'],
    ) => void;
}) {
    const stream = useSseStream();
    const [changes, setChanges] = useState<AssistantChangeData[]>([]);
    const [thread, setThread] = useState<AssistantChatEntry[]>([]);
    const [outlineReady, setOutlineReady] = useState(false);
    const [narration, setNarration] = useState<string | null>(null);
    const decisions = useHttp<Record<string, never>, AcceptChangeResponse>({});
    const stateRequest = useHttp<Record<string, never>, AssistantStateResponse>(
        {},
    );

    const refreshState = async (withThread: boolean) => {
        try {
            const response = await stateRequest.get(
                AssistantStateController.url(postId),
            );
            setChanges(response.changes);
            setOutlineReady(outlineReadyFromTurns(response.turns));

            if (withThread) {
                setThread(threadFromTurns(response.turns));
            }
        } catch {
            // The panel simply starts empty when hydration fails.
        }
    };

    const hydrate = useEffectEvent(() => void refreshState(true));

    useEffect(() => hydrate(), []);

    const upsertChange = (change: AssistantChangeData) => {
        setChanges((current) => [
            ...current.filter(
                (existing) =>
                    existing.id !== change.id &&
                    (change.type === 'body' || existing.type !== change.type),
            ),
            change,
        ]);
    };

    const removeChange = (id: number) => {
        setChanges((current) =>
            current.filter((existing) => existing.id !== id),
        );
    };

    const patchLiveEntry = (
        patch: (entry: AssistantChatEntry) => AssistantChatEntry,
    ) => {
        setThread((current) => {
            const next = [...current];
            const last = next[next.length - 1];

            if (
                last &&
                last.key.startsWith('live-') &&
                last.role === 'assistant'
            ) {
                next[next.length - 1] = patch(last);
            }

            return next;
        });
    };

    const runConversationalTurn = async (
        url: string,
        body: unknown,
        displayText: string,
    ) => {
        if (!(await ensureSaved())) {
            toast.error('Save the post before asking the assistant.');

            return;
        }

        const stamp = Date.now();
        setThread((current) => [
            ...current,
            {
                key: `live-${stamp}-user`,
                role: 'user',
                text: displayText,
                state: 'done',
                activity: null,
            },
            {
                key: `live-${stamp}-assistant`,
                role: 'assistant',
                text: '',
                state: 'streaming',
                activity: null,
            },
        ]);

        try {
            await stream.start(url, body, (frame) => {
                const typed = frame as AssistantStreamFrame;

                if (typed.event === 'narration') {
                    patchLiveEntry((entry) => ({
                        ...entry,
                        text:
                            entry.text === ''
                                ? typed.data.text
                                : `${entry.text}\n\n${typed.data.text}`,
                        activity: null,
                    }));
                } else if (typed.event === 'activity') {
                    patchLiveEntry((entry) => ({
                        ...entry,
                        activity: typed.data.target
                            ? `${typed.data.tool} · ${typed.data.target}`
                            : typed.data.tool,
                    }));
                } else if (typed.event === 'change') {
                    upsertChange(typed.data.change);
                } else if (typed.event === 'error') {
                    patchLiveEntry((entry) => ({
                        ...entry,
                        text: typed.data.message,
                        state: 'error',
                        activity: null,
                    }));
                } else if (typed.event === 'done') {
                    patchLiveEntry((entry) => ({
                        ...entry,
                        state: 'done',
                        activity: null,
                    }));
                }
            });
        } catch (error) {
            const aborted =
                error instanceof DOMException && error.name === 'AbortError';
            patchLiveEntry((entry) => ({
                ...entry,
                text: aborted
                    ? 'Stopped.'
                    : error instanceof Error
                      ? error.message
                      : 'The assistant request failed.',
                state: 'error',
                activity: null,
            }));
        }

        await refreshState(false);
    };

    const sendChat = (message: string) =>
        runConversationalTurn(
            AssistantChatController.url(postId),
            { message },
            message,
        );

    const startOutline = async (topic: string) => {
        await runConversationalTurn(
            AssistantOutlineController.url(postId),
            { step: 'start', message: topic },
            topic,
        );
        setOutlineReady(true);
    };

    const draftOutline = async () => {
        setOutlineReady(false);
        await runConversationalTurn(
            AssistantOutlineController.url(postId),
            { step: 'draft', message: null },
            'Draft the article from the outline.',
        );
    };

    // The one-shot flows (metadata, transform, images) share a shape: ensure
    // the draft is saved, stream the turn while surfacing its narration as a
    // live status line, and fold each proposed change into the store. Errors
    // toast unless the author simply cancelled the stream.
    const runOneShotTurn = async (
        url: string,
        body: unknown,
        workingMessage: string,
    ) => {
        if (!(await ensureSaved())) {
            toast.error('Save the post before asking the assistant.');

            return;
        }

        setNarration(workingMessage);

        try {
            await stream.start(url, body, (frame) => {
                const typed = frame as AssistantStreamFrame;

                if (typed.event === 'narration') {
                    setNarration(typed.data.text);
                } else if (typed.event === 'change') {
                    upsertChange(typed.data.change);
                } else if (typed.event === 'error') {
                    toast.error(typed.data.message);
                }
            });
        } catch (error) {
            if (!(
                error instanceof DOMException && error.name === 'AbortError'
            )) {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'The assistant request failed.',
                );
            }
        } finally {
            setNarration(null);
        }
    };

    const reviewImages = () =>
        runOneShotTurn(
            AssistantImageController.url(postId),
            {},
            'The AI is reviewing your images…',
        );

    const generateMetadata = (fields: string[]) =>
        runOneShotTurn(
            AssistantMetadataController.url(postId),
            { fields },
            'The AI is reading your draft…',
        );

    const transform = (
        selection: string,
        preset: TransformPreset,
        instruction?: string,
    ) =>
        runOneShotTurn(
            AssistantTransformController.url(postId),
            { selection, preset, instruction: instruction ?? null },
            'The AI is working on your selection…',
        );

    const accept = async (change: AssistantChangeData) => {
        try {
            const response = await decisions.post(
                AssistantChangeController.accept.url({
                    post: postId,
                    change: change.id,
                }),
                {
                    onHttpException: (httpResponse) => {
                        if (httpResponse.status === 422) {
                            removeChange(change.id);
                            toast.error(
                                'That suggestion is out of date — ask the assistant again.',
                            );
                        }
                    },
                },
            );

            onAccepted(change, response.post);
            removeChange(change.id);
        } catch {
            // Handled via onHttpException / toast above.
        }
    };

    const reject = async (change: AssistantChangeData) => {
        removeChange(change.id);

        try {
            await decisions.post(
                AssistantChangeController.reject.url({
                    post: postId,
                    change: change.id,
                }),
            );
        } catch {
            // Dismissal is best-effort; the proposal stays undecided server-side.
        }
    };

    const cancel = () => {
        stream.cancel();
        patchLiveEntry((entry) => ({
            ...entry,
            text: entry.text === '' ? 'Stopped.' : entry.text,
            state: 'error',
            activity: null,
        }));
        void decisions.post(AssistantCancelController.url(postId)).catch(() => {
            // Best-effort: the server also self-recovers via the turn timeout.
        });
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

    return {
        thread,
        suggestions,
        bodyChanges,
        narration,
        sendChat,
        startOutline,
        draftOutline,
        reviewImages,
        outlineReady,
        generateMetadata,
        transform,
        accept,
        reject,
        cancel,
        generating: stream.streaming,
        deciding: decisions.processing,
    };
}

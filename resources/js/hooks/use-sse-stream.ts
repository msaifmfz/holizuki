import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { postEventStream } from '@/lib/assistant/sse';
import { AssistantRequestError } from '@/lib/assistant/sse';
import type { AssistantStreamCompletion, SseFrame } from '@/lib/assistant/sse';

/**
 * Drives one assistant SSE request at a time. A monotonically increasing
 * generation prevents late frames/finalizers from an aborted request from
 * mutating the next request.
 */
export function useSseStream(onDisposeActive?: () => void) {
    const [streaming, setStreaming] = useState(false);
    const [turnId, setTurnId] = useState<number | null>(null);
    const abortRef = useRef<AbortController | null>(null);
    const generationRef = useRef(0);
    const disposedRef = useRef(false);
    const disposeActive = useEffectEvent(() => onDisposeActive?.());

    useEffect(() => {
        disposedRef.current = false;

        return () => {
            const wasActive = abortRef.current !== null;

            try {
                if (wasActive) {
                    disposeActive();
                }
            } finally {
                disposedRef.current = true;
                generationRef.current += 1;
                abortRef.current?.abort();
                abortRef.current = null;
            }
        };
    }, []);

    const start = async (
        url: string,
        body: unknown,
        onFrame: (frame: SseFrame) => void,
    ): Promise<AssistantStreamCompletion> => {
        if (disposedRef.current) {
            throw new AssistantRequestError(
                'This assistant request is no longer active.',
                'request_disposed',
            );
        }

        if (abortRef.current !== null) {
            throw new AssistantRequestError(
                'Another assistant request is already running.',
                'request_in_progress',
                409,
            );
        }

        const generation = generationRef.current + 1;
        generationRef.current = generation;
        const controller = new AbortController();
        abortRef.current = controller;
        setTurnId(null);
        setStreaming(true);

        try {
            return await postEventStream(
                url,
                body,
                (frame) => {
                    if (
                        disposedRef.current ||
                        generationRef.current !== generation
                    ) {
                        return;
                    }

                    if (frame.event === 'started') {
                        setTurnId(frame.data.turn_id);
                    }

                    onFrame(frame);
                },
                controller.signal,
            );
        } finally {
            if (
                abortRef.current === controller &&
                !disposedRef.current &&
                generationRef.current === generation
            ) {
                abortRef.current = null;
                setStreaming(false);
            }
        }
    };

    const cancel = (): boolean => {
        if (abortRef.current === null) {
            return false;
        }

        generationRef.current += 1;
        abortRef.current?.abort();
        abortRef.current = null;
        setStreaming(false);

        return true;
    };

    const isActive = (): boolean => abortRef.current !== null;

    return { streaming, turnId, start, cancel, isActive };
}

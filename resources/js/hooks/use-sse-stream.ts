import { useEffect, useRef, useState } from 'react';
import { postEventStream } from '@/lib/assistant/sse';
import type { SseFrame } from '@/lib/assistant/sse';

/**
 * Drives one assistant SSE request at a time: exposes streaming state, a
 * starter that aborts any in-flight stream, and cancellation that also fires
 * on unmount so a closed editor never leaves a hanging request.
 */
export function useSseStream() {
    const [streaming, setStreaming] = useState(false);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => () => abortRef.current?.abort(), []);

    const start = async (
        url: string,
        body: unknown,
        onFrame: (frame: SseFrame) => void,
    ): Promise<void> => {
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;
        setStreaming(true);

        try {
            await postEventStream(url, body, onFrame, controller.signal);
        } finally {
            if (abortRef.current === controller) {
                abortRef.current = null;
                setStreaming(false);
            }
        }
    };

    const cancel = () => {
        abortRef.current?.abort();
        abortRef.current = null;
        setStreaming(false);
    };

    return { streaming, start, cancel };
}

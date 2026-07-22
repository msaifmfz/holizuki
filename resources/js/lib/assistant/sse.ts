/**
 * Minimal SSE-over-fetch client for the assistant endpoints. The native
 * EventSource cannot POST, so frames are parsed off a streamed fetch body.
 */

export type SseFrame = { event: string; data: unknown };

/**
 * Extract complete `event:`/`data:` frames from an SSE buffer. Returns the
 * parsed frames plus the unconsumed remainder (a partial trailing frame).
 */
export function drainSseBuffer(buffer: string): {
    frames: SseFrame[];
    rest: string;
} {
    const frames: SseFrame[] = [];
    const normalized = buffer.replace(/\r\n/g, '\n');
    const segments = normalized.split('\n\n');
    const rest = segments.pop() ?? '';

    for (const segment of segments) {
        let event = 'message';
        const dataLines: string[] = [];

        for (const line of segment.split('\n')) {
            if (line.startsWith('event:')) {
                event = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
                dataLines.push(line.slice(5).trimStart());
            }
        }

        if (dataLines.length === 0) {
            continue;
        }

        try {
            frames.push({ event, data: JSON.parse(dataLines.join('\n')) });
        } catch {
            // Malformed frame data — skip rather than kill the stream.
        }
    }

    return { frames, rest };
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * POST to an SSE endpoint and invoke `onFrame` for every complete frame.
 * Resolves when the stream ends; rejects on network failure, a non-OK
 * response, or abort.
 */
export async function postEventStream(
    url: string,
    body: unknown,
    onFrame: (frame: SseFrame) => void,
    signal?: AbortSignal,
): Promise<void> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            // JSON first so guard failures (409 busy, 422, 404) render as JSON
            // the client can read; the streamed success sets its own
            // Content-Type and is unaffected by Accept.
            Accept: 'application/json, text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
        signal,
    });

    if (!response.ok || !response.body) {
        let message = 'The assistant request failed.';

        try {
            const payload = (await response.json()) as { message?: string };

            if (payload.message) {
                message = payload.message;
            }
        } catch {
            // Ignore non-JSON error bodies.
        }

        throw new Error(message);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });
        const { frames, rest } = drainSseBuffer(buffer);
        buffer = rest;
        frames.forEach(onFrame);
    }

    const { frames } = drainSseBuffer(buffer + '\n\n');
    frames.forEach(onFrame);
}

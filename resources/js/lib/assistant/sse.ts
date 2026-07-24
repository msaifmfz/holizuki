/**
 * Strict SSE-over-fetch client for assistant endpoints. Native EventSource
 * cannot POST, so the response body is parsed and validated here.
 */

import type {
    AssistantChangeData,
    AssistantErrorData,
    AssistantStreamFrame,
} from '@/types';

const DEFAULT_INACTIVITY_TIMEOUT_MS = 120_000;
const DEFAULT_MAX_BUFFER_CHARACTERS = 32 * 1024 * 1024;

export type SseFrame = AssistantStreamFrame;
export type AssistantStreamCompletion = Extract<
    AssistantStreamFrame,
    { event: 'done' | 'error' }
>;

export class AssistantRequestError extends Error {
    public constructor(
        message: string,
        public readonly code = 'assistant_request_failed',
        public readonly status: number | null = null,
        public readonly retryAt: string | null = null,
        public readonly turnId: number | null = null,
    ) {
        super(message);
        this.name = 'AssistantRequestError';
    }
}

export function assistantCooldownMessage(
    retryAt: string | null,
    now = new Date(),
): string {
    if (retryAt === null) {
        return 'The assistant is cooling down. Try again later.';
    }

    const date = new Date(retryAt);

    if (Number.isNaN(date.getTime())) {
        return 'The assistant is cooling down. Try again later.';
    }

    const sameDay =
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate();
    const formatted = sameDay
        ? date.toLocaleTimeString([], {
              hour: 'numeric',
              minute: '2-digit',
          })
        : date.toLocaleString([], {
              weekday: 'short',
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          });

    return `The assistant is cooling down until ${formatted}.`;
}

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

        let data: unknown;

        try {
            data = JSON.parse(dataLines.join('\n'));
        } catch {
            throw protocolError('The assistant sent malformed stream data.');
        }

        frames.push(validateFrame(event, data));
    }

    return { frames, rest };
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Best-effort cancellation used while the editor is unmounting. `keepalive`
 * lets the POST outlive navigation; the normal cancel action uses Inertia's
 * HTTP client so errors can be shown to the author.
 */
export function sendAssistantCancellation(
    url: string,
    body: { request_id?: string } = {},
): void {
    void fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    }).catch(() => undefined);
}

/**
 * POST to an SSE endpoint and invoke `onFrame` for every complete frame.
 * Resolves with the terminal frame. Rejects redirects, non-SSE responses,
 * malformed frames, turn mismatches, inactivity, missing/duplicate terminal
 * frames, network failures, and non-successful HTTP responses.
 */
export async function postEventStream(
    url: string,
    body: unknown,
    onFrame: (frame: SseFrame) => void,
    signal?: AbortSignal,
    options: {
        inactivityTimeoutMs?: number;
        maxBufferCharacters?: number;
    } = {},
): Promise<AssistantStreamCompletion> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        redirect: 'follow',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json, text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
        signal,
    });

    if (response.redirected) {
        throw new AssistantRequestError(
            'Your session changed while starting the assistant. Reload and try again.',
            'unexpected_redirect',
            response.status,
        );
    }

    if (!response.ok) {
        throw await errorFromResponse(response);
    }

    const contentType = response.headers
        .get('Content-Type')
        ?.split(';', 1)[0]
        .trim()
        .toLowerCase();

    if (contentType !== 'text/event-stream') {
        throw protocolError(
            'The assistant returned an unexpected response. Reload and try again.',
        );
    }

    if (!response.body) {
        throw protocolError('The assistant response did not include a stream.');
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let startedTurnId: number | null = null;
    let terminal: AssistantStreamCompletion | null = null;
    const inactivityTimeoutMs =
        options.inactivityTimeoutMs ?? DEFAULT_INACTIVITY_TIMEOUT_MS;
    const maxBufferCharacters =
        options.maxBufferCharacters ?? DEFAULT_MAX_BUFFER_CHARACTERS;

    const deliver = (frame: SseFrame): void => {
        if (terminal !== null) {
            throw protocolError(
                'The assistant sent data after the request had finished.',
            );
        }

        if (startedTurnId === null) {
            if (frame.event !== 'started') {
                throw protocolError(
                    'The assistant stream did not begin correctly.',
                );
            }

            startedTurnId = frame.data.turn_id;
        } else if (frame.event === 'started') {
            throw protocolError('The assistant stream started more than once.');
        } else if (frame.data.turn_id !== startedTurnId) {
            throw protocolError(
                'The assistant mixed events from different requests.',
            );
        }

        if (frame.event === 'done' || frame.event === 'error') {
            terminal = frame;
        }

        onFrame(frame);
    };

    try {
        for (;;) {
            const { done, value } = await readWithTimeout(
                reader,
                inactivityTimeoutMs,
            );

            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            if (buffer.length > maxBufferCharacters) {
                throw protocolError(
                    'The assistant sent a stream frame that was too large.',
                );
            }

            const { frames, rest } = drainSseBuffer(buffer);
            buffer = rest;
            frames.forEach(deliver);
        }

        buffer += decoder.decode();

        if (buffer.trim() !== '') {
            const { frames, rest } = drainSseBuffer(`${buffer}\n\n`);
            frames.forEach(deliver);

            if (rest.trim() !== '') {
                throw protocolError(
                    'The assistant stream ended with an incomplete frame.',
                );
            }
        }
    } catch (error) {
        await reader.cancel().catch(() => undefined);

        throw error;
    } finally {
        reader.releaseLock();
    }

    if (terminal === null) {
        throw protocolError(
            'The assistant connection closed before the request finished.',
        );
    }

    return terminal;
}

function validateFrame(event: string, data: unknown): SseFrame {
    if (!isRecord(data) || !isInteger(data.turn_id)) {
        throw protocolError(
            `The assistant sent an invalid ${event || 'unnamed'} event.`,
        );
    }

    const turnId = data.turn_id;

    switch (event) {
        case 'started':
        case 'heartbeat':
            return { event, data: { turn_id: turnId } };
        case 'narration':
            if (typeof data.text !== 'string') {
                break;
            }

            return { event, data: { turn_id: turnId, text: data.text } };
        case 'activity':
            if (
                typeof data.tool !== 'string' ||
                (data.target !== null && typeof data.target !== 'string')
            ) {
                break;
            }

            return {
                event,
                data: {
                    turn_id: turnId,
                    tool: data.tool,
                    target: data.target,
                },
            };
        case 'change':
            if (!isAssistantChange(data.change)) {
                break;
            }

            return {
                event,
                data: { turn_id: turnId, change: data.change },
            };
        case 'done':
            if (!isTurnResult(data.turn)) {
                break;
            }

            return { event, data: { turn_id: turnId, turn: data.turn } };
        case 'error':
            if (
                typeof data.code !== 'string' ||
                typeof data.message !== 'string'
            ) {
                break;
            }

            if (
                data.retry_at !== undefined &&
                data.retry_at !== null &&
                typeof data.retry_at !== 'string'
            ) {
                break;
            }

            return {
                event,
                data: {
                    turn_id: turnId,
                    code: data.code,
                    message: data.message,
                    retry_at: data.retry_at,
                },
            };
        default:
            throw protocolError(
                `The assistant sent an unsupported "${event}" event.`,
            );
    }

    throw protocolError(`The assistant sent an invalid ${event} event.`);
}

async function errorFromResponse(
    response: Response,
): Promise<AssistantRequestError> {
    let payload: Partial<AssistantErrorData> | null = null;

    try {
        const parsed: unknown = await response.json();

        if (isRecord(parsed)) {
            payload = parsed;
        }
    } catch {
        // The safe fallback below deliberately ignores HTML/proxy error bodies.
    }

    return new AssistantRequestError(
        typeof payload?.message === 'string'
            ? payload.message
            : 'The assistant request failed.',
        typeof payload?.code === 'string'
            ? payload.code
            : `http_${response.status}`,
        response.status,
        typeof payload?.retry_at === 'string' ? payload.retry_at : null,
        isInteger(payload?.turn_id) ? payload.turn_id : null,
    );
}

async function readWithTimeout(
    reader: ReadableStreamDefaultReader<Uint8Array>,
    timeoutMs: number,
): Promise<ReadableStreamReadResult<Uint8Array>> {
    if (!Number.isFinite(timeoutMs) || timeoutMs <= 0) {
        return reader.read();
    }

    let timeoutId: ReturnType<typeof setTimeout> | undefined;

    try {
        return await Promise.race([
            reader.read(),
            new Promise<never>((_, reject) => {
                timeoutId = setTimeout(() => {
                    reject(
                        new AssistantRequestError(
                            'The assistant stopped responding. Try again.',
                            'stream_inactive',
                        ),
                    );
                }, timeoutMs);
            }),
        ]);
    } finally {
        if (timeoutId !== undefined) {
            clearTimeout(timeoutId);
        }
    }
}

function isAssistantChange(value: unknown): value is AssistantChangeData {
    return (
        isRecord(value) &&
        isInteger(value.id) &&
        typeof value.type === 'string' &&
        typeof value.status === 'string' &&
        isRecord(value.payload) &&
        isInteger(value.turn_id)
    );
}

function isTurnResult(value: unknown): value is {
    id: number;
    status: string;
    assistant_message: string | null;
    duration_ms: number | null;
} {
    return (
        isRecord(value) &&
        isInteger(value.id) &&
        typeof value.status === 'string' &&
        (value.assistant_message === null ||
            typeof value.assistant_message === 'string') &&
        (value.duration_ms === null || isInteger(value.duration_ms))
    );
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value);
}

function protocolError(message: string): AssistantRequestError {
    return new AssistantRequestError(message, 'invalid_stream');
}

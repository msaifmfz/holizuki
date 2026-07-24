import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    assistantCooldownMessage,
    drainSseBuffer,
    postEventStream,
    sendAssistantCancellation,
} from './sse';

const DONE_FRAME =
    'event: done\n' +
    'data: {"turn_id":7,"turn":{"id":7,"status":"completed","assistant_message":"Done.","duration_ms":10}}\n\n';

afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
});

describe('drainSseBuffer', () => {
    it('parses complete validated frames and keeps the partial remainder', () => {
        const buffer =
            'event: started\ndata: {"turn_id":7}\n\n' +
            'event: narration\ndata: {"turn_id":7,"text":"Reading."}\n\n' +
            'event: heartbeat\ndata: {"turn_id":7';

        const { frames, rest } = drainSseBuffer(buffer);

        expect(frames).toEqual([
            { event: 'started', data: { turn_id: 7 } },
            {
                event: 'narration',
                data: { turn_id: 7, text: 'Reading.' },
            },
        ]);
        expect(rest).toBe('event: heartbeat\ndata: {"turn_id":7');
    });

    it('normalizes CRLF line endings', () => {
        const { frames } = drainSseBuffer(
            'event: heartbeat\r\ndata: {"turn_id":7}\r\n\r\n',
        );

        expect(frames).toEqual([{ event: 'heartbeat', data: { turn_id: 7 } }]);
    });

    it('ignores comment heartbeats and frames without data', () => {
        const { frames } = drainSseBuffer(
            ': heartbeat\n\nevent: heartbeat\n\n',
        );

        expect(frames).toEqual([]);
    });

    it('rejects malformed JSON instead of silently dropping it', () => {
        expect(() =>
            drainSseBuffer('event: narration\ndata: {not-json}\n\n'),
        ).toThrow('malformed stream data');
    });

    it('rejects unknown and structurally invalid events', () => {
        expect(() =>
            drainSseBuffer('event: surprise\ndata: {"turn_id":7}\n\n'),
        ).toThrow('unsupported');
        expect(() =>
            drainSseBuffer('event: narration\ndata: {"turn_id":7}\n\n'),
        ).toThrow('invalid narration');
    });

    it('joins multi-line data payloads', () => {
        const { frames } = drainSseBuffer(
            'event: heartbeat\ndata: {"turn_id":\ndata: 7}\n\n',
        );

        expect(frames).toEqual([{ event: 'heartbeat', data: { turn_id: 7 } }]);
    });
});

describe('assistantCooldownMessage', () => {
    it('includes the reset date when the cooldown ends on another day', () => {
        const message = assistantCooldownMessage(
            '2026-07-27T18:30:00Z',
            new Date('2026-07-24T12:00:00Z'),
        );

        expect(message).toContain('Jul');
        expect(message).toContain('27');
    });
});

describe('sendAssistantCancellation', () => {
    it('sends a keepalive cancellation scoped to the owned request', () => {
        mockFetch(new Response('{}'));

        sendAssistantCancellation('/assistant/cancel', {
            request_id: '123e4567-e89b-12d3-a456-426614174000',
        });

        expect(fetch).toHaveBeenCalledWith(
            '/assistant/cancel',
            expect.objectContaining({
                method: 'POST',
                keepalive: true,
                body: JSON.stringify({
                    request_id: '123e4567-e89b-12d3-a456-426614174000',
                }),
            }),
        );
    });
});

describe('postEventStream', () => {
    it('delivers one turn and resolves with its terminal frame', async () => {
        mockFetch(
            streamResponse([
                'event: started\ndata: {"turn_id":7}\n\n',
                'event: heartbeat\ndata: {"turn_id":7}\n\n',
                'event: narration\ndata: {"turn_id":7,"text":"Hello"}\n\n',
                DONE_FRAME,
            ]),
        );
        const frames: unknown[] = [];

        const terminal = await postEventStream(
            '/assistant',
            { message: 'Hello' },
            (frame) => frames.push(frame),
        );

        expect(terminal.event).toBe('done');
        expect(frames).toHaveLength(4);
        expect(fetch).toHaveBeenCalledWith(
            '/assistant',
            expect.objectContaining({
                method: 'POST',
                redirect: 'follow',
            }),
        );
    });

    it('rejects a successful response that is not an SSE stream', async () => {
        mockFetch(
            new Response('{}', {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );

        await expect(
            postEventStream('/assistant', {}, vi.fn()),
        ).rejects.toMatchObject({ code: 'invalid_stream' });
    });

    it('preserves structured HTTP error details', async () => {
        mockFetch(
            new Response(
                JSON.stringify({
                    code: 'usage_limit',
                    message: 'Claude is cooling down.',
                    retry_at: '2026-07-24T18:30:00Z',
                    turn_id: 22,
                }),
                {
                    status: 429,
                    headers: { 'Content-Type': 'application/json' },
                },
            ),
        );

        await expect(
            postEventStream('/assistant', {}, vi.fn()),
        ).rejects.toMatchObject({
            code: 'usage_limit',
            status: 429,
            retryAt: '2026-07-24T18:30:00Z',
            turnId: 22,
        });
    });

    it('rejects redirects before accepting their response body', async () => {
        const response = streamResponse([
            'event: started\ndata: {"turn_id":7}\n\n',
            DONE_FRAME,
        ]);
        Object.defineProperty(response, 'redirected', { value: true });
        mockFetch(response);

        await expect(
            postEventStream('/assistant', {}, vi.fn()),
        ).rejects.toMatchObject({ code: 'unexpected_redirect' });
    });

    it('requires a started frame followed by exactly one terminal frame', async () => {
        mockFetch(
            streamResponse([
                'event: started\ndata: {"turn_id":7}\n\n',
                'event: heartbeat\ndata: {"turn_id":7}\n\n',
            ]),
        );

        await expect(
            postEventStream('/assistant', {}, vi.fn()),
        ).rejects.toThrow('closed before');

        mockFetch(
            streamResponse([
                'event: started\ndata: {"turn_id":7}\n\n',
                DONE_FRAME,
                'event: heartbeat\ndata: {"turn_id":7}\n\n',
            ]),
        );

        await expect(
            postEventStream('/assistant', {}, vi.fn()),
        ).rejects.toThrow('after the request had finished');
    });

    it('rejects events from another turn', async () => {
        mockFetch(
            streamResponse([
                'event: started\ndata: {"turn_id":7}\n\n',
                'event: heartbeat\ndata: {"turn_id":8}\n\n',
            ]),
        );

        await expect(
            postEventStream('/assistant', {}, vi.fn()),
        ).rejects.toThrow('different requests');
    });

    it('bounds an incomplete frame buffer', async () => {
        mockFetch(
            streamResponse([
                'event: started\ndata: {"turn_id":7}\n\n',
                `event: narration\ndata: {"turn_id":7,"text":"${'x'.repeat(
                    100,
                )}`,
            ]),
        );

        await expect(
            postEventStream('/assistant', {}, vi.fn(), undefined, {
                maxBufferCharacters: 50,
            }),
        ).rejects.toThrow('too large');
    });

    it('times out an inactive stream', async () => {
        vi.useFakeTimers();
        const response = new Response(
            new ReadableStream<Uint8Array>({
                start() {
                    // Deliberately never enqueue or close.
                },
            }),
            {
                status: 200,
                headers: { 'Content-Type': 'text/event-stream' },
            },
        );
        mockFetch(response);

        const request = postEventStream('/assistant', {}, vi.fn(), undefined, {
            inactivityTimeoutMs: 100,
        });
        const expectation = expect(request).rejects.toMatchObject({
            code: 'stream_inactive',
        });

        await vi.advanceTimersByTimeAsync(100);
        await expectation;
    });
});

function mockFetch(response: Response): void {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(response);
}

function streamResponse(chunks: string[]): Response {
    const encoder = new TextEncoder();

    return new Response(
        new ReadableStream<Uint8Array>({
            start(controller) {
                for (const chunk of chunks) {
                    controller.enqueue(encoder.encode(chunk));
                }

                controller.close();
            },
        }),
        {
            status: 200,
            headers: { 'Content-Type': 'text/event-stream; charset=UTF-8' },
        },
    );
}

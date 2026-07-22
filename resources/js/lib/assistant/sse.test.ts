import { describe, expect, it } from 'vitest';
import { drainSseBuffer } from './sse';

describe('drainSseBuffer', () => {
    it('parses complete frames and keeps the partial remainder', () => {
        const buffer =
            'event: narration\ndata: {"text":"Reading the draft."}\n\n' +
            'event: change\ndata: {"change":{"id":1}}\n\n' +
            'event: done\ndata: {"tu';

        const { frames, rest } = drainSseBuffer(buffer);

        expect(frames).toEqual([
            { event: 'narration', data: { text: 'Reading the draft.' } },
            { event: 'change', data: { change: { id: 1 } } },
        ]);
        expect(rest).toBe('event: done\ndata: {"tu');
    });

    it('returns everything as remainder when no frame is complete', () => {
        const { frames, rest } = drainSseBuffer('event: narration\ndata: {}');

        expect(frames).toEqual([]);
        expect(rest).toBe('event: narration\ndata: {}');
    });

    it('normalizes CRLF line endings', () => {
        const { frames } = drainSseBuffer(
            'event: done\r\ndata: {"ok":true}\r\n\r\n',
        );

        expect(frames).toEqual([{ event: 'done', data: { ok: true } }]);
    });

    it('skips heartbeat comments and frames without data', () => {
        const { frames } = drainSseBuffer(': heartbeat\n\nevent: ping\n\n');

        expect(frames).toEqual([]);
    });

    it('skips frames whose data is not valid JSON', () => {
        const { frames } = drainSseBuffer(
            'event: broken\ndata: {nope}\n\nevent: fine\ndata: 1\n\n',
        );

        expect(frames).toEqual([{ event: 'fine', data: 1 }]);
    });

    it('defaults the event name to message', () => {
        const { frames } = drainSseBuffer('data: {"a":1}\n\n');

        expect(frames).toEqual([{ event: 'message', data: { a: 1 } }]);
    });

    it('joins multi-line data payloads', () => {
        const { frames } = drainSseBuffer('event: x\ndata: [1,\ndata: 2]\n\n');

        expect(frames).toEqual([{ event: 'x', data: [1, 2] }]);
    });
});

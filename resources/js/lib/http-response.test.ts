import { describe, expect, it } from 'vitest';
import { decodeHttpResponsePayload } from '@/lib/http-response';

describe('decodeHttpResponsePayload', () => {
    it('decodes the raw JSON text returned by the Inertia HTTP client', () => {
        expect(
            decodeHttpResponsePayload(
                JSON.stringify({
                    code: 'rate_limit',
                    message: 'Try again later.',
                }),
            ),
        ).toEqual({
            code: 'rate_limit',
            message: 'Try again later.',
        });
    });

    it('accepts already-decoded object test doubles', () => {
        const payload = { message: 'Conflict', conflict: { lock_version: 2 } };

        expect(decodeHttpResponsePayload(payload)).toBe(payload);
    });

    it.each(['', '<html>Bad gateway</html>', '{broken'])(
        'uses an empty safe payload for malformed response text',
        (data) => {
            expect(decodeHttpResponsePayload(data)).toEqual({});
        },
    );

    it.each([null, 42, [], '"message"'])(
        'rejects decoded non-object payloads',
        (data) => {
            expect(decodeHttpResponsePayload(data)).toEqual({});
        },
    );
});

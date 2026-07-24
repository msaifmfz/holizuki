import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import * as assistantSse from '@/lib/assistant/sse';
import { useSseStream } from './use-sse-stream';

const postEventStreamMock = vi.spyOn(assistantSse, 'postEventStream');

describe('useSseStream', () => {
    beforeEach(() => {
        postEventStreamMock.mockReset();
    });

    it('rejects an overlapping start instead of replacing the active request', async () => {
        postEventStreamMock.mockReturnValue(new Promise(() => {}));
        const { result } = renderHook(() => useSseStream());

        act(() => {
            void result.current.start('/first', {}, vi.fn());
        });

        await expect(
            result.current.start('/second', {}, vi.fn()),
        ).rejects.toMatchObject({
            code: 'request_in_progress',
        });
        expect(postEventStreamMock).toHaveBeenCalledOnce();
        expect(result.current.streaming).toBe(true);
    });

    it('ignores a cancelled generation finalizing after a new stream starts', async () => {
        let finishFirst: (() => void) | null = null;
        let finishSecond: (() => void) | null = null;
        postEventStreamMock
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishFirst = () =>
                            resolve({
                                event: 'done',
                                data: {
                                    turn_id: 1,
                                    turn: {
                                        id: 1,
                                        status: 'completed',
                                        assistant_message: null,
                                        duration_ms: 1,
                                    },
                                },
                            });
                    }),
            )
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishSecond = () =>
                            resolve({
                                event: 'done',
                                data: {
                                    turn_id: 2,
                                    turn: {
                                        id: 2,
                                        status: 'completed',
                                        assistant_message: null,
                                        duration_ms: 1,
                                    },
                                },
                            });
                    }),
            );
        const { result } = renderHook(() => useSseStream());

        let first: Promise<unknown>;
        await act(async () => {
            first = result.current.start('/first', {}, vi.fn());
            result.current.cancel();
            void result.current.start('/second', {}, vi.fn());
        });

        await act(async () => {
            finishFirst?.();
            await first!;
        });
        expect(result.current.streaming).toBe(true);

        await act(async () => {
            finishSecond?.();
        });
        expect(result.current.streaming).toBe(false);
    });

    it('cannot launch a request after its owner unmounts', async () => {
        const { result, unmount } = renderHook(() => useSseStream());

        unmount();

        await expect(
            result.current.start('/assistant', {}, vi.fn()),
        ).rejects.toMatchObject({
            code: 'request_disposed',
        });
        expect(postEventStreamMock).not.toHaveBeenCalled();
    });

    it('notifies its owner before disposing a locally active stream', () => {
        postEventStreamMock.mockReturnValue(new Promise(() => {}));
        const onDisposeActive = vi.fn();
        const { result, unmount } = renderHook(() =>
            useSseStream(onDisposeActive),
        );

        act(() => {
            void result.current.start('/assistant', {}, vi.fn());
        });
        unmount();

        expect(onDisposeActive).toHaveBeenCalledOnce();
    });

    it('does not notify its owner when only an observed remote turn exists', () => {
        const onDisposeActive = vi.fn();
        const { unmount } = renderHook(() => useSseStream(onDisposeActive));

        unmount();

        expect(onDisposeActive).not.toHaveBeenCalled();
    });
});

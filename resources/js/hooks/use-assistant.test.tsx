import type { HttpResponse } from '@inertiajs/core';
import { act, renderHook, waitFor } from '@testing-library/react';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type * as AssistantSseModule from '@/lib/assistant/sse';
import type {
    AcceptChangeResponse,
    AssistantChangeData,
    AssistantStateResponse,
} from '@/types';
import { useAssistant } from './use-assistant';

const mocks = vi.hoisted(() => {
    const stateGet = vi.fn();
    const acceptPost = vi.fn();
    const rejectPost = vi.fn();
    const cancelPost = vi.fn();
    const streamStart = vi.fn();
    const streamCancel = vi.fn(() => false);
    const streamIsActive = vi.fn(() => false);
    const sendAssistantCancellation = vi.fn();
    let disposeActive: (() => void) | undefined;
    const forms = [
        { get: stateGet },
        { post: acceptPost },
        { post: rejectPost },
        { post: cancelPost },
    ];
    let useHttpCall = 0;

    return {
        stateGet,
        acceptPost,
        rejectPost,
        cancelPost,
        streamStart,
        streamCancel,
        streamIsActive,
        sendAssistantCancellation,
        forms,
        setDisposeActive: (callback: (() => void) | undefined) => {
            disposeActive = callback;
        },
        disposeActive: () => disposeActive?.(),
        nextForm: () => forms[useHttpCall++ % forms.length],
        resetForms: () => {
            useHttpCall = 0;
            disposeActive = undefined;
        },
    };
});

vi.mock('@inertiajs/react', () => ({
    useHttp: vi.fn(() => mocks.nextForm()),
}));

vi.mock('@/hooks/use-sse-stream', () => ({
    useSseStream: (onDisposeActive?: () => void) => {
        mocks.setDisposeActive(onDisposeActive);

        return {
            streaming: false,
            turnId: null,
            start: mocks.streamStart,
            cancel: mocks.streamCancel,
            isActive: mocks.streamIsActive,
        };
    },
}));

vi.mock('@/lib/assistant/sse', async (importOriginal) => ({
    ...(await importOriginal<typeof AssistantSseModule>()),
    sendAssistantCancellation: mocks.sendAssistantCancellation,
}));

vi.mock('sonner', () => ({
    toast: { error: vi.fn(), success: vi.fn() },
}));

const idleState = (
    changes: AssistantChangeData[] = [],
): AssistantStateResponse => ({
    enabled: true,
    available: true,
    status: 'idle',
    retry_at: null,
    active_turn: null,
    outline_ready: false,
    changes,
    turns: [],
});

const titleChange = (id: number): AssistantChangeData => ({
    id,
    turn_id: 1,
    type: 'title',
    status: 'proposed',
    payload: { old: 'Old', new: `New ${id}` },
});

const acceptedPost = (
    overrides: Partial<AcceptChangeResponse['post']> = {},
): AcceptChangeResponse['post'] => ({
    lock_version: 2,
    slug: 'post',
    updated_at: '2026-07-24T00:00:00Z',
    last_editor: null,
    title: 'New title',
    slug_is_manual: false,
    excerpt: null,
    category_id: null,
    author_id: null,
    seo_title: null,
    meta_description: null,
    canonical_url: null,
    og_title: null,
    og_description: null,
    noindex: false,
    featured_image_alt: null,
    featured_image_caption: null,
    tags: [],
    body: null,
    ...overrides,
});

type HttpExceptionOptions = {
    onHttpException?: (response: HttpResponse) => boolean | void;
};

describe('useAssistant lifecycle', () => {
    beforeEach(() => {
        mocks.resetForms();
        mocks.stateGet.mockReset();
        mocks.acceptPost.mockReset();
        mocks.rejectPost.mockReset();
        mocks.cancelPost.mockReset();
        mocks.streamStart.mockReset();
        mocks.streamCancel.mockReset().mockReturnValue(false);
        mocks.streamIsActive.mockReset().mockReturnValue(false);
        mocks.sendAssistantCancellation.mockReset();
        mocks.stateGet.mockResolvedValue(idleState());
        vi.mocked(toast.error).mockClear();
    });

    it('cancels a start that is still waiting for autosave', async () => {
        let finishSave: ((saved: boolean) => void) | undefined;
        const ensureSaved = vi.fn(
            () =>
                new Promise<boolean>((resolve) => {
                    finishSave = resolve;
                }),
        );
        const { result } = renderHook(() =>
            useAssistant({
                postId: 7,
                ensureSaved,
                onAccepted: vi.fn(),
            }),
        );

        await waitFor(() => expect(result.current.canGenerate).toBe(true));

        let turn = Promise.resolve(false);
        act(() => {
            turn = result.current.sendChat('Help');
        });
        await waitFor(() => expect(result.current.generating).toBe(true));

        await act(async () => {
            await result.current.cancel();
            finishSave?.(true);
            await turn;
        });

        expect(mocks.streamStart).not.toHaveBeenCalled();
        expect(mocks.cancelPost).not.toHaveBeenCalled();
    });

    it('does not launch after unmounting during preflight', async () => {
        let finishSave: ((saved: boolean) => void) | undefined;
        const ensureSaved = vi.fn(
            () =>
                new Promise<boolean>((resolve) => {
                    finishSave = resolve;
                }),
        );
        const { result, unmount } = renderHook(() =>
            useAssistant({
                postId: 7,
                ensureSaved,
                onAccepted: vi.fn(),
            }),
        );

        await waitFor(() => expect(result.current.canGenerate).toBe(true));

        let turn = Promise.resolve(false);
        act(() => {
            turn = result.current.sendChat('Help');
        });
        await waitFor(() => expect(ensureSaved).toHaveBeenCalledOnce());
        unmount();

        finishSave?.(true);
        await turn;

        expect(mocks.streamStart).not.toHaveBeenCalled();
    });

    it('serializes decisions across different proposals', async () => {
        const firstChange = titleChange(1);
        const secondChange = titleChange(2);
        mocks.stateGet.mockResolvedValue(
            idleState([firstChange, secondChange]),
        );
        let finishAccept:
            ((response: AcceptChangeResponse) => void) | undefined;
        mocks.acceptPost.mockReturnValue(
            new Promise<AcceptChangeResponse>((resolve) => {
                finishAccept = resolve;
            }),
        );
        const onAccepted = vi.fn();
        const { result } = renderHook(() =>
            useAssistant({
                postId: 7,
                ensureSaved: vi.fn().mockResolvedValue(true),
                onAccepted,
            }),
        );

        await waitFor(() => expect(result.current.pendingCount).toBe(2));

        let firstDecision = Promise.resolve(false);
        act(() => {
            firstDecision = result.current.accept(firstChange);
        });

        await expect(result.current.accept(secondChange)).resolves.toBe(false);
        expect(mocks.acceptPost).toHaveBeenCalledOnce();

        finishAccept?.({
            change: { ...firstChange, status: 'accepted' },
            post: acceptedPost({ title: 'New 1' }),
        });

        await expect(firstDecision).resolves.toBe(true);
        expect(onAccepted).toHaveBeenCalledOnce();
    });

    it('blocks a new turn while a proposal decision is pending', async () => {
        const change = titleChange(1);
        mocks.stateGet.mockResolvedValue(idleState([change]));
        let finishAccept:
            ((response: AcceptChangeResponse) => void) | undefined;
        mocks.acceptPost.mockReturnValue(
            new Promise<AcceptChangeResponse>((resolve) => {
                finishAccept = resolve;
            }),
        );
        const { result } = renderHook(() =>
            useAssistant({
                postId: 7,
                ensureSaved: vi.fn().mockResolvedValue(true),
                onAccepted: vi.fn(),
            }),
        );

        await waitFor(() => expect(result.current.pendingCount).toBe(1));

        let decision = Promise.resolve(false);
        act(() => {
            decision = result.current.accept(change);
        });
        await waitFor(() => expect(result.current.deciding).toBe(true));

        await expect(
            result.current.sendChat('Start another turn'),
        ).resolves.toBe(false);
        expect(result.current.canGenerate).toBe(false);
        expect(mocks.streamStart).not.toHaveBeenCalled();

        await act(async () => {
            finishAccept?.({
                change: { ...change, status: 'accepted' },
                post: acceptedPost(),
            });
            await decision;
        });
    });

    it('blocks proposal decisions while a turn is starting', async () => {
        const change = titleChange(1);
        mocks.stateGet.mockResolvedValue(idleState([change]));
        let finishSave: ((saved: boolean) => void) | undefined;
        const ensureSaved = vi.fn(
            () =>
                new Promise<boolean>((resolve) => {
                    finishSave = resolve;
                }),
        );
        const { result } = renderHook(() =>
            useAssistant({
                postId: 7,
                ensureSaved,
                onAccepted: vi.fn(),
            }),
        );

        await waitFor(() => expect(result.current.pendingCount).toBe(1));

        let turn = Promise.resolve(false);
        act(() => {
            turn = result.current.sendChat('Start a turn');
        });
        await waitFor(() => expect(result.current.generating).toBe(true));

        await expect(result.current.accept(change)).resolves.toBe(false);
        await expect(result.current.reject(change)).resolves.toBe(false);
        expect(result.current.isDeciding(change.id)).toBe(true);
        expect(mocks.acceptPost).not.toHaveBeenCalled();
        expect(mocks.rejectPost).not.toHaveBeenCalled();

        await act(async () => {
            finishSave?.(false);
            await turn;
        });
    });

    it('scopes disposal cancellation to the locally owned request ID', async () => {
        let finishStream:
            ((completion: { event: 'done'; data: object }) => void) | undefined;
        mocks.streamStart.mockReturnValue(
            new Promise((resolve) => {
                finishStream = resolve;
            }),
        );
        const { result } = renderHook(() =>
            useAssistant({
                postId: 7,
                ensureSaved: vi.fn().mockResolvedValue(true),
                onAccepted: vi.fn(),
            }),
        );

        await waitFor(() => expect(result.current.canGenerate).toBe(true));

        let turn = Promise.resolve(false);
        act(() => {
            turn = result.current.sendChat('Keep ownership scoped');
        });
        await waitFor(() => expect(mocks.streamStart).toHaveBeenCalledOnce());

        const requestBody = mocks.streamStart.mock.calls[0]?.[1] as {
            request_id: string;
        };
        act(() => {
            mocks.disposeActive();
        });

        expect(mocks.sendAssistantCancellation).toHaveBeenCalledWith(
            expect.any(String),
            { request_id: requestBody.request_id },
        );

        await act(async () => {
            finishStream?.({ event: 'done', data: {} });
            await turn;
        });
    });

    it('decodes assistant errors from Inertia raw response text', async () => {
        const change = titleChange(1);
        mocks.stateGet.mockResolvedValue(idleState([change]));
        mocks.acceptPost.mockImplementation(
            (_url: string, options: HttpExceptionOptions) => {
                options.onHttpException?.({
                    status: 409,
                    data: JSON.stringify({
                        code: 'stale_change',
                        message: 'This suggestion no longer matches the post.',
                    }),
                    headers: {},
                });

                return Promise.reject(new Error('Request failed'));
            },
        );
        const { result } = renderHook(() =>
            useAssistant({
                postId: 7,
                ensureSaved: vi.fn().mockResolvedValue(true),
                onAccepted: vi.fn(),
            }),
        );

        await waitFor(() => expect(result.current.pendingCount).toBe(1));

        await act(async () => {
            await expect(result.current.accept(change)).resolves.toBe(false);
        });

        expect(result.current.decisionError(change.id)).toBe(
            'This suggestion no longer matches the post.',
        );
        expect(toast.error).toHaveBeenCalledWith(
            'This suggestion no longer matches the post.',
        );
    });
    it('keeps a safe fallback when assistant error text is malformed', async () => {
        const change = titleChange(1);
        mocks.stateGet.mockResolvedValue(idleState([change]));
        mocks.acceptPost.mockImplementation(
            (_url: string, options: HttpExceptionOptions) => {
                options.onHttpException?.({
                    status: 503,
                    data: '<html>Proxy failure</html>',
                    headers: {},
                });

                return Promise.reject(new Error('Request failed'));
            },
        );
        const { result } = renderHook(() =>
            useAssistant({
                postId: 7,
                ensureSaved: vi.fn().mockResolvedValue(true),
                onAccepted: vi.fn(),
            }),
        );

        await waitFor(() => expect(result.current.pendingCount).toBe(1));

        await act(async () => {
            await expect(result.current.accept(change)).resolves.toBe(false);
        });

        expect(result.current.decisionError(change.id)).toBe(
            'The assistant is unavailable. Try again shortly.',
        );
        expect(toast.error).not.toHaveBeenCalledWith(
            expect.stringContaining('Proxy failure'),
        );
    });
});

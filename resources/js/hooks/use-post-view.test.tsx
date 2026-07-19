import { useHttp } from '@inertiajs/react';
import { act, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { usePostView } from '@/hooks/use-post-view';

vi.mock('@inertiajs/react', () => ({
    useHttp: vi.fn(),
}));

function Harness({ slug }: { slug: string }) {
    usePostView(slug);

    return null;
}

describe('usePostView', () => {
    const post = vi.fn().mockResolvedValue(undefined);

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-07-18T12:00:00Z'));
        post.mockClear();
        window.sessionStorage.clear();
        setDocumentVisibility(false);
        vi.mocked(useHttp).mockReturnValue({ post } as never);
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('records a view only after ten visible seconds', async () => {
        render(<Harness slug="reader-experience" />);

        await act(() => vi.advanceTimersByTimeAsync(9_999));
        expect(post).not.toHaveBeenCalled();

        await act(() => vi.advanceTimersByTimeAsync(1));

        expect(post).toHaveBeenCalledOnce();
        expect(post).toHaveBeenCalledWith('/posts/reader-experience/views');
        expect(
            window.sessionStorage.getItem(
                'post-view:reader-experience:2026-07-18',
            ),
        ).toBe('sent');
    });

    it('accumulates visible time without counting hidden time', async () => {
        render(<Harness slug="visibility" />);

        await act(() => vi.advanceTimersByTimeAsync(4_000));
        setDocumentVisibility(true);
        act(() => document.dispatchEvent(new Event('visibilitychange')));

        await act(() => vi.advanceTimersByTimeAsync(30_000));
        expect(post).not.toHaveBeenCalled();

        setDocumentVisibility(false);
        act(() => document.dispatchEvent(new Event('visibilitychange')));
        await act(() => vi.advanceTimersByTimeAsync(5_999));
        expect(post).not.toHaveBeenCalled();

        await act(() => vi.advanceTimersByTimeAsync(1));
        expect(post).toHaveBeenCalledOnce();
    });

    it('does not send the same slug twice during a browser session and UTC day', async () => {
        const first = render(<Harness slug="once" />);

        await act(() => vi.advanceTimersByTimeAsync(10_000));
        expect(post).toHaveBeenCalledOnce();

        first.unmount();
        render(<Harness slug="once" />);
        await act(() => vi.advanceTimersByTimeAsync(20_000));

        expect(post).toHaveBeenCalledOnce();
    });

    it('can count the same session again on the next UTC day', async () => {
        const first = render(<Harness slug="daily" />);

        await act(() => vi.advanceTimersByTimeAsync(10_000));
        first.unmount();

        vi.setSystemTime(new Date('2026-07-19T12:00:00Z'));
        render(<Harness slug="daily" />);
        await act(() => vi.advanceTimersByTimeAsync(10_000));

        expect(post).toHaveBeenCalledTimes(2);
    });
});

function setDocumentVisibility(hidden: boolean): void {
    Object.defineProperty(document, 'hidden', {
        configurable: true,
        value: hidden,
    });
}

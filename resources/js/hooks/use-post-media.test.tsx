import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { AuthoringMediaData } from '@/types';
import { usePostMedia } from './use-post-media';

const mocks = vi.hoisted(() => {
    const listGet = vi.fn();
    const updatePatch = vi.fn();
    const forms = [
        { get: listGet, processing: false },
        { post: vi.fn(), processing: false, transform: vi.fn() },
        { patch: updatePatch, processing: false, transform: vi.fn() },
        { delete: vi.fn(), processing: false, transform: vi.fn() },
        { post: vi.fn(), processing: false, transform: vi.fn() },
    ];
    let call = 0;

    return {
        listGet,
        updatePatch,
        nextForm: () => forms[call++ % forms.length],
        reset: () => {
            call = 0;
            listGet.mockReset();
            updatePatch.mockReset();
        },
    };
});

vi.mock('@inertiajs/react', () => ({
    useHttp: vi.fn(() => mocks.nextForm()),
}));

const media: AuthoringMediaData = {
    id: 7,
    url: '/storage/story.webp',
    width: 1200,
    height: 800,
    display_name: 'Story photo',
    alt_text: '',
    caption: '',
    role: 'supporting',
    placement: 'body',
    status: 'ready',
    story_priority: 'optional',
    story_notes: '',
    used_in_draft: false,
    error: null,
};

describe('usePostMedia', () => {
    beforeEach(() => {
        mocks.reset();
        mocks.listGet.mockResolvedValue({ media: [media] });
    });

    it('keeps failed image edits pending until that field saves successfully', async () => {
        mocks.updatePatch
            .mockRejectedValueOnce(new Error('network failure'))
            .mockResolvedValueOnce({
                media: {
                    ...media,
                    story_notes: 'The corrected context.',
                },
            });
        const { result } = renderHook(() => usePostMedia(10));

        await waitFor(() => expect(result.current.hydrated).toBe(true));

        await act(async () => {
            expect(
                await result.current.updateMedia(media, {
                    story_notes: 'The corrected context.',
                }),
            ).toBe(false);
        });

        expect(result.current.hasPendingChanges).toBe(true);
        await expect(result.current.flush()).resolves.toBe(false);

        await act(async () => {
            expect(
                await result.current.updateMedia(media, {
                    story_notes: 'The corrected context.',
                }),
            ).toBe(true);
        });

        expect(result.current.hasPendingChanges).toBe(false);
        await expect(result.current.flush()).resolves.toBe(true);
    });

    it('does not let an older success clear a newer failed edit to the same field', async () => {
        mocks.updatePatch
            .mockResolvedValueOnce({
                media: { ...media, story_notes: 'Older edit.' },
            })
            .mockRejectedValueOnce(new Error('newer edit failed'));
        const { result } = renderHook(() => usePostMedia(10));

        await waitFor(() => expect(result.current.hydrated).toBe(true));

        await act(async () => {
            const older = result.current.updateMedia(media, {
                story_notes: 'Older edit.',
            });
            const newer = result.current.updateMedia(media, {
                story_notes: 'Newer edit.',
            });

            await expect(Promise.all([older, newer])).resolves.toEqual([
                true,
                false,
            ]);
        });

        expect(result.current.hasPendingChanges).toBe(true);
        await expect(result.current.flush()).resolves.toBe(false);
    });
});

import { describe, expect, it } from 'vitest';
import type { EditorForm } from '@/components/editor/types';
import {
    buildReviewChecks,
    guidedSaveLabel,
    storyPhotoFlowIsReady,
    storyPhotosFrom,
} from '@/lib/guided-authoring';
import type { AuthoringImageComposition, AuthoringMediaData } from '@/types';

function draft(overrides: Partial<EditorForm> = {}): EditorForm {
    return {
        title: 'A finished title',
        slug: 'a-finished-title',
        slug_is_manual: false,
        excerpt: 'A short summary.',
        body: {
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Readable body text.' }],
                },
            ],
        },
        featured_image_alt: 'Descriptive alt text',
        featured_image_caption: '',
        category_id: 4,
        author_id: 1,
        tags: [],
        seo_title: '',
        meta_description: '',
        canonical_url: '',
        og_title: '',
        og_description: '',
        noindex: false,
        lock_version: 0,
        force: false,
        ...overrides,
    };
}

function photo(
    id: number,
    overrides: Partial<AuthoringMediaData> = {},
): AuthoringMediaData {
    return {
        id,
        url: `https://example.test/${id}.jpg`,
        width: 800,
        height: 600,
        display_name: `Photo ${id}`,
        alt_text: '',
        caption: '',
        role: 'unassigned',
        placement: 'unassigned',
        status: 'ready',
        story_priority: 'optional',
        story_notes: '',
        used_in_draft: false,
        error: null,
        ...overrides,
    };
}

function composition(
    overrides: Partial<AuthoringImageComposition> = {},
): AuthoringImageComposition {
    return {
        id: 'composition-1',
        usage: 'include',
        section_heading: 'Why gardens matter',
        position: 1,
        media: [photo(1)],
        selected_passage_id: null,
        passage_markdown: 'An approved passage.',
        passage_stale: false,
        ...overrides,
    } as AuthoringImageComposition;
}

describe('buildReviewChecks', () => {
    it('passes a complete draft', () => {
        const checks = buildReviewChecks(draft(), 'https://example.test/a.jpg');

        expect(checks.every((check) => check.complete)).toBe(true);
    });

    it('treats the placeholder title as unfinished', () => {
        const checks = buildReviewChecks(
            draft({ title: '  Untitled Post ' }),
            'https://example.test/a.jpg',
        );

        expect(checks.find((check) => check.id === 'title')?.complete).toBe(
            false,
        );
    });

    it('needs alt text alongside the featured image', () => {
        expect(
            buildReviewChecks(draft(), null).find(
                (check) => check.id === 'featured-image',
            )?.complete,
        ).toBe(false);
        expect(
            buildReviewChecks(
                draft({ featured_image_alt: '   ' }),
                'https://example.test/a.jpg',
            ).find((check) => check.id === 'featured-image')?.complete,
        ).toBe(false);
    });

    it('rejects a body with no readable text', () => {
        const checks = buildReviewChecks(
            draft({ body: { type: 'doc', content: [{ type: 'paragraph' }] } }),
            'https://example.test/a.jpg',
        );

        expect(checks.find((check) => check.id === 'body')?.complete).toBe(
            false,
        );
    });
});

describe('storyPhotosFrom', () => {
    it('leaves out the lead image however it is marked', () => {
        const featuredUrl = 'https://example.test/1.jpg';
        const photos = storyPhotosFrom(
            [
                photo(1),
                photo(2, { role: 'hero' }),
                photo(3, { placement: 'featured' }),
                photo(4),
            ],
            featuredUrl,
        );

        expect(photos.map((item) => item.id)).toEqual([4]);
    });
});

describe('storyPhotoFlowIsReady', () => {
    it('waits for the library and artifacts to load', () => {
        expect(storyPhotoFlowIsReady([photo(1)], [composition()], false)).toBe(
            false,
        );
    });

    it('is ready when there are no story photos to arrange', () => {
        expect(storyPhotoFlowIsReady([], [], true)).toBe(true);
    });

    it('is not ready while a photo is unplaced', () => {
        expect(
            storyPhotoFlowIsReady([photo(1), photo(2)], [composition()], true),
        ).toBe(false);
    });

    it('is not ready while an included photo lacks current writing', () => {
        expect(
            storyPhotoFlowIsReady(
                [photo(1)],
                [composition({ passage_markdown: '   ' })],
                true,
            ),
        ).toBe(false);
        expect(
            storyPhotoFlowIsReady(
                [photo(1)],
                [composition({ passage_stale: true })],
                true,
            ),
        ).toBe(false);
    });

    it('ignores writing on photos the author skipped', () => {
        expect(
            storyPhotoFlowIsReady(
                [photo(1)],
                [
                    composition({
                        usage: 'exclude',
                        section_heading: null,
                        passage_markdown: '',
                    }),
                ],
                true,
            ),
        ).toBe(true);
    });
});

describe('guidedSaveLabel', () => {
    it('asks for unsaved photo work first', () => {
        expect(
            guidedSaveLabel({
                photoChangesPending: true,
                editorSaveState: 'error',
                journeySaveState: 'error',
                savedAtLabel: 'just now',
            }),
        ).toBe('Save your photo changes before continuing');
    });

    it('reports a failure from either side', () => {
        expect(
            guidedSaveLabel({
                photoChangesPending: false,
                editorSaveState: 'saved',
                journeySaveState: 'error',
                savedAtLabel: 'just now',
            }),
        ).toBe('Some changes could not be saved');
    });

    it('falls back to when the work was last saved', () => {
        expect(
            guidedSaveLabel({
                photoChangesPending: false,
                editorSaveState: 'saved',
                journeySaveState: 'saved',
                savedAtLabel: 'just now',
            }),
        ).toBe('Saved just now');
    });
});

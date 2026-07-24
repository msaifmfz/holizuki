import { describe, expect, it } from 'vitest';
import type { EditorForm } from '@/components/editor/types';
import {
    postConflictFromHttpResponse,
    reconcileAcceptedChange,
    reconcileAutosave,
} from '@/lib/editor-save';
import type { AcceptChangeResponse } from '@/types';

const form = (overrides: Partial<EditorForm> = {}): EditorForm => ({
    title: 'Original',
    slug: 'original',
    slug_is_manual: false,
    excerpt: '',
    body: null,
    featured_image_alt: '',
    featured_image_caption: '',
    category_id: null,
    author_id: null,
    tags: [],
    seo_title: '',
    meta_description: '',
    canonical_url: '',
    og_title: '',
    og_description: '',
    noindex: false,
    lock_version: 1,
    force: false,
    ...overrides,
});

const savedPost = (
    overrides: Partial<AcceptChangeResponse['post']> = {},
): AcceptChangeResponse['post'] => ({
    lock_version: 2,
    slug: 'original',
    updated_at: '2026-07-24T00:00:00Z',
    last_editor: null,
    title: 'Suggested',
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

describe('editor save reconciliation', () => {
    it('keeps edits typed while autosave was in flight', () => {
        const submitted = form({ title: 'Submitted' });
        const latest = form({ title: 'Typed later' });
        const result = reconcileAutosave(latest, submitted, {
            slug: 'submitted',
            lock_version: 2,
            updated_at: '2026-07-24T00:00:00Z',
            last_editor: null,
        });

        expect(result.data.title).toBe('Typed later');
        expect(result.defaults.title).toBe('Submitted');
        expect(result.data.lock_version).toBe(2);
        expect(result.hasPendingChanges).toBe(true);
    });

    it('preserves a field edited while accepting an AI proposal', () => {
        const baseline = form();
        const latest = form({ title: 'My newer title' });
        const result = reconcileAcceptedChange(latest, baseline, savedPost());

        expect(result.data.title).toBe('My newer title');
        expect(result.defaults.title).toBe('Suggested');
        expect(result.data.lock_version).toBe(2);
        expect(result.hasPendingChanges).toBe(true);
    });

    it('applies an accepted proposal when its target stayed untouched', () => {
        const baseline = form();
        const result = reconcileAcceptedChange(baseline, baseline, savedPost());

        expect(result.data.title).toBe('Suggested');
        expect(result.hasPendingChanges).toBe(false);
    });

    it('adopts unrelated server edits for every locally untouched field', () => {
        const baseline = form();
        const result = reconcileAcceptedChange(
            baseline,
            baseline,
            savedPost({
                slug: 'server-slug',
                slug_is_manual: true,
                excerpt: 'Edited in another tab',
                category_id: 8,
                author_id: 9,
                canonical_url: 'https://example.com/server',
                noindex: true,
            }),
        );

        expect(result.data).toMatchObject({
            title: 'Suggested',
            slug: 'server-slug',
            slug_is_manual: true,
            excerpt: 'Edited in another tab',
            category_id: 8,
            author_id: 9,
            canonical_url: 'https://example.com/server',
            noindex: true,
            lock_version: 2,
            force: false,
        });
        expect(result.defaults).toEqual(result.data);
        expect(result.hasPendingChanges).toBe(false);
    });

    it('keeps in-flight local edits dirty against the complete server snapshot', () => {
        const baseline = form();
        const latest = form({
            excerpt: 'My unsaved local edit',
            canonical_url: 'https://example.com/local',
        });
        const result = reconcileAcceptedChange(
            latest,
            baseline,
            savedPost({
                excerpt: 'Edited on the server',
                category_id: 8,
                canonical_url: 'https://example.com/server',
                noindex: true,
            }),
        );

        expect(result.data).toMatchObject({
            title: 'Suggested',
            excerpt: 'My unsaved local edit',
            category_id: 8,
            canonical_url: 'https://example.com/local',
            noindex: true,
            lock_version: 2,
            force: false,
        });
        expect(result.defaults).toMatchObject({
            excerpt: 'Edited on the server',
            category_id: 8,
            canonical_url: 'https://example.com/server',
            noindex: true,
            lock_version: 2,
            force: false,
        });
        expect(result.hasPendingChanges).toBe(true);
    });

    it('reads a post conflict from Inertia raw response text', () => {
        expect(
            postConflictFromHttpResponse(
                JSON.stringify({
                    conflict: {
                        lock_version: 7,
                        updated_at: '2026-07-24T00:00:00Z',
                        last_editor: 'Owner',
                    },
                }),
                2,
            ),
        ).toEqual({
            lock_version: 7,
            updated_at: '2026-07-24T00:00:00Z',
            last_editor: 'Owner',
        });
    });

    it('returns a safe blocking conflict for malformed response text', () => {
        expect(
            postConflictFromHttpResponse('<html>Bad gateway</html>', 4),
        ).toEqual({
            lock_version: 4,
            updated_at: null,
            last_editor: null,
        });
    });
});

import { describe, expect, it } from 'vitest';
import {
    buildTableOfContents,
    getRichTextContent,
    normalizeTableOfContents,
    safeHeadingId,
    safeImageUrl,
    safeLinkUrl,
    slugifyHeading,
} from '@/lib/public-rich-text';
import type { RichTextDocument } from '@/types';

const document: RichTextDocument = {
    type: 'doc',
    content: [
        {
            type: 'heading',
            attrs: { level: 1 },
            content: [{ type: 'text', text: 'Ignored title' }],
        },
        {
            type: 'heading',
            attrs: { level: 2 },
            content: [{ type: 'text', text: 'Café queues' }],
        },
        {
            type: 'heading',
            attrs: { level: 3 },
            content: [{ type: 'text', text: 'Café queues' }],
        },
        {
            type: 'heading',
            attrs: { level: 2, id: 'custom:heading' },
            content: [{ type: 'text', text: 'Explicit identifier' }],
        },
    ],
};

describe('public rich text helpers', () => {
    it('extracts nested text and creates readable heading slugs', () => {
        expect(getRichTextContent(document)).toContain('Café queues');
        expect(slugifyHeading('  Café & queues!  ')).toBe('cafe-queues');
        expect(slugifyHeading('✨')).toBe('section');
    });

    it('builds a duplicate-safe table of contents from h2 and h3 headings', () => {
        expect(buildTableOfContents(document)).toEqual([
            { id: 'cafe-queues', title: 'Café queues', level: 2 },
            { id: 'cafe-queues-2', title: 'Café queues', level: 3 },
            {
                id: 'custom:heading',
                title: 'Explicit identifier',
                level: 2,
            },
        ]);
    });

    it('uses generated entries when none are supplied and filters unsafe entries', () => {
        expect(normalizeTableOfContents(document, [])).toEqual(
            buildTableOfContents(document),
        );

        expect(
            normalizeTableOfContents(document, [
                { id: 'valid-heading', title: '  Valid heading  ', level: 2 },
                { id: 'bad id', title: 'Unsafe', level: 2 },
                { id: 'h4', title: 'Wrong level', level: 4 as 2 },
            ]),
        ).toEqual([{ id: 'valid-heading', title: 'Valid heading', level: 2 }]);
    });

    it('allows only safe link image and heading values', () => {
        expect(safeLinkUrl('/archive')).toBe('/archive');
        expect(safeLinkUrl('#section')).toBe('#section');
        expect(safeLinkUrl('mailto:reader@example.com')).toBe(
            'mailto:reader@example.com',
        );
        expect(safeLinkUrl('javascript:alert(1)')).toBeNull();
        expect(safeLinkUrl('data:text/html,bad')).toBeNull();

        expect(safeImageUrl('/storage/posts/image.webp')).toBe(
            '/storage/posts/image.webp',
        );
        expect(safeImageUrl('https://cdn.example.com/image.webp')).toBe(
            'https://cdn.example.com/image.webp',
        );
        expect(safeImageUrl('data:image/svg+xml,bad')).toBeNull();

        expect(safeHeadingId('section:one')).toBe('section:one');
        expect(safeHeadingId('bad id')).toBeNull();
        expect(safeHeadingId('" onclick="alert(1)')).toBeNull();
    });
});

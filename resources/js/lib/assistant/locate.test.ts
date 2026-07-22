import { getSchema } from '@tiptap/core';
import Document from '@tiptap/extension-document';
import Heading from '@tiptap/extension-heading';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import { EditorState } from '@tiptap/pm/state';
import { describe, expect, it } from 'vitest';
import { findTextRange, plainTextNeedle } from './locate';

const schema = getSchema([Document, Paragraph, Text, Heading]);

function docFromText(...paragraphs: string[]) {
    return schema.nodeFromJSON({
        type: 'doc',
        content: paragraphs.map((text) => ({
            type: 'paragraph',
            content: text ? [{ type: 'text', text }] : [],
        })),
    });
}

describe('plainTextNeedle', () => {
    it('strips heading, emphasis, and code markers', () => {
        expect(plainTextNeedle('## The **bold** and `code` title')).toBe(
            'The bold and code title',
        );
    });

    it('keeps link text but drops the url', () => {
        expect(plainTextNeedle('See [the docs](https://example.com) now')).toBe(
            'See the docs now',
        );
    });

    it('drops list and blockquote markers', () => {
        expect(plainTextNeedle('> quoted wisdom')).toBe('quoted wisdom');
        expect(plainTextNeedle('- a bullet point')).toBe('a bullet point');
        expect(plainTextNeedle('3. third item')).toBe('third item');
    });

    it('uses only the first block of a multi-block payload', () => {
        expect(plainTextNeedle('First paragraph.\n\nSecond paragraph.')).toBe(
            'First paragraph.',
        );
    });

    it('unescapes backslash-escaped markdown', () => {
        expect(plainTextNeedle('a literal \\# hash sign')).toBe(
            'a literal # hash sign',
        );
    });

    it('returns empty for image-only blocks', () => {
        expect(plainTextNeedle('![alt text](media:12 "caption")')).toBe('');
    });
});

describe('findTextRange', () => {
    it('locates a phrase and returns a range covering it', () => {
        const doc = docFromText(
            'An intro.',
            'The target sentence.',
            'An outro.',
        );
        const range = findTextRange(doc, 'The target sentence.');

        expect(range).not.toBeNull();
        expect(doc.textBetween(range!.from, range!.to)).toBe(
            'The target sentence.',
        );
    });

    it('is tolerant of collapsed whitespace differences', () => {
        const doc = docFromText('Words   with    irregular spacing');
        const range = findTextRange(doc, 'Words with irregular spacing');

        expect(range).not.toBeNull();
    });

    it('falls back to a leading slice when the full block drifted', () => {
        const doc = docFromText(
            'This opening clause is stable but the tail has changed entirely.',
        );
        const range = findTextRange(
            doc,
            'This opening clause is stable but the original ending was different.',
        );

        expect(range).not.toBeNull();
        expect(doc.textBetween(range!.from, range!.to)).toContain(
            'This opening clause is stable',
        );
    });

    it('returns null when nothing matches', () => {
        const doc = docFromText('Completely unrelated content.');

        expect(
            findTextRange(doc, 'nowhere to be found in this document'),
        ).toBeNull();
    });

    it('returns null for an empty needle', () => {
        const doc = docFromText('Anything.');

        expect(findTextRange(doc, '   ')).toBeNull();
    });

    it('produces a range TipTap can select', () => {
        const doc = docFromText('First.', 'Second passage here.');
        const range = findTextRange(doc, 'Second passage here.')!;

        // A valid selection range stays within the document bounds.
        const state = EditorState.create({ schema, doc });
        expect(range.from).toBeGreaterThan(0);
        expect(range.to).toBeLessThanOrEqual(state.doc.content.size);
    });
});

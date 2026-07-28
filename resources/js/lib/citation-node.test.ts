import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { describe, expect, it } from 'vitest';
import { PostCitation } from '@/lib/citation-node';

describe('post citation editor node', () => {
    it('numbers sources by first appearance and reuses numbers', () => {
        const element = document.createElement('div');
        const editor = new Editor({
            element,
            extensions: [StarterKit, PostCitation],
            content: {
                type: 'doc',
                content: [
                    {
                        type: 'paragraph',
                        content: [
                            { type: 'text', text: 'First' },
                            {
                                type: 'citation',
                                attrs: { sourceId: 12 },
                            },
                            {
                                type: 'citation',
                                attrs: { sourceId: 7 },
                            },
                            {
                                type: 'citation',
                                attrs: { sourceId: 12 },
                            },
                        ],
                    },
                ],
            },
        });

        const citations = element.querySelectorAll('[data-citation]');

        expect(citations).toHaveLength(3);
        expect(
            [...citations].map((citation) =>
                citation.getAttribute('data-citation-number'),
            ),
        ).toEqual(['1', '2', '1']);
        expect(editor.getJSON().content?.[0].content?.[0]).toEqual({
            type: 'text',
            text: 'First',
        });
        expect(editor.getJSON().content?.[0].content?.[1]).toEqual({
            type: 'citation',
            attrs: { sourceId: 12 },
        });

        editor.destroy();
    });
});

describe('post citation parsing', () => {
    it('drops pasted citations that carry no usable source id', () => {
        const element = document.createElement('div');
        const editor = new Editor({
            element,
            extensions: [StarterKit, PostCitation],
            content:
                '<p>Claim<span data-citation data-source-id="0"></span>' +
                '<span data-citation data-source-id="oops"></span>' +
                '<span data-citation data-source-id="9"></span></p>',
        });

        const citations: Array<number | null> = [];
        editor.state.doc.descendants((node) => {
            if (node.type.name === 'citation') {
                citations.push(node.attrs.sourceId);
            }
        });

        // Only the well-formed one survives, so the document can never reach
        // the server carrying a citation it will reject.
        expect(citations).toEqual([9]);

        editor.destroy();
    });
});

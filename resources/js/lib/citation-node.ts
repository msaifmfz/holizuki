import { mergeAttributes, Node } from '@tiptap/core';
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';

const citationPluginKey = new PluginKey('postCitationNumbers');

export const PostCitation = Node.create({
    name: 'citation',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    marks: '',

    addAttributes() {
        return {
            sourceId: {
                default: null,
                parseHTML: (element) =>
                    positiveInteger(element.getAttribute('data-source-id')),
                renderHTML: (attributes) => {
                    const sourceId = positiveInteger(attributes.sourceId);

                    return sourceId
                        ? { 'data-source-id': sourceId.toString() }
                        : {};
                },
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'span[data-citation][data-source-id]',
                // Pasted markup without a usable source id would produce a
                // citation the server rejects, failing the whole save with an
                // error the author cannot act on. Refuse the node instead.
                getAttrs: (element) => {
                    const sourceId = positiveInteger(
                        element.getAttribute('data-source-id'),
                    );

                    return sourceId === null ? false : { sourceId };
                },
            },
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'span',
            mergeAttributes(HTMLAttributes, {
                'data-citation': '',
                contenteditable: 'false',
            }),
        ];
    },

    renderText({ node }) {
        const sourceId = positiveInteger(node.attrs.sourceId);

        return sourceId ? `[](source:${sourceId})` : '';
    },

    addProseMirrorPlugins() {
        return [
            new Plugin({
                key: citationPluginKey,
                props: {
                    decorations: (state) => citationDecorations(state.doc),
                },
            }),
        ];
    },
});

function citationDecorations(document: ProseMirrorNode): DecorationSet {
    const sourceNumbers = new Map<number, number>();
    const decorations: Decoration[] = [];

    document.descendants((node, position) => {
        if (node.type.name !== 'citation') {
            return;
        }

        const sourceId = positiveInteger(node.attrs.sourceId);

        if (!sourceId) {
            return;
        }

        if (!sourceNumbers.has(sourceId)) {
            sourceNumbers.set(sourceId, sourceNumbers.size + 1);
        }

        const number = sourceNumbers.get(sourceId);

        if (!number) {
            return;
        }

        decorations.push(
            Decoration.node(position, position + node.nodeSize, {
                'aria-label': `Citation ${number}`,
                'data-citation-number': number.toString(),
                title: `Citation [${number}]`,
            }),
        );
    });

    return DecorationSet.create(document, decorations);
}

function positiveInteger(value: unknown): number | null {
    const number = Number(value);

    return Number.isSafeInteger(number) && number > 0 ? number : null;
}

import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';

const locateHighlightKey = new PluginKey<DecorationSet>('locateHighlight');

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        locateHighlight: {
            /** Highlight a range and scroll it into view. */
            setLocateHighlight: (from: number, to: number) => ReturnType;
            /** Clear any active locate highlight. */
            clearLocateHighlight: () => ReturnType;
        };
    }
}

/**
 * A transient highlight over a document range, used to show the author where
 * an accepted or proposed change lives in the editor. The decoration is held
 * in plugin state and mapped through edits so it survives typing until it's
 * explicitly cleared.
 */
export const LocateHighlight = Extension.create({
    name: 'locateHighlight',

    addCommands() {
        return {
            setLocateHighlight:
                (from: number, to: number) =>
                ({ tr, dispatch }) => {
                    if (dispatch) {
                        dispatch(
                            tr
                                .setMeta(locateHighlightKey, { from, to })
                                .scrollIntoView(),
                        );
                    }

                    return true;
                },
            clearLocateHighlight:
                () =>
                ({ tr, dispatch }) => {
                    if (dispatch) {
                        dispatch(tr.setMeta(locateHighlightKey, null));
                    }

                    return true;
                },
        };
    },

    addProseMirrorPlugins() {
        return [
            new Plugin<DecorationSet>({
                key: locateHighlightKey,
                state: {
                    init: () => DecorationSet.empty,
                    apply(tr, current) {
                        const meta = tr.getMeta(locateHighlightKey) as
                            { from: number; to: number } | null | undefined;

                        if (meta === null) {
                            return DecorationSet.empty;
                        }

                        if (meta) {
                            return DecorationSet.create(tr.doc, [
                                Decoration.inline(meta.from, meta.to, {
                                    class: 'assistant-locate-highlight',
                                }),
                            ]);
                        }

                        return current.map(tr.mapping, tr.doc);
                    },
                },
                props: {
                    decorations(state) {
                        return this.getState(state);
                    },
                },
            }),
        ];
    },
});

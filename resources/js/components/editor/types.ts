import type { useHttp } from '@inertiajs/react';
import type { useAssistant } from '@/hooks/use-assistant';
import type { AutosaveResponse, RichTextDocument } from '@/types';

/** The autosaved editor form, shared by the page and its extracted cards. */
export type EditorForm = {
    title: string;
    slug: string;
    slug_is_manual: boolean;
    excerpt: string;
    body: RichTextDocument | null;
    featured_image_alt: string;
    featured_image_caption: string;
    category_id: number | null;
    author_id: number | null;
    tags: string[];
    seo_title: string;
    meta_description: string;
    canonical_url: string;
    og_title: string;
    og_description: string;
    noindex: boolean;
    lock_version: number;
    force: boolean;
};

/** The autosave form handle the page threads into its editor cards. */
export type EditorAutosave = ReturnType<
    typeof useHttp<EditorForm, AutosaveResponse>
>;

/** The assistant hook handle shared with editor cards. */
export type EditorAssistant = ReturnType<typeof useAssistant>;

/** Whether a rich-text document has any visible text. */
export function documentHasText(body: RichTextDocument | null): boolean {
    if (!body) {
        return false;
    }

    const walk = (node: { text?: string; content?: unknown[] }): boolean => {
        if (typeof node.text === 'string' && node.text.trim() !== '') {
            return true;
        }

        return (node.content ?? []).some(
            (child) =>
                typeof child === 'object' &&
                child !== null &&
                walk(child as { text?: string; content?: unknown[] }),
        );
    };

    return walk(body as { text?: string; content?: unknown[] });
}

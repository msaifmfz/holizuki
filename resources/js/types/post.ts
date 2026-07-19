import type { JSONContent } from '@tiptap/react';

type PostDisplayStatus = 'draft' | 'scheduled' | 'published' | 'trashed';
export type RichTextDocument = JSONContent;

export type PostSummary = {
    id: number;
    title: string;
    slug: string;
    status: PostDisplayStatus;
    author: string | null;
    last_editor: string | null;
    scheduled_at: string | null;
    published_at: string | null;
    updated_at: string | null;
};

export type EditorOption = { id: number; name: string };

export type PostEditorData = PostSummary & {
    category_id: number | null;
    author_id: number | null;
    tags: string[];
    excerpt: string | null;
    body: RichTextDocument | null;
    featured_image_url: string | null;
    featured_image_alt: string | null;
    featured_image_caption: string | null;
    featured_at: string | null;
    slug_is_manual: boolean;
    slug_locked_at: string | null;
    lock_version: number;
    created_at: string | null;
    seo_title: string | null;
    meta_description: string | null;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image_url: string | null;
    noindex: boolean;
};

export type PostPreviewData = {
    id: number;
    title: string;
    excerpt: string | null;
    body: RichTextDocument | null;
    featured_image_url: string | null;
    featured_image_alt: string | null;
    featured_image_caption: string | null;
    reading_time_minutes: number;
    published_at: string | null;
    updated_at: string | null;
};

export type PostRevisionSummary = {
    id: number;
    revision_number: number;
    event: string;
    editor: string;
    created_at: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

export type AutosaveResponse = {
    lock_version: number;
    slug: string;
    updated_at: string;
    last_editor: string | null;
};

export type PostConflict = {
    lock_version: number;
    updated_at: string | null;
    last_editor: string | null;
};

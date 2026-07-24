import type { EditorForm } from '@/components/editor/types';
import { decodeHttpResponsePayload } from '@/lib/http-response';
import type {
    AcceptChangeResponse,
    AutosaveResponse,
    PostConflict,
} from '@/types';

export type EditorSaveReconciliation = {
    data: EditorForm;
    defaults: EditorForm;
    hasPendingChanges: boolean;
};

/**
 * Advances the saved baseline to exactly what was submitted while preserving
 * edits made after the request started.
 */
export function reconcileAutosave(
    latest: EditorForm,
    submitted: EditorForm,
    response: AutosaveResponse,
): EditorSaveReconciliation {
    const defaults: EditorForm = {
        ...submitted,
        slug: response.slug,
        lock_version: response.lock_version,
        force: false,
    };
    const data: EditorForm = {
        ...latest,
        slug: latest.slug === submitted.slug ? response.slug : latest.slug,
        lock_version: response.lock_version,
        force: false,
    };

    return {
        data,
        defaults,
        hasPendingChanges: !editorFormsEqual(data, defaults),
    };
}

/**
 * Advances every untouched field to the accepted server snapshot while
 * preserving edits made locally during the decision request as dirty values.
 */
export function reconcileAcceptedChange(
    latest: EditorForm,
    baseline: EditorForm,
    saved: AcceptChangeResponse['post'],
): EditorSaveReconciliation {
    const defaults = editorFormFromAcceptedPost(saved);
    const data = Object.fromEntries(
        (Object.keys(defaults) as Array<keyof EditorForm>).map((key) => [
            key,
            key === 'lock_version' ||
            key === 'force' ||
            serializableValuesEqual(latest[key], baseline[key])
                ? defaults[key]
                : latest[key],
        ]),
    ) as EditorForm;

    return {
        data,
        defaults,
        hasPendingChanges: !editorFormsEqual(data, defaults),
    };
}

export function editorFormsEqual(left: EditorForm, right: EditorForm): boolean {
    return serializableValuesEqual(left, right);
}

export function postConflictFromHttpResponse(
    data: unknown,
    fallbackLockVersion: number,
): PostConflict {
    const payload = decodeHttpResponsePayload(data);
    const conflict = isRecord(payload.conflict) ? payload.conflict : {};

    return {
        lock_version:
            typeof conflict.lock_version === 'number' &&
            Number.isInteger(conflict.lock_version) &&
            conflict.lock_version >= 0
                ? conflict.lock_version
                : fallbackLockVersion,
        updated_at:
            typeof conflict.updated_at === 'string'
                ? conflict.updated_at
                : null,
        last_editor:
            typeof conflict.last_editor === 'string'
                ? conflict.last_editor
                : null,
    };
}

function editorFormFromAcceptedPost(
    saved: AcceptChangeResponse['post'],
): EditorForm {
    return {
        title: saved.title ?? '',
        slug: saved.slug,
        slug_is_manual: saved.slug_is_manual,
        excerpt: saved.excerpt ?? '',
        body: saved.body,
        featured_image_alt: saved.featured_image_alt ?? '',
        featured_image_caption: saved.featured_image_caption ?? '',
        category_id: saved.category_id,
        author_id: saved.author_id,
        tags: saved.tags,
        seo_title: saved.seo_title ?? '',
        meta_description: saved.meta_description ?? '',
        canonical_url: saved.canonical_url ?? '',
        og_title: saved.og_title ?? '',
        og_description: saved.og_description ?? '',
        noindex: saved.noindex,
        lock_version: saved.lock_version,
        force: false,
    };
}

function serializableValuesEqual(left: unknown, right: unknown): boolean {
    return JSON.stringify(left) === JSON.stringify(right);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

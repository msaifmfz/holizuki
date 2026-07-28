import { useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import {
    destroy,
    feature,
    index,
    store,
    update,
} from '@/actions/App/Http/Admin/Controllers/PostMediaController';
import type {
    AuthoringMediaData,
    AuthoringMediaPlacement,
    AuthoringMediaRole,
    AuthoringMediaStatus,
    AuthoringMediaStoryPriority,
    RichTextDocument,
} from '@/types';

type MediaResponse = { media: ApiPostMedia };
/**
 * Editing a used image's alt text or caption rewrites the draft body server
 * side, which bumps the post lock version. The rewritten body comes back so
 * the editor can adopt it instead of re-sending its stale copy.
 */
type MediaUpdateResponse = MediaResponse & {
    lock_version: number;
    body: RichTextDocument | null;
};
type MediaListResponse = { media: ApiPostMedia[] };
type FeaturedMediaResponse = MediaResponse & {
    featured_image_url: string;
    featured_image_alt: string | null;
    featured_image_caption: string | null;
    lock_version: number;
    updated_at: string;
};
type MediaUpdate = Partial<
    Pick<
        AuthoringMediaData,
        | 'display_name'
        | 'alt_text'
        | 'caption'
        | 'role'
        | 'placement'
        | 'story_priority'
        | 'story_notes'
    >
>;

type ApiPostMedia = {
    id: number;
    url: string;
    width: number | null;
    height: number | null;
    display_name: string | null;
    alt_text: string | null;
    caption: string | null;
    role: AuthoringMediaRole;
    placement: AuthoringMediaPlacement;
    status: AuthoringMediaStatus;
    story_priority: AuthoringMediaStoryPriority;
    story_notes: string | null;
    used_in_draft: boolean;
};

function normalizeMedia(media: ApiPostMedia): AuthoringMediaData {
    return {
        ...media,
        display_name: media.display_name ?? 'Untitled image',
        alt_text: media.alt_text ?? '',
        caption: media.caption ?? '',
        story_priority:
            media.used_in_draft === true ? 'required' : media.story_priority,
        story_notes: media.story_notes ?? '',
        error: null,
    };
}

export function usePostMedia(
    postId: number,
    onDraftBodyRewritten?: (
        lockVersion: number,
        body: RichTextDocument | null,
    ) => void,
) {
    const [media, setMedia] = useState<AuthoringMediaData[]>([]);
    const [hydrated, setHydrated] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [featuringMediaId, setFeaturingMediaId] = useState<number | null>(
        null,
    );
    const [hasPendingChanges, setHasPendingChanges] = useState(false);
    const dirtyUpdateKeys = useRef(new Map<string, number>());
    const updateGeneration = useRef(0);
    const pendingUpdates = useRef(new Set<Promise<boolean>>());
    const updateQueue = useRef<Promise<boolean>>(Promise.resolve(true));
    const listRequest = useHttp<Record<string, never>, MediaListResponse>({});
    const uploadRequest = useHttp<{ images: File[] }, MediaListResponse>({
        images: [],
    });
    const updateRequest = useHttp<MediaUpdate, MediaUpdateResponse>({});
    const deleteRequest = useHttp<{ discard_story_writing: boolean }, void>({
        discard_story_writing: false,
    });
    const featureRequest = useHttp<
        { lock_version: number },
        FeaturedMediaResponse
    >({ lock_version: 0 });

    const refresh = async (): Promise<void> => {
        try {
            const response = await listRequest.get(index.url(postId));
            setMedia(response.media.map(normalizeMedia));
            setError(null);
        } catch {
            setError('The image library could not be loaded.');
        } finally {
            setHydrated(true);
        }
    };

    const hydrate = useEffectEvent(() => {
        void refresh();
    });

    useEffect(() => {
        const timeout = window.setTimeout(() => hydrate(), 0);

        return () => window.clearTimeout(timeout);
    }, []);

    const upload = async (images: File[]): Promise<void> => {
        uploadRequest.transform(() => ({ images }));

        try {
            const response = await uploadRequest.post(store.url(postId));
            setMedia((current) => [
                ...response.media.map(normalizeMedia),
                ...current,
            ]);
            setError(null);
        } catch {
            setError(
                'The images could not be uploaded. Use JPG, PNG, or WebP files up to 5 MB each.',
            );

            throw new Error('The images could not be uploaded.');
        }
    };

    const updateMedia = (
        item: AuthoringMediaData,
        attributes: MediaUpdate,
    ): Promise<boolean> => {
        const updateKeys = Object.keys(attributes).map(
            (attribute) => `${item.id}:${attribute}`,
        );
        const generation = ++updateGeneration.current;

        for (const key of updateKeys) {
            dirtyUpdateKeys.current.set(key, generation);
        }

        setHasPendingChanges(dirtyUpdateKeys.current.size > 0);

        const pending = updateQueue.current.then(async (): Promise<boolean> => {
            updateRequest.transform(() => attributes);

            try {
                const response = await updateRequest.patch(
                    update.url({ post: postId, media: item.id }),
                );
                const normalized = normalizeMedia(response.media);

                setMedia((current) =>
                    current.map((candidate) =>
                        candidate.id === normalized.id ? normalized : candidate,
                    ),
                );

                if (response.body !== null) {
                    onDraftBodyRewritten?.(
                        response.lock_version,
                        response.body,
                    );
                }

                for (const key of updateKeys) {
                    if (dirtyUpdateKeys.current.get(key) === generation) {
                        dirtyUpdateKeys.current.delete(key);
                    }
                }

                setHasPendingChanges(dirtyUpdateKeys.current.size > 0);
                setError(null);

                return true;
            } catch {
                setError('The image details could not be saved.');

                return false;
            }
        });

        updateQueue.current = pending;
        pendingUpdates.current.add(pending);
        void pending.finally(() => pendingUpdates.current.delete(pending));

        return pending;
    };

    const flush = async (): Promise<boolean> => {
        let saved = true;

        while (pendingUpdates.current.size > 0) {
            const results = await Promise.all([...pendingUpdates.current]);
            saved = saved && results.every(Boolean);
        }

        return saved && dirtyUpdateKeys.current.size === 0;
    };

    const remove = async (item: AuthoringMediaData): Promise<boolean> => {
        deleteRequest.transform(() => ({ discard_story_writing: true }));

        try {
            await deleteRequest.delete(
                destroy.url({ post: postId, media: item.id }),
            );
            setMedia((current) =>
                current.filter((candidate) => candidate.id !== item.id),
            );

            for (const key of dirtyUpdateKeys.current.keys()) {
                if (key.startsWith(`${item.id}:`)) {
                    dirtyUpdateKeys.current.delete(key);
                }
            }

            setHasPendingChanges(dirtyUpdateKeys.current.size > 0);
            setError(null);

            return true;
        } catch {
            setError(
                'The image could not be removed because it is still used by the article, a saved photo passage, or a retained revision.',
            );

            return false;
        }
    };

    const useAsFeatured = async (
        item: AuthoringMediaData,
        lockVersion: number,
    ): Promise<FeaturedMediaResponse | null> => {
        featureRequest.transform(() => ({ lock_version: lockVersion }));
        setFeaturingMediaId(item.id);

        try {
            const response = await featureRequest.post(
                feature.url({ post: postId, media: item.id }),
            );
            const normalized = normalizeMedia(response.media);
            setMedia((current) =>
                current.map((candidate) =>
                    candidate.id === normalized.id ? normalized : candidate,
                ),
            );
            setError(null);

            return response;
        } catch {
            setError('The image could not be set as the featured image.');

            return null;
        } finally {
            setFeaturingMediaId(null);
        }
    };

    return {
        media,
        hydrated,
        uploading: uploadRequest.processing,
        featuringMediaId,
        hasPendingChanges,
        error,
        upload,
        updateMedia,
        useAsFeatured,
        remove,
        refresh,
        flush,
    };
}

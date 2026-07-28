import { useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useState } from 'react';
import {
    destroy,
    index,
    storeDocuments,
    storeNote,
    storeUrl,
    update,
} from '@/actions/App/Http/Admin/Controllers/PostSourceController';
import type {
    AuthoringResearchFinding,
    PostSourceData,
    PostSourceKind,
    PostSourceVisibility,
} from '@/types';

type SourceResponse = { source: ApiPostSource };
type SourcesResponse = { sources: ApiPostSource[] };

type ApiPostSource = {
    id: number;
    kind: Exclude<PostSourceKind, 'research'>;
    title: string;
    url: string | null;
    content: string | null;
    original_name: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    visibility: PostSourceVisibility;
    approved: boolean;
    created_at: string | null;
};

function normalizeSource(source: ApiPostSource): PostSourceData {
    return {
        ...source,
        status: 'ready',
        error: null,
        created_at: source.created_at ?? '',
    };
}

export function usePostSources(postId: number) {
    const [sources, setSources] = useState<PostSourceData[]>([]);
    const [hydrated, setHydrated] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const listRequest = useHttp<Record<string, never>, SourcesResponse>({});
    const noteRequest = useHttp<{ content: string }, SourceResponse>({
        content: '',
    });
    const urlRequest = useHttp<{ url: string; title?: string }, SourceResponse>(
        { url: '' },
    );
    const documentRequest = useHttp<{ documents: File[] }, SourcesResponse>({
        documents: [],
    });
    const updateRequest = useHttp<
        {
            approved?: boolean;
            visibility?: PostSourceVisibility;
            title?: string;
        },
        SourceResponse
    >({});
    const deleteRequest = useHttp<Record<string, never>, void>({});

    const refresh = async (): Promise<void> => {
        try {
            const response = await listRequest.get(index.url(postId));
            setSources(response.sources.map(normalizeSource));
            setError(null);
        } catch {
            setError('Sources could not be loaded.');
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

    const addNote = async (content: string): Promise<void> => {
        noteRequest.transform(() => ({ content }));

        try {
            const response = await noteRequest.post(storeNote.url(postId));
            setSources((current) => [
                normalizeSource(response.source),
                ...current,
            ]);
            setError(null);
        } catch {
            setError('The note could not be added.');

            throw new Error('The note could not be added.');
        }
    };

    const addUrl = async (
        url: string,
        title?: string,
    ): Promise<PostSourceData> => {
        urlRequest.transform(() => ({ url, ...(title ? { title } : {}) }));

        try {
            const response = await urlRequest.post(storeUrl.url(postId));
            const source = normalizeSource(response.source);
            setSources((current) => [source, ...current]);
            setError(null);

            return source;
        } catch {
            setError('The link could not be added.');

            throw new Error('The link could not be added.');
        }
    };

    const upload = async (documents: File[]): Promise<void> => {
        documentRequest.transform(() => ({ documents }));

        try {
            const response = await documentRequest.post(
                storeDocuments.url(postId),
            );
            setSources((current) => [
                ...response.sources.map(normalizeSource),
                ...current,
            ]);
            setError(null);
        } catch {
            setError(
                'The documents could not be uploaded. Use PDF, TXT, or Markdown files up to 10 MB each.',
            );

            throw new Error('The documents could not be uploaded.');
        }
    };

    const updateSource = async (
        source: PostSourceData,
        attributes: {
            approved?: boolean;
            visibility?: PostSourceVisibility;
            title?: string;
        },
    ): Promise<void> => {
        updateRequest.transform(() => attributes);

        try {
            const response = await updateRequest.patch(
                update.url({ post: postId, source: source.id }),
            );
            const normalized = normalizeSource(response.source);
            setSources((current) =>
                current.map((item) =>
                    item.id === normalized.id ? normalized : item,
                ),
            );
            setError(null);
        } catch {
            setError('The source could not be updated.');
        }
    };

    const remove = async (source: PostSourceData): Promise<void> => {
        try {
            await deleteRequest.delete(
                destroy.url({ post: postId, source: source.id }),
            );
            setSources((current) =>
                current.filter((item) => item.id !== source.id),
            );
            setError(null);
        } catch {
            setError('The source could not be removed.');
        }
    };

    const approveResearch = async (
        finding: AuthoringResearchFinding,
    ): Promise<void> => {
        const existingSource = sources.find(
            (source) => source.url === finding.url,
        );
        const source =
            existingSource ?? (await addUrl(finding.url, finding.title));

        await updateSource(source, {
            approved: true,
            visibility: 'public',
        });
    };

    return {
        sources,
        hydrated,
        uploading: documentRequest.processing,
        error,
        addNote,
        addUrl,
        upload,
        updateSource,
        approveResearch,
        remove,
        refresh,
    };
}

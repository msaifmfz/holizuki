import { Head, Link, router, setLayoutProps, useHttp } from '@inertiajs/react';
import {
    CalendarClock,
    Eye,
    History,
    RotateCcw,
    Save,
    Send,
    Sparkles,
    Star,
    StarOff,
} from 'lucide-react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import PostAutosaveController from '@/actions/App/Http/Admin/Controllers/PostAutosaveController';
import {
    edit,
    index,
    update,
} from '@/actions/App/Http/Admin/Controllers/PostController';
import {
    preview,
    publish,
    schedule,
    unpublish,
} from '@/actions/App/Http/Admin/Controllers/PostPublishingController';
import { index as revisionsIndex } from '@/actions/App/Http/Admin/Controllers/PostRevisionController';
import AssistantPanel from '@/components/assistant/assistant-panel';
import FieldSuggestionChip from '@/components/assistant/field-suggestion-chip';
import GenerateButton from '@/components/assistant/generate-button';
import OutlineWizard from '@/components/assistant/outline-wizard';
import OrganizationCard from '@/components/editor/organization-card';
import SeoCard from '@/components/editor/seo-card';
import { documentHasText } from '@/components/editor/types';
import type { EditorForm } from '@/components/editor/types';
import FeaturedImageUploader from '@/components/featured-image-uploader';
import InputError from '@/components/input-error';
import PostStatusBadge from '@/components/post-status-badge';
import RichTextEditor from '@/components/rich-text-editor';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAssistant } from '@/hooks/use-assistant';
import {
    editorFormsEqual,
    postConflictFromHttpResponse,
    reconcileAcceptedChange,
    reconcileAutosave,
} from '@/lib/editor-save';
import {
    errorText,
    formatDate,
    localDateTimeToUtc,
    slugify,
    toLocalDateTimeInput,
} from '@/lib/post-editor';
import {
    destroy as unfeaturePost,
    store as featurePost,
} from '@/routes/posts/feature';
import type {
    AcceptChangeResponse,
    AutosaveResponse,
    EditorOption,
    PostConflict,
    PostEditorData,
} from '@/types';

type PublishingResponse = {
    status: 'draft' | 'scheduled' | 'published';
    lock_version: number;
    scheduled_at: string | null;
    published_at: string | null;
    slug_locked_at: string | null;
    updated_at: string;
};

export default function EditPost({
    post,
    categories,
    authors,
    tagSuggestions,
}: {
    post: PostEditorData;
    categories: EditorOption[];
    authors: EditorOption[];
    tagSuggestions: string[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Posts', href: index() },
            { title: 'Edit post', href: edit(post.id) },
        ],
    });

    const [postState, setPostState] = useState(post);
    const [featuredAt, setFeaturedAt] = useState(post.featured_at);
    const [saveState, setSaveState] = useState<'saved' | 'saving' | 'error'>(
        'saved',
    );
    const [conflict, setConflict] = useState<PostConflict | null>(null);
    const [assistantOpen, setAssistantOpen] = useState(false);
    const [locateTarget, setLocateTarget] = useState<{
        markdown: string;
        token: number;
    } | null>(null);
    const [scheduleAt, setScheduleAt] = useState(
        toLocalDateTimeInput(post.scheduled_at),
    );
    const [initialEditorData] = useState<EditorForm>(() => ({
        title: post.title,
        slug: post.slug,
        slug_is_manual: post.slug_is_manual,
        excerpt: post.excerpt ?? '',
        body: post.body,
        featured_image_alt: post.featured_image_alt ?? '',
        featured_image_caption: post.featured_image_caption ?? '',
        category_id: post.category_id,
        author_id: post.author_id,
        tags: post.tags,
        seo_title: post.seo_title ?? '',
        meta_description: post.meta_description ?? '',
        canonical_url: post.canonical_url ?? '',
        og_title: post.og_title ?? '',
        og_description: post.og_description ?? '',
        noindex: post.noindex,
        lock_version: post.lock_version,
        force: false,
    }));
    const autosave = useHttp<EditorForm, AutosaveResponse>(initialEditorData);
    const latestAutosaveDataRef = useRef(initialEditorData);
    const savedBaselineRef = useRef(initialEditorData);
    const savePromiseRef = useRef<Promise<AutosaveResponse | null> | null>(
        null,
    );

    useEffect(() => {
        latestAutosaveDataRef.current = autosave.data;
    }, [autosave.data]);
    const publishing = useHttp<
        { lock_version: number; scheduled_at: string },
        PublishingResponse
    >({
        lock_version: post.lock_version,
        scheduled_at: '',
    });
    const featuring = useHttp<Record<string, never>, void>({});

    const applySaveResponse = (
        response: AutosaveResponse,
        submittedData: EditorForm,
    ) => {
        const reconciled = reconcileAutosave(
            latestAutosaveDataRef.current,
            submittedData,
            response,
        );
        latestAutosaveDataRef.current = reconciled.data;
        savedBaselineRef.current = reconciled.defaults;
        autosave.setDefaults(reconciled.defaults);
        autosave.setData(reconciled.data);
        setPostState((current) => ({
            ...current,
            slug: response.slug,
            lock_version: response.lock_version,
            updated_at: response.updated_at,
            last_editor: response.last_editor,
        }));
        setSaveState(reconciled.hasPendingChanges ? 'saving' : 'saved');
        setConflict(null);
    };

    const save = (
        manual = false,
        force = false,
    ): Promise<AutosaveResponse | null> => {
        const activeSave = savePromiseRef.current;

        if (activeSave !== null) {
            return manual
                ? activeSave.then((response) =>
                      response === null ? null : save(true, force),
                  )
                : activeSave;
        }

        const submittedData: EditorForm = {
            ...latestAutosaveDataRef.current,
            force,
        };
        const request = (async (): Promise<AutosaveResponse | null> => {
            setSaveState('saving');
            autosave.transform(() => submittedData);
            let successfulResponse: AutosaveResponse | null = null;

            try {
                await autosave.patch(
                    manual
                        ? update.url(post.id)
                        : PostAutosaveController.url(post.id),
                    {
                        onSuccess: (response) => {
                            successfulResponse = response;
                            applySaveResponse(response, submittedData);
                        },
                        onHttpException: (httpResponse) => {
                            if (httpResponse.status === 409) {
                                setConflict(
                                    postConflictFromHttpResponse(
                                        httpResponse.data,
                                        submittedData.lock_version,
                                    ),
                                );
                            }
                        },
                    },
                );

                if (successfulResponse === null) {
                    setSaveState('error');
                }

                return successfulResponse;
            } catch {
                setSaveState('error');

                return null;
            }
        })();
        savePromiseRef.current = request;
        void request.finally(() => {
            if (savePromiseRef.current === request) {
                savePromiseRef.current = null;
            }
        });

        return request;
    };

    const applyAcceptedChange = (saved: AcceptChangeResponse['post']) => {
        const reconciled = reconcileAcceptedChange(
            latestAutosaveDataRef.current,
            savedBaselineRef.current,
            saved,
        );
        latestAutosaveDataRef.current = reconciled.data;
        savedBaselineRef.current = reconciled.defaults;
        autosave.setDefaults(reconciled.defaults);
        autosave.setData(reconciled.data);
        setSaveState(reconciled.hasPendingChanges ? 'saving' : 'saved');
        setPostState((current) => ({
            ...current,
            slug: saved.slug,
            lock_version: saved.lock_version,
            updated_at: saved.updated_at,
            last_editor: saved.last_editor,
        }));
    };

    const ensureLatestSaved = async (): Promise<boolean> => {
        if (conflict) {
            return false;
        }

        for (let attempt = 0; attempt < 3; attempt += 1) {
            if (
                editorFormsEqual(
                    latestAutosaveDataRef.current,
                    savedBaselineRef.current,
                )
            ) {
                return true;
            }

            if ((await save()) === null) {
                return false;
            }
        }

        return editorFormsEqual(
            latestAutosaveDataRef.current,
            savedBaselineRef.current,
        );
    };

    const assistant = useAssistant({
        postId: post.id,
        ensureSaved: ensureLatestSaved,
        onAccepted: applyAcceptedChange,
    });

    const autosaveAfterDelay = useEffectEvent(() => void save());

    useEffect(() => {
        if (
            !autosave.isDirty ||
            autosave.processing ||
            savePromiseRef.current !== null ||
            assistant.deciding ||
            conflict
        ) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setSaveState('saving');
            autosaveAfterDelay();
        }, 2000);

        return () => window.clearTimeout(timeout);
    }, [
        assistant.deciding,
        autosave.data,
        autosave.isDirty,
        autosave.processing,
        conflict,
    ]);

    const changeTitle = (title: string) => {
        autosave.setData((data) => ({
            ...data,
            title,
            slug:
                !data.slug_is_manual && !postState.slug_locked_at
                    ? slugify(title)
                    : data.slug,
        }));
    };

    const applyServerEditorUpdate = (updates: Partial<EditorForm>): void => {
        latestAutosaveDataRef.current = {
            ...latestAutosaveDataRef.current,
            ...updates,
        };
        savedBaselineRef.current = {
            ...savedBaselineRef.current,
            ...updates,
        };
        autosave.setData((data) => ({ ...data, ...updates }));
        autosave.setDefaults(updates);
    };

    const applyPublishingResponse = (response: PublishingResponse) => {
        // Update only lock_version against the live form state so any edits made
        // while the request was in flight are preserved rather than clobbered.
        applyServerEditorUpdate({
            lock_version: response.lock_version,
        });
        publishing.setDefaults({
            lock_version: response.lock_version,
            scheduled_at: '',
        });
        setPostState((current) => ({ ...current, ...response }));

        if (response.status !== 'published') {
            setFeaturedAt(null);
        }
    };

    const publishNow = async () => {
        if (!(await ensureLatestSaved())) {
            return;
        }

        const lockVersion = latestAutosaveDataRef.current.lock_version;
        publishing.transform(() => ({
            lock_version: lockVersion,
            scheduled_at: '',
        }));

        try {
            applyPublishingResponse(
                await publishing.post(publish.url(post.id)),
            );
        } catch {
            return;
        }
    };

    const schedulePost = async () => {
        if (!scheduleAt) {
            return;
        }

        if (!(await ensureLatestSaved())) {
            return;
        }

        const lockVersion = latestAutosaveDataRef.current.lock_version;
        publishing.transform(() => ({
            lock_version: lockVersion,
            scheduled_at: localDateTimeToUtc(scheduleAt),
        }));

        try {
            applyPublishingResponse(
                await publishing.post(schedule.url(post.id)),
            );
        } catch {
            return;
        }
    };

    const unpublishPost = async () => {
        publishing.transform(() => ({
            lock_version: autosave.data.lock_version,
            scheduled_at: '',
        }));

        try {
            applyPublishingResponse(
                await publishing.post(unpublish.url(post.id)),
            );
        } catch {
            return;
        }
    };

    const toggleFeatured = async () => {
        try {
            if (featuredAt) {
                await featuring.delete(unfeaturePost.url(post.id));
                setFeaturedAt(null);

                return;
            }

            await featuring.post(featurePost.url(post.id));
            setFeaturedAt(new Date().toISOString());
        } catch {
            return;
        }
    };

    const openPreview = async () => {
        const previewWindow = window.open('about:blank', '_blank');
        const saved = await ensureLatestSaved();

        if (!saved) {
            previewWindow?.close();

            return;
        }

        if (previewWindow) {
            previewWindow.location.href = preview.url(post.id);
        }
    };

    const publishingErrors = Object.values(publishing.errors)
        .flat()
        .filter((error): error is string => typeof error === 'string');

    return (
        <>
            <Head title={`Edit ${postState.title}`} />
            <div className="mx-auto grid w-full max-w-7xl gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_21rem] lg:p-6">
                <main className="grid min-w-0 gap-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    Edit post
                                </h1>
                                <PostStatusBadge status={postState.status} />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {saveState === 'saving'
                                    ? 'Saving…'
                                    : saveState === 'error'
                                      ? 'Save failed'
                                      : `Saved ${formatDate(postState.updated_at)}`}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant={assistantOpen ? 'default' : 'outline'}
                                onClick={() =>
                                    setAssistantOpen((open) => !open)
                                }
                            >
                                <Sparkles />
                                Co-writer
                                {assistant.pendingCount > 0 && (
                                    <span className="ml-1 rounded-full bg-primary-foreground/20 px-1.5 text-xs">
                                        {assistant.pendingCount}
                                    </span>
                                )}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={openPreview}
                            >
                                <Eye />
                                Preview
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={autosave.processing}
                                onClick={() => void save(true)}
                            >
                                <Save />
                                Save revision
                            </Button>
                        </div>
                    </div>

                    {conflict && (
                        <Alert variant="destructive">
                            <AlertTitle>This post changed elsewhere</AlertTitle>
                            <AlertDescription className="grid gap-3">
                                <p>
                                    {conflict.last_editor
                                        ? `${conflict.last_editor} saved a newer version.`
                                        : 'A newer version was saved.'}{' '}
                                    Reload it, or explicitly overwrite it with
                                    your draft.
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.reload()}
                                    >
                                        Reload latest
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => void save(false, true)}
                                    >
                                        Keep my draft
                                    </Button>
                                </div>
                            </AlertDescription>
                        </Alert>
                    )}

                    <Card>
                        <CardContent className="grid gap-5 pt-6">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={autosave.data.title}
                                    maxLength={255}
                                    onChange={(event) =>
                                        changeTitle(event.target.value)
                                    }
                                    placeholder="Post title"
                                />
                                <InputError
                                    message={errorText(autosave.errors.title)}
                                />
                                {assistant.suggestions.title && (
                                    <FieldSuggestionChip
                                        change={assistant.suggestions.title}
                                        busy={assistant.isDeciding(
                                            assistant.suggestions.title.id,
                                        )}
                                        error={assistant.decisionError(
                                            assistant.suggestions.title.id,
                                        )}
                                        onAccept={assistant.accept}
                                        onReject={assistant.reject}
                                    />
                                )}
                            </div>
                            <div className="grid gap-2">
                                <div className="flex items-center justify-between gap-2">
                                    <Label htmlFor="slug">Slug</Label>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            autosave.setData((data) => ({
                                                ...data,
                                                slug: slugify(data.title),
                                                slug_is_manual: false,
                                            }))
                                        }
                                    >
                                        <RotateCcw />
                                        Regenerate
                                    </Button>
                                </div>
                                <Input
                                    id="slug"
                                    value={autosave.data.slug}
                                    maxLength={255}
                                    onChange={(event) =>
                                        autosave.setData((data) => ({
                                            ...data,
                                            slug: slugify(event.target.value),
                                            slug_is_manual: true,
                                        }))
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    {postState.slug_locked_at
                                        ? 'This post has been published — changing the slug creates a permanent redirect from the old URL.'
                                        : 'Automatic changes stop after first publication; manual edits remain available.'}
                                </p>
                                <InputError
                                    message={errorText(autosave.errors.slug)}
                                />
                            </div>
                            <div className="grid gap-2">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="excerpt">Excerpt</Label>
                                    <GenerateButton
                                        busy={assistant.generating}
                                        disabled={!assistant.canGenerate}
                                        onClick={() =>
                                            void assistant.generateMetadata([
                                                'excerpt',
                                            ])
                                        }
                                    />
                                </div>
                                <textarea
                                    id="excerpt"
                                    value={autosave.data.excerpt}
                                    maxLength={500}
                                    rows={4}
                                    onChange={(event) =>
                                        autosave.setData(
                                            'excerpt',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                    placeholder="A concise summary shown with the post"
                                />
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <InputError
                                        message={errorText(
                                            autosave.errors.excerpt,
                                        )}
                                    />
                                    <span>
                                        {autosave.data.excerpt.length}/500
                                    </span>
                                </div>
                                {assistant.suggestions.excerpt && (
                                    <FieldSuggestionChip
                                        change={assistant.suggestions.excerpt}
                                        busy={assistant.isDeciding(
                                            assistant.suggestions.excerpt.id,
                                        )}
                                        error={assistant.decisionError(
                                            assistant.suggestions.excerpt.id,
                                        )}
                                        onAccept={assistant.accept}
                                        onReject={assistant.reject}
                                    />
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {!documentHasText(autosave.data.body) && (
                        <OutlineWizard
                            busy={assistant.generating}
                            disabled={!assistant.canGenerate}
                            onStart={async (topic) => {
                                setAssistantOpen(true);

                                return assistant.startOutline(topic);
                            }}
                        />
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Body</CardTitle>
                            <CardDescription>
                                Use headings, lists, links, quotes, and code to
                                structure the article.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <RichTextEditor
                                value={autosave.data.body}
                                postId={post.id}
                                locateTarget={locateTarget}
                                aiBusy={
                                    assistant.generating ||
                                    !assistant.canGenerate
                                }
                                onAiTransform={(
                                    selection,
                                    preset,
                                    instruction,
                                ) => {
                                    setAssistantOpen(true);
                                    void assistant.transform(
                                        selection,
                                        preset,
                                        instruction,
                                    );
                                }}
                                onChange={(body) =>
                                    autosave.setData('body', body)
                                }
                            />
                            <InputError
                                className="mt-2"
                                message={errorText(autosave.errors.body)}
                            />
                        </CardContent>
                    </Card>
                </main>

                <aside className="grid content-start gap-6">
                    <OrganizationCard
                        autosave={autosave}
                        assistant={assistant}
                        categories={categories}
                        authors={authors}
                        tagSuggestions={tagSuggestions}
                    />

                    <SeoCard
                        autosave={autosave}
                        assistant={assistant}
                        postId={post.id}
                        ogImageUrl={postState.og_image_url}
                        onOgImageChange={(response) => {
                            applyServerEditorUpdate({
                                lock_version: response.lock_version,
                            });
                            setPostState((current) => ({
                                ...current,
                                og_image_url: response.og_image_url,
                                lock_version: response.lock_version,
                                updated_at: response.updated_at,
                            }));
                        }}
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>Publishing</CardTitle>
                            <CardDescription>
                                All fields and a featured image are required.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {publishingErrors.length > 0 && (
                                <Alert variant="destructive">
                                    <AlertTitle>Cannot publish yet</AlertTitle>
                                    <AlertDescription>
                                        <ul className="list-disc pl-4">
                                            {publishingErrors.map((error) => (
                                                <li key={error}>{error}</li>
                                            ))}
                                        </ul>
                                    </AlertDescription>
                                </Alert>
                            )}
                            {postState.status === 'published' ? (
                                <>
                                    <Button
                                        type="button"
                                        variant={
                                            featuredAt ? 'secondary' : 'outline'
                                        }
                                        disabled={featuring.processing}
                                        onClick={toggleFeatured}
                                    >
                                        {featuredAt ? <StarOff /> : <Star />}
                                        {featuredAt
                                            ? 'Remove from featured'
                                            : 'Feature on homepage'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={publishing.processing}
                                        onClick={unpublishPost}
                                    >
                                        Unpublish
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    type="button"
                                    disabled={publishing.processing}
                                    onClick={publishNow}
                                >
                                    <Send />
                                    Publish now
                                </Button>
                            )}
                            {postState.status === 'scheduled' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={publishing.processing}
                                    onClick={unpublishPost}
                                >
                                    Cancel schedule
                                </Button>
                            )}
                            {postState.status !== 'published' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="schedule-at">
                                        Schedule in{' '}
                                        {
                                            Intl.DateTimeFormat().resolvedOptions()
                                                .timeZone
                                        }
                                    </Label>
                                    <Input
                                        id="schedule-at"
                                        type="datetime-local"
                                        min={toLocalDateTimeInput(
                                            new Date().toISOString(),
                                        )}
                                        value={scheduleAt}
                                        onChange={(event) =>
                                            setScheduleAt(event.target.value)
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={
                                            !scheduleAt || publishing.processing
                                        }
                                        onClick={schedulePost}
                                    >
                                        <CalendarClock />
                                        Schedule
                                    </Button>
                                </div>
                            )}
                            {postState.published_at && (
                                <p className="text-xs text-muted-foreground">
                                    Published{' '}
                                    {formatDate(postState.published_at)}
                                </p>
                            )}
                            {postState.scheduled_at && (
                                <p className="text-xs text-muted-foreground">
                                    Scheduled for{' '}
                                    {formatDate(postState.scheduled_at)}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="grid gap-3 pt-6">
                            <div className="flex justify-end">
                                <GenerateButton
                                    label="Review images"
                                    busy={assistant.generating}
                                    disabled={!assistant.canGenerate}
                                    onClick={() => {
                                        setAssistantOpen(true);
                                        void assistant.reviewImages();
                                    }}
                                />
                            </div>
                            <FeaturedImageUploader
                                key={postState.featured_image_url ?? 'no-image'}
                                postId={post.id}
                                lockVersion={autosave.data.lock_version}
                                imageUrl={postState.featured_image_url}
                                altText={autosave.data.featured_image_alt}
                                caption={autosave.data.featured_image_caption}
                                canRemove={postState.status === 'draft'}
                                onAltTextChange={(value) =>
                                    autosave.setData(
                                        'featured_image_alt',
                                        value,
                                    )
                                }
                                onCaptionChange={(value) =>
                                    autosave.setData(
                                        'featured_image_caption',
                                        value,
                                    )
                                }
                                onChange={(response) => {
                                    const alternativeText =
                                        response.featured_image_alt ?? '';
                                    const editorUpdates: Partial<EditorForm> = {
                                        lock_version: response.lock_version,
                                        featured_image_alt: alternativeText,
                                    };

                                    if (response.featured_image_url === null) {
                                        editorUpdates.featured_image_caption =
                                            '';
                                    }

                                    applyServerEditorUpdate(editorUpdates);
                                    setPostState((current) => ({
                                        ...current,
                                        featured_image_url:
                                            response.featured_image_url,
                                        featured_image_alt:
                                            response.featured_image_alt,
                                        featured_image_caption:
                                            response.featured_image_url === null
                                                ? null
                                                : current.featured_image_caption,
                                        lock_version: response.lock_version,
                                        updated_at: response.updated_at,
                                    }));
                                }}
                            />
                            {assistant.suggestions.featured_image_alt && (
                                <div className="grid gap-1">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Suggested image alt text
                                    </p>
                                    <FieldSuggestionChip
                                        change={
                                            assistant.suggestions
                                                .featured_image_alt
                                        }
                                        busy={assistant.isDeciding(
                                            assistant.suggestions
                                                .featured_image_alt.id,
                                        )}
                                        error={assistant.decisionError(
                                            assistant.suggestions
                                                .featured_image_alt.id,
                                        )}
                                        onAccept={assistant.accept}
                                        onReject={assistant.reject}
                                    />
                                </div>
                            )}
                            {assistant.suggestions.featured_image_caption && (
                                <div className="grid gap-1">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Suggested image caption
                                    </p>
                                    <FieldSuggestionChip
                                        change={
                                            assistant.suggestions
                                                .featured_image_caption
                                        }
                                        busy={assistant.isDeciding(
                                            assistant.suggestions
                                                .featured_image_caption.id,
                                        )}
                                        error={assistant.decisionError(
                                            assistant.suggestions
                                                .featured_image_caption.id,
                                        )}
                                        onAccept={assistant.accept}
                                        onReject={assistant.reject}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Button asChild variant="outline">
                        <Link href={revisionsIndex(post.id)}>
                            <History />
                            Revision history
                        </Link>
                    </Button>
                </aside>
            </div>

            <AssistantPanel
                open={assistantOpen}
                onClose={() => setAssistantOpen(false)}
                thread={assistant.thread}
                bodyChanges={assistant.bodyChanges}
                pendingCount={assistant.pendingCount}
                streaming={assistant.generating}
                narration={assistant.narration}
                hydrated={assistant.hydrated}
                status={assistant.status}
                retryAt={assistant.retryAt}
                stateError={assistant.stateError}
                canGenerate={assistant.canGenerate}
                cancelling={assistant.cancelling}
                outlineReady={assistant.outlineReady}
                onDraftOutline={assistant.draftOutline}
                onSend={assistant.sendChat}
                onCancel={() => void assistant.cancel()}
                onAccept={(change) => void assistant.accept(change)}
                onReject={(change) => void assistant.reject(change)}
                onRetry={() => void assistant.refresh()}
                isDeciding={assistant.isDeciding}
                decisionError={assistant.decisionError}
                onLocate={(change) => {
                    const payload = change.payload as {
                        old_blocks?: string;
                        anchor_before?: string | null;
                        anchor_after?: string | null;
                    };
                    const markdown =
                        payload.old_blocks && payload.old_blocks !== ''
                            ? payload.old_blocks
                            : (payload.anchor_before ??
                              payload.anchor_after ??
                              '');

                    if (markdown !== '') {
                        setLocateTarget({ markdown, token: Date.now() });
                    }
                }}
            />
        </>
    );
}

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
import { useEffect, useEffectEvent, useState } from 'react';
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
    AssistantChangeData,
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
    const autosave = useHttp<EditorForm, AutosaveResponse>({
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
    });
    const publishing = useHttp<
        { lock_version: number; scheduled_at: string },
        PublishingResponse
    >({
        lock_version: post.lock_version,
        scheduled_at: '',
    });
    const featuring = useHttp<Record<string, never>, void>({});

    const applySaveResponse = (response: AutosaveResponse) => {
        const nextData = {
            ...autosave.data,
            slug: response.slug,
            lock_version: response.lock_version,
            force: false,
        };
        autosave.setData(nextData);
        autosave.setDefaults(nextData);
        setPostState((current) => ({
            ...current,
            slug: response.slug,
            lock_version: response.lock_version,
            updated_at: response.updated_at,
            last_editor: response.last_editor,
        }));
        setSaveState('saved');
        setConflict(null);
    };

    const save = async (
        manual = false,
        force = false,
    ): Promise<AutosaveResponse | null> => {
        setSaveState('saving');
        autosave.transform((data) => ({ ...data, force }));

        try {
            const response = await autosave.patch(
                manual
                    ? update.url(post.id)
                    : PostAutosaveController.url(post.id),
                {
                    onHttpException: (httpResponse) => {
                        if (httpResponse.status === 409) {
                            const payload = httpResponse.data as unknown as {
                                conflict: PostConflict;
                            };
                            setConflict(payload.conflict);
                        }
                    },
                },
            );
            applySaveResponse(response);

            return response;
        } catch {
            setSaveState('error');

            return null;
        }
    };

    const applyAcceptedChange = (
        change: AssistantChangeData,
        saved: AcceptChangeResponse['post'],
    ) => {
        const updates: Partial<EditorForm> = {
            slug: saved.slug,
            lock_version: saved.lock_version,
        };

        switch (change.type) {
            case 'title':
                updates.title = saved.title ?? '';
                break;
            case 'excerpt':
                updates.excerpt = saved.excerpt ?? '';
                break;
            case 'seo_title':
                updates.seo_title = saved.seo_title ?? '';
                break;
            case 'meta_description':
                updates.meta_description = saved.meta_description ?? '';
                break;
            case 'og_title':
                updates.og_title = saved.og_title ?? '';
                break;
            case 'og_description':
                updates.og_description = saved.og_description ?? '';
                break;
            case 'featured_image_alt':
                updates.featured_image_alt = saved.featured_image_alt ?? '';
                break;
            case 'featured_image_caption':
                updates.featured_image_caption =
                    saved.featured_image_caption ?? '';
                break;
            case 'tags':
                updates.tags = saved.tags;
                break;
            case 'body':
                updates.body = saved.body;
                break;
        }

        autosave.setData((data) => ({ ...data, ...updates }));
        autosave.setDefaults(updates);
        setPostState((current) => ({
            ...current,
            slug: saved.slug,
            lock_version: saved.lock_version,
            updated_at: saved.updated_at,
            last_editor: saved.last_editor,
        }));
    };

    const assistant = useAssistant({
        postId: post.id,
        ensureSaved: async () => {
            if (conflict) {
                return false;
            }

            return autosave.isDirty ? (await save()) !== null : true;
        },
        onAccepted: applyAcceptedChange,
    });

    const autosaveAfterDelay = useEffectEvent(() => void save());

    useEffect(() => {
        if (!autosave.isDirty || autosave.processing || conflict) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setSaveState('saving');
            autosaveAfterDelay();
        }, 2000);

        return () => window.clearTimeout(timeout);
    }, [autosave.data, autosave.isDirty, autosave.processing, conflict]);

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

    const applyPublishingResponse = (response: PublishingResponse) => {
        // Update only lock_version against the live form state so any edits made
        // while the request was in flight are preserved rather than clobbered.
        autosave.setData('lock_version', response.lock_version);
        autosave.setDefaults('lock_version', response.lock_version);
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
        const saved = autosave.isDirty ? await save() : null;

        if (autosave.isDirty && !saved) {
            return;
        }

        const lockVersion = saved?.lock_version ?? autosave.data.lock_version;
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

        const saved = autosave.isDirty ? await save() : null;

        if (autosave.isDirty && !saved) {
            return;
        }

        const lockVersion = saved?.lock_version ?? autosave.data.lock_version;
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
        const saved = autosave.isDirty ? await save() : true;

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
                                {assistant.bodyChanges.length > 0 && (
                                    <span className="ml-1 rounded-full bg-primary-foreground/20 px-1.5 text-xs">
                                        {assistant.bodyChanges.length}
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
                                        busy={assistant.deciding}
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
                            onStart={(topic) => {
                                setAssistantOpen(true);
                                void assistant.startOutline(topic);
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
                                aiBusy={assistant.generating}
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
                            autosave.setData(
                                'lock_version',
                                response.lock_version,
                            );
                            autosave.setDefaults(
                                'lock_version',
                                response.lock_version,
                            );
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
                                    autosave.setData(
                                        'lock_version',
                                        response.lock_version,
                                    );
                                    autosave.setDefaults(
                                        'lock_version',
                                        response.lock_version,
                                    );
                                    autosave.setData(
                                        'featured_image_alt',
                                        alternativeText,
                                    );
                                    autosave.setDefaults(
                                        'featured_image_alt',
                                        alternativeText,
                                    );

                                    if (response.featured_image_url === null) {
                                        autosave.setData(
                                            'featured_image_caption',
                                            '',
                                        );
                                        autosave.setDefaults(
                                            'featured_image_caption',
                                            '',
                                        );
                                    }

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
                streaming={assistant.generating}
                narration={assistant.narration}
                deciding={assistant.deciding}
                outlineReady={assistant.outlineReady}
                onDraftOutline={() => void assistant.draftOutline()}
                onSend={(message) => void assistant.sendChat(message)}
                onCancel={assistant.cancel}
                onAccept={(change) => void assistant.accept(change)}
                onReject={(change) => void assistant.reject(change)}
                onLocate={(change) => {
                    const payload = change.payload as {
                        old_blocks?: string;
                        anchor_before?: string | null;
                    };
                    const markdown =
                        payload.old_blocks && payload.old_blocks !== ''
                            ? payload.old_blocks
                            : (payload.anchor_before ?? '');

                    if (markdown !== '') {
                        setLocateTarget({ markdown, token: Date.now() });
                    }
                }}
            />
        </>
    );
}

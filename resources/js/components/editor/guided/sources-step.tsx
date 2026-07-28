import {
    FileText,
    Globe2,
    Link2,
    LoaderCircle,
    NotebookPen,
    Plus,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AuthoringResearchFinding, PostSourceData } from '@/types';

const acceptedSourceTypes =
    '.pdf,.txt,.md,application/pdf,text/plain,text/markdown';

function formatFileSize(bytes: number | null): string | null {
    if (bytes === null) {
        return null;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function SourceIcon({ kind }: Pick<PostSourceData, 'kind'>) {
    if (kind === 'url' || kind === 'research') {
        return <Globe2 className="size-4" aria-hidden />;
    }

    if (kind === 'note') {
        return <NotebookPen className="size-4" aria-hidden />;
    }

    return <FileText className="size-4" aria-hidden />;
}

export default function SourcesStep({
    sources,
    researchFindings,
    uploading,
    researching,
    assistantAvailable,
    webResearchEnabled,
    error,
    onAddNote,
    onAddUrl,
    onUpload,
    onApprovalChange,
    onVisibilityChange,
    onRemove,
    onWebResearchChange,
    onResearch,
    onApproveResearch,
}: {
    sources: PostSourceData[];
    researchFindings: AuthoringResearchFinding[];
    uploading: boolean;
    researching: boolean;
    assistantAvailable: boolean;
    webResearchEnabled: boolean;
    error: string | null;
    onAddNote: (note: string) => Promise<void> | void;
    onAddUrl: (url: string) => Promise<void> | void;
    onUpload: (files: File[]) => Promise<void> | void;
    onApprovalChange: (
        source: PostSourceData,
        approved: boolean,
    ) => Promise<void> | void;
    onVisibilityChange: (
        source: PostSourceData,
        visibility: PostSourceData['visibility'],
    ) => Promise<void> | void;
    onRemove: (source: PostSourceData) => Promise<void> | void;
    onWebResearchChange: (enabled: boolean) => void;
    onResearch: () => Promise<void> | void;
    onApproveResearch: (
        finding: AuthoringResearchFinding,
    ) => Promise<void> | void;
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [note, setNote] = useState('');
    const [url, setUrl] = useState('');
    const [addingNote, setAddingNote] = useState(false);
    const [addingUrl, setAddingUrl] = useState(false);

    const addNote = async (): Promise<void> => {
        const trimmed = note.trim();

        if (trimmed === '') {
            return;
        }

        setAddingNote(true);

        try {
            await onAddNote(trimmed);
            setNote('');
        } finally {
            setAddingNote(false);
        }
    };

    const addUrl = async (): Promise<void> => {
        const trimmed = url.trim();

        if (trimmed === '') {
            return;
        }

        setAddingUrl(true);

        try {
            await onAddUrl(trimmed);
            setUrl('');
        } finally {
            setAddingUrl(false);
        }
    };

    return (
        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem]">
            <div className="grid content-start gap-5">
                <Card>
                    <CardHeader>
                        <CardTitle>Add what you already know</CardTitle>
                        <CardDescription>
                            Notes, links, and documents help the assistant stay
                            accurate. This step is optional.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="authoring-source-note">
                                Paste a note
                            </Label>
                            <Textarea
                                id="authoring-source-note"
                                rows={4}
                                maxLength={20_000}
                                value={note}
                                placeholder="Facts, quotes, interview notes, or context…"
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                            />
                            <Button
                                type="button"
                                variant="outline"
                                className="justify-self-start"
                                disabled={note.trim() === '' || addingNote}
                                onClick={() => void addNote()}
                            >
                                {addingNote ? (
                                    <LoaderCircle className="animate-spin" />
                                ) : (
                                    <Plus />
                                )}
                                Add note
                            </Button>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="authoring-source-url">
                                Add a webpage
                            </Label>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    id="authoring-source-url"
                                    type="url"
                                    inputMode="url"
                                    maxLength={2_048}
                                    value={url}
                                    placeholder="https://example.com/article"
                                    onChange={(event) =>
                                        setUrl(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            void addUrl();
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={url.trim() === '' || addingUrl}
                                    onClick={() => void addUrl()}
                                >
                                    {addingUrl ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : (
                                        <Link2 />
                                    )}
                                    Add link
                                </Button>
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label>Upload documents</Label>
                            <button
                                type="button"
                                className="flex min-h-32 flex-col items-center justify-center gap-2 rounded-lg border border-dashed px-4 text-center text-sm text-muted-foreground transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                disabled={uploading}
                                onClick={() => fileInputRef.current?.click()}
                            >
                                {uploading ? (
                                    <LoaderCircle className="size-7 animate-spin" />
                                ) : (
                                    <Upload className="size-7" />
                                )}
                                <span className="font-medium text-foreground">
                                    {uploading
                                        ? 'Uploading documents…'
                                        : 'Choose PDF, TXT, or Markdown files'}
                                </span>
                                <span>
                                    You can select more than one. New files stay
                                    private.
                                </span>
                            </button>
                            <input
                                ref={fileInputRef}
                                type="file"
                                multiple
                                accept={acceptedSourceTypes}
                                aria-label="Source documents"
                                className="sr-only"
                                onChange={(event) => {
                                    const files = Array.from(
                                        event.target.files ?? [],
                                    );

                                    if (files.length > 0) {
                                        void onUpload(files);
                                    }

                                    event.target.value = '';
                                }}
                            />
                        </div>
                    </CardContent>
                </Card>

                <section aria-labelledby="authoring-sources-heading">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h3
                                id="authoring-sources-heading"
                                className="font-semibold"
                            >
                                Your sources
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Approve a source before the assistant uses it.
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {sources.length}{' '}
                            {sources.length === 1 ? 'source' : 'sources'}
                        </Badge>
                    </div>

                    {sources.length === 0 ? (
                        <div className="rounded-lg border bg-muted/30 p-6 text-center text-sm text-muted-foreground">
                            Nothing added yet. You can continue without sources.
                        </div>
                    ) : (
                        <div className="grid gap-3">
                            {sources.map((source) => {
                                const size = formatFileSize(source.size_bytes);

                                return (
                                    <Card key={source.id}>
                                        <CardContent className="grid gap-3 pt-5">
                                            <div className="flex items-start gap-3">
                                                <div className="rounded-md bg-muted p-2">
                                                    <SourceIcon
                                                        kind={source.kind}
                                                    />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="text-sm font-medium break-words">
                                                            {source.title}
                                                        </p>
                                                        <Badge
                                                            variant={
                                                                source.status ===
                                                                'failed'
                                                                    ? 'destructive'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {source.status}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-1 text-xs break-all text-muted-foreground">
                                                        {source.url ??
                                                            source.original_name ??
                                                            'Author note'}
                                                        {size
                                                            ? ` · ${size}`
                                                            : ''}
                                                    </p>
                                                    {source.content && (
                                                        <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">
                                                            {source.content}
                                                        </p>
                                                    )}
                                                    {source.error && (
                                                        <p className="mt-2 text-sm text-destructive">
                                                            {source.error}
                                                        </p>
                                                    )}
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={`Remove ${source.title}`}
                                                    onClick={() =>
                                                        void onRemove(source)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                            <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                                                <Label
                                                    htmlFor={`source-approved-${source.id}`}
                                                    className="flex cursor-pointer items-center gap-2"
                                                >
                                                    <Checkbox
                                                        id={`source-approved-${source.id}`}
                                                        checked={
                                                            source.approved
                                                        }
                                                        disabled={
                                                            source.status !==
                                                            'ready'
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            void onApprovalChange(
                                                                source,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    Let AI use this source
                                                </Label>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        void onVisibilityChange(
                                                            source,
                                                            source.visibility ===
                                                                'private'
                                                                ? 'public'
                                                                : 'private',
                                                        )
                                                    }
                                                >
                                                    {source.visibility ===
                                                    'private'
                                                        ? 'Hidden from readers'
                                                        : 'Shown to readers'}
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>

            <Card className="h-fit">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Search className="size-4" />
                        Research on the web
                    </CardTitle>
                    <CardDescription>
                        Optional. The assistant will propose findings for you to
                        approve before using them.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <Label
                        htmlFor="authoring-web-research"
                        className="flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                    >
                        <Checkbox
                            id="authoring-web-research"
                            checked={webResearchEnabled}
                            onCheckedChange={(checked) =>
                                onWebResearchChange(checked === true)
                            }
                        />
                        <span className="grid gap-1">
                            <span>Allow web research for this post</span>
                            <span className="text-xs font-normal text-muted-foreground">
                                Off by default. Existing links can still be
                                added above.
                            </span>
                        </span>
                    </Label>
                    <Button
                        type="button"
                        disabled={
                            !webResearchEnabled ||
                            !assistantAvailable ||
                            researching
                        }
                        onClick={() => void onResearch()}
                    >
                        {researching ? (
                            <LoaderCircle className="animate-spin" />
                        ) : (
                            <Search />
                        )}
                        {researching ? 'Researching…' : 'Find useful sources'}
                    </Button>
                    {researchFindings.length > 0 && (
                        <div className="grid gap-3 border-t pt-4">
                            <p className="text-sm font-medium">
                                Findings to review
                            </p>
                            {researchFindings.map((finding) => {
                                const added = sources.some(
                                    (source) =>
                                        source.url === finding.url &&
                                        source.approved,
                                );

                                return (
                                    <div
                                        key={finding.id}
                                        className="grid gap-2 rounded-lg border p-3"
                                    >
                                        <div>
                                            <a
                                                href={finding.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-sm font-medium underline-offset-4 hover:underline"
                                            >
                                                {finding.title}
                                            </a>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {finding.publisher}
                                                {finding.published_at
                                                    ? ` · ${finding.published_at}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <p className="line-clamp-4 text-sm text-muted-foreground">
                                            {finding.summary}
                                        </p>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={
                                                added ? 'secondary' : 'outline'
                                            }
                                            disabled={added}
                                            onClick={() =>
                                                void onApproveResearch(finding)
                                            }
                                        >
                                            {added
                                                ? 'Added to sources'
                                                : 'Approve this source'}
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    {!assistantAvailable && (
                        <p className="text-sm text-muted-foreground">
                            Web research is unavailable, but you can keep adding
                            your own sources.
                        </p>
                    )}
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

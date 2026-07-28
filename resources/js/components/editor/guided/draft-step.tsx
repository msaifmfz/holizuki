import { FileCheck2, LoaderCircle, Sparkles } from 'lucide-react';
import GenerateButton from '@/components/assistant/generate-button';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Badge } from '@/components/ui/badge';
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
import type { TransformPreset } from '@/lib/assistant/transforms';
import type { AuthoringDraftVariant, RichTextDocument } from '@/types';

export default function DraftStep({
    postId,
    title,
    excerpt,
    body,
    variants,
    generating,
    assistantAvailable,
    generationError,
    errors,
    locateTarget,
    onTitleChange,
    onExcerptChange,
    onBodyChange,
    onGenerateExcerpt,
    onAiTransform,
    onUseVariant,
    onGenerateAgain,
}: {
    postId: number;
    title: string;
    excerpt: string;
    body: RichTextDocument | null;
    variants: AuthoringDraftVariant[];
    generating: boolean;
    assistantAvailable: boolean;
    generationError: string | null;
    errors: {
        title?: string;
        excerpt?: string;
        body?: string;
    };
    locateTarget: { markdown: string; token: number } | null;
    onTitleChange: (title: string) => void;
    onExcerptChange: (excerpt: string) => void;
    onBodyChange: (body: RichTextDocument) => void;
    onGenerateExcerpt: () => Promise<void> | void;
    onAiTransform: (
        selection: string,
        preset: TransformPreset,
        instruction?: string,
    ) => Promise<void> | void;
    onUseVariant: (variant: AuthoringDraftVariant) => Promise<void> | void;
    onGenerateAgain: () => Promise<void> | void;
}) {
    return (
        <div className="grid gap-5">
            {generationError && (
                <p role="alert" className="text-sm text-destructive">
                    {generationError}
                </p>
            )}
            {(variants.length > 0 || generating) && (
                <Card>
                    <CardHeader className="sm:flex-row sm:items-start sm:justify-between">
                        <div className="grid gap-1.5">
                            <CardTitle className="text-base">
                                AI draft options
                            </CardTitle>
                            <CardDescription>
                                Drafts stay separate until you explicitly use
                                one. You can then edit every word.
                            </CardDescription>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={generating || !assistantAvailable}
                            onClick={() => void onGenerateAgain()}
                        >
                            {generating ? (
                                <LoaderCircle className="animate-spin" />
                            ) : (
                                <Sparkles />
                            )}
                            Create another draft
                        </Button>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {generating && variants.length === 0 && (
                            <div className="flex items-center gap-3 rounded-lg border p-4 text-sm text-muted-foreground">
                                <LoaderCircle className="size-5 animate-spin text-primary" />
                                The assistant is drafting from your selected
                                plan. You may safely leave this page.
                            </div>
                        )}
                        {variants.map((variant) => (
                            <div
                                key={variant.id}
                                className="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-medium">
                                            {variant.title}
                                        </p>
                                        <Badge
                                            variant={
                                                variant.status === 'failed'
                                                    ? 'destructive'
                                                    : variant.selected
                                                      ? 'default'
                                                      : 'outline'
                                            }
                                        >
                                            {variant.selected
                                                ? 'In use'
                                                : variant.status}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                        {variant.excerpt}
                                    </p>
                                    <details className="mt-3 rounded-md bg-muted/40 p-3">
                                        <summary className="cursor-pointer text-sm font-medium">
                                            Preview full draft
                                        </summary>
                                        <div className="mt-3 max-h-80 overflow-y-auto text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                            {variant.body_markdown}
                                        </div>
                                    </details>
                                </div>
                                <Button
                                    type="button"
                                    variant={
                                        variant.selected
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    disabled={
                                        variant.status !== 'ready' ||
                                        variant.selected
                                    }
                                    onClick={() => void onUseVariant(variant)}
                                >
                                    <FileCheck2 />
                                    {variant.selected
                                        ? 'Using this draft'
                                        : 'Use this draft'}
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Shape your draft</CardTitle>
                    <CardDescription>
                        This is your working copy. Changes save privately and
                        never alter the published article until you publish.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="guided-post-title">Title</Label>
                        <Input
                            id="guided-post-title"
                            value={title}
                            maxLength={255}
                            placeholder="A clear, useful title"
                            onChange={(event) =>
                                onTitleChange(event.target.value)
                            }
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor="guided-post-excerpt">
                                Short summary
                            </Label>
                            <GenerateButton
                                label="Suggest"
                                busy={generating}
                                disabled={!assistantAvailable}
                                onClick={() => void onGenerateExcerpt()}
                            />
                        </div>
                        <textarea
                            id="guided-post-excerpt"
                            value={excerpt}
                            maxLength={500}
                            rows={4}
                            className="w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                            placeholder="One or two sentences readers will see before opening the article"
                            onChange={(event) =>
                                onExcerptChange(event.target.value)
                            }
                        />
                        <div className="flex justify-between gap-3 text-xs text-muted-foreground">
                            <InputError message={errors.excerpt} />
                            <span>{excerpt.length}/500</span>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>Article</Label>
                        <RichTextEditor
                            value={body}
                            postId={postId}
                            locateTarget={locateTarget}
                            aiBusy={generating || !assistantAvailable}
                            onAiTransform={(selection, preset, instruction) =>
                                void onAiTransform(
                                    selection,
                                    preset,
                                    instruction,
                                )
                            }
                            onChange={onBodyChange}
                        />
                        <InputError message={errors.body} />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

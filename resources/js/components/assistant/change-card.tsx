import { diffWords } from 'diff';
import { Check, Crosshair, FileDiff, X } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import type { AssistantBodyPayload, AssistantChangeData } from '@/types';

const MAX_INLINE_DIFF_CHARACTERS = 12_000;
const LARGE_CHANGE_PREVIEW_CHARACTERS = 3_000;

/**
 * A reviewable body edit proposed by the co-writer: a word-level diff of the
 * touched blocks with accept/reject controls.
 */
export default function ChangeCard({
    change,
    busy,
    error = null,
    onAccept,
    onReject,
    onLocate,
}: {
    change: AssistantChangeData;
    busy: boolean;
    error?: string | null;
    onAccept: (change: AssistantChangeData) => void;
    onReject: (change: AssistantChangeData) => void;
    onLocate?: (change: AssistantChangeData) => void;
}) {
    const payload = change.payload as AssistantBodyPayload;
    const isLargeChange =
        payload.old_blocks.length + payload.new_blocks.length >
        MAX_INLINE_DIFF_CHARACTERS;

    const parts = useMemo(
        () =>
            isLargeChange
                ? []
                : diffWords(payload.old_blocks, payload.new_blocks),
        [isLargeChange, payload.old_blocks, payload.new_blocks],
    );

    const label =
        payload.old_blocks === ''
            ? 'Addition'
            : payload.new_blocks === ''
              ? 'Removal'
              : 'Rewrite';

    // Additions have no existing text to scroll to; their anchor does.
    const canLocate =
        onLocate !== undefined &&
        (payload.old_blocks !== '' ||
            payload.anchor_before !== null ||
            payload.anchor_after !== null);

    return (
        <div className="grid gap-2 rounded-lg border border-dashed border-primary/40 bg-primary/5 p-3">
            <div className="flex items-center justify-between">
                <span className="flex items-center gap-1.5 text-xs font-medium text-primary">
                    <FileDiff className="size-3.5" aria-hidden />
                    {label} in the draft
                </span>
                <div className="flex gap-1">
                    {canLocate && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-6 gap-1 px-2 text-xs text-muted-foreground"
                            onClick={() => onLocate?.(change)}
                        >
                            <Crosshair className="size-3.5" />
                            Find
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-6 gap-1 px-2 text-xs text-primary hover:text-primary"
                        disabled={busy}
                        onClick={() => onAccept(change)}
                    >
                        <Check className="size-3.5" />
                        Accept
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-6 gap-1 px-2 text-xs text-muted-foreground"
                        disabled={busy}
                        onClick={() => onReject(change)}
                    >
                        <X className="size-3.5" />
                        Reject
                    </Button>
                </div>
            </div>
            {isLargeChange ? (
                <div className="grid max-h-64 gap-2 overflow-y-auto text-sm">
                    <p className="text-xs text-muted-foreground">
                        Large change — showing shortened before and after
                        previews.
                    </p>
                    {payload.old_blocks !== '' && (
                        <div className="grid gap-1">
                            <span className="text-xs font-medium text-muted-foreground">
                                Before
                            </span>
                            <p className="whitespace-pre-wrap text-red-600/80 dark:text-red-400/80">
                                {preview(payload.old_blocks)}
                            </p>
                        </div>
                    )}
                    {payload.new_blocks !== '' && (
                        <div className="grid gap-1">
                            <span className="text-xs font-medium text-muted-foreground">
                                After
                            </span>
                            <p className="whitespace-pre-wrap text-emerald-700 dark:text-emerald-400">
                                {preview(payload.new_blocks)}
                            </p>
                        </div>
                    )}
                </div>
            ) : (
                <div className="max-h-56 overflow-y-auto text-sm leading-relaxed whitespace-pre-wrap">
                    {parts.map((part, index) =>
                        part.added ? (
                            <span
                                key={index}
                                className="rounded-sm bg-emerald-500/15 text-emerald-700 dark:text-emerald-400"
                            >
                                {part.value}
                            </span>
                        ) : part.removed ? (
                            <span
                                key={index}
                                className="rounded-sm bg-red-500/10 text-red-600/80 line-through dark:text-red-400/80"
                            >
                                {part.value}
                            </span>
                        ) : (
                            <span key={index}>{part.value}</span>
                        ),
                    )}
                </div>
            )}
            {error && (
                <p role="alert" className="text-xs text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

function preview(value: string): string {
    return value.length <= LARGE_CHANGE_PREVIEW_CHARACTERS
        ? value
        : `${value.slice(0, LARGE_CHANGE_PREVIEW_CHARACTERS)}…`;
}

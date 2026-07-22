import { diffWords } from 'diff';
import { Check, Crosshair, FileDiff, X } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import type { AssistantBodyPayload, AssistantChangeData } from '@/types';

/**
 * A reviewable body edit proposed by the co-writer: a word-level diff of the
 * touched blocks with accept/reject controls.
 */
export default function ChangeCard({
    change,
    busy,
    onAccept,
    onReject,
    onLocate,
}: {
    change: AssistantChangeData;
    busy: boolean;
    onAccept: (change: AssistantChangeData) => void;
    onReject: (change: AssistantChangeData) => void;
    onLocate?: (change: AssistantChangeData) => void;
}) {
    const payload = change.payload as AssistantBodyPayload;

    const parts = useMemo(
        () => diffWords(payload.old_blocks, payload.new_blocks),
        [payload.old_blocks, payload.new_blocks],
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
        (payload.old_blocks !== '' || payload.anchor_before !== null);

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
        </div>
    );
}

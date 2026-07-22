import { Check, Sparkles, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { AssistantChangeData, AssistantFieldPayload } from '@/types';

/**
 * An inline AI suggestion under a metadata field: shows the proposed value
 * with accept/dismiss controls. Nothing is saved until accepted.
 */
export default function FieldSuggestionChip({
    change,
    busy,
    onAccept,
    onReject,
}: {
    change: AssistantChangeData;
    busy: boolean;
    onAccept: (change: AssistantChangeData) => void;
    onReject: (change: AssistantChangeData) => void;
}) {
    const payload = change.payload as AssistantFieldPayload;
    const value = Array.isArray(payload.new)
        ? payload.new.join(', ')
        : (payload.new ?? '');

    return (
        <div className="flex items-start gap-2 rounded-md border border-dashed border-primary/40 bg-primary/5 p-2 text-sm">
            <Sparkles
                className="mt-0.5 size-3.5 shrink-0 text-primary"
                aria-hidden
            />
            <p className="flex-1 break-words whitespace-pre-wrap">{value}</p>
            <div className="flex shrink-0 gap-1">
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-6 text-primary hover:text-primary"
                    aria-label="Accept suggestion"
                    disabled={busy}
                    onClick={() => onAccept(change)}
                >
                    <Check className="size-3.5" />
                </Button>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-6 text-muted-foreground"
                    aria-label="Dismiss suggestion"
                    disabled={busy}
                    onClick={() => onReject(change)}
                >
                    <X className="size-3.5" />
                </Button>
            </div>
        </div>
    );
}

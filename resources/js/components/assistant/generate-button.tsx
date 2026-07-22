import { Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * The small ✦ affordance next to a metadata field that asks the AI to fill
 * it from the draft.
 */
export default function GenerateButton({
    label = 'Generate',
    busy,
    disabled,
    onClick,
}: {
    label?: string;
    busy: boolean;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-6 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
            disabled={busy || disabled}
            onClick={onClick}
        >
            <Sparkles
                className={busy ? 'size-3 animate-pulse' : 'size-3'}
                aria-hidden
            />
            {busy ? 'Thinking…' : label}
        </Button>
    );
}

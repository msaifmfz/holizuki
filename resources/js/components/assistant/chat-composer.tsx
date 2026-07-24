import { Send, Square } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

export default function ChatComposer({
    streaming,
    cancelling = false,
    disabled = false,
    disabledReason,
    onSend,
    onCancel,
}: {
    streaming: boolean;
    cancelling?: boolean;
    disabled?: boolean;
    disabledReason?: string | null;
    onSend: (message: string) => Promise<boolean>;
    onCancel: () => void;
}) {
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submit = async (): Promise<void> => {
        const submittedMessage = message;
        const trimmed = submittedMessage.trim();

        if (trimmed === '' || streaming || submitting || disabled) {
            return;
        }

        setSubmitting(true);

        try {
            if (await onSend(trimmed)) {
                setMessage((current) =>
                    current === submittedMessage ? '' : current,
                );
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="grid gap-1 border-t p-3">
            <div className="flex items-end gap-2">
                <Textarea
                    aria-label="Message the co-writer"
                    value={message}
                    rows={2}
                    maxLength={10_000}
                    disabled={disabled || cancelling}
                    placeholder="Ask the co-writer anything — “tighten the intro”, “add a section on…”"
                    className="max-h-40 min-h-9 flex-1 resize-none"
                    onChange={(event) => setMessage(event.target.value)}
                    onKeyDown={(event) => {
                        // Avoid submitting a half-typed IME candidate.
                        if (
                            event.key === 'Enter' &&
                            !event.shiftKey &&
                            !event.nativeEvent.isComposing
                        ) {
                            event.preventDefault();
                            void submit();
                        }
                    }}
                />
                {streaming ? (
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        aria-label={
                            cancelling
                                ? 'Stopping the assistant'
                                : 'Stop the assistant'
                        }
                        disabled={cancelling}
                        onClick={onCancel}
                    >
                        <Square className="size-4" />
                    </Button>
                ) : (
                    <Button
                        type="button"
                        size="icon"
                        aria-label="Send message"
                        disabled={
                            disabled || submitting || message.trim() === ''
                        }
                        onClick={() => void submit()}
                    >
                        <Send className="size-4" />
                    </Button>
                )}
            </div>
            {disabledReason && (
                <p className="text-xs text-muted-foreground">
                    {disabledReason}
                </p>
            )}
        </div>
    );
}

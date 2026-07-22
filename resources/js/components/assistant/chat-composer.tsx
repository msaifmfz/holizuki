import { Send, Square } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

export default function ChatComposer({
    streaming,
    onSend,
    onCancel,
}: {
    streaming: boolean;
    onSend: (message: string) => void;
    onCancel: () => void;
}) {
    const [message, setMessage] = useState('');

    const submit = () => {
        const trimmed = message.trim();

        if (trimmed === '' || streaming) {
            return;
        }

        setMessage('');
        onSend(trimmed);
    };

    return (
        <div className="flex items-end gap-2 border-t p-3">
            <Textarea
                value={message}
                rows={2}
                placeholder="Ask the co-writer anything — “tighten the intro”, “add a section on…”"
                className="max-h-40 min-h-9 flex-1 resize-none"
                onChange={(event) => setMessage(event.target.value)}
                onKeyDown={(event) => {
                    // isComposing guards against submitting a half-typed IME
                    // (CJK, etc.) message when Enter commits the candidate.
                    if (
                        event.key === 'Enter' &&
                        !event.shiftKey &&
                        !event.nativeEvent.isComposing
                    ) {
                        event.preventDefault();
                        submit();
                    }
                }}
            />
            {streaming ? (
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    aria-label="Stop the assistant"
                    onClick={onCancel}
                >
                    <Square className="size-4" />
                </Button>
            ) : (
                <Button
                    type="button"
                    size="icon"
                    aria-label="Send message"
                    disabled={message.trim() === ''}
                    onClick={submit}
                >
                    <Send className="size-4" />
                </Button>
            )}
        </div>
    );
}

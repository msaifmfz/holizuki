import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

/**
 * The empty-post starting point: describe the article, and the co-writer
 * turns it into an outline you refine in chat before drafting.
 */
export default function OutlineWizard({
    busy,
    onStart,
}: {
    busy: boolean;
    onStart: (topic: string) => void;
}) {
    const [topic, setTopic] = useState('');

    const start = () => {
        const trimmed = topic.trim();

        if (trimmed === '' || busy) {
            return;
        }

        setTopic('');
        onStart(trimmed);
    };

    return (
        <div className="grid gap-3 rounded-lg border border-dashed border-primary/40 bg-primary/5 p-4">
            <div className="flex items-center gap-2">
                <Sparkles className="size-4 text-primary" aria-hidden />
                <h3 className="text-sm font-semibold">
                    Start this article with AI
                </h3>
            </div>
            <p className="text-sm text-foreground/80">
                Describe the topic, the angle, anything you already know you
                want. The co-writer proposes an outline you can adjust in chat
                before it drafts a single word.
            </p>
            <Textarea
                value={topic}
                rows={3}
                placeholder="e.g. Why home espresso beats cafe espresso — aimed at beginners, mention my Gaggia Classic setup"
                onChange={(event) => setTopic(event.target.value)}
                onKeyDown={(event) => {
                    if (
                        event.key === 'Enter' &&
                        (event.metaKey || event.ctrlKey)
                    ) {
                        event.preventDefault();
                        start();
                    }
                }}
            />
            <div>
                <Button
                    type="button"
                    disabled={busy || topic.trim() === ''}
                    onClick={start}
                >
                    <Sparkles />
                    {busy ? 'Thinking…' : 'Outline it'}
                </Button>
            </div>
        </div>
    );
}

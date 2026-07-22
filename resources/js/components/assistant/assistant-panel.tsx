import { Loader2, Sparkles, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import ChangeCard from '@/components/assistant/change-card';
import ChatComposer from '@/components/assistant/chat-composer';
import { Button } from '@/components/ui/button';
import type { AssistantChangeData, AssistantChatEntry } from '@/types';

/**
 * The co-writer dock: conversation thread with live streaming, the agent's
 * activity ticker, and the tray of pending body diffs awaiting an
 * accept/reject decision. Field suggestions surface inline under their own
 * editor fields, not here.
 */
export default function AssistantPanel({
    open,
    onClose,
    thread,
    bodyChanges,
    streaming,
    narration,
    deciding,
    outlineReady = false,
    onDraftOutline,
    onSend,
    onCancel,
    onAccept,
    onReject,
    onLocate,
}: {
    open: boolean;
    onClose: () => void;
    thread: AssistantChatEntry[];
    bodyChanges: AssistantChangeData[];
    streaming: boolean;
    narration: string | null;
    deciding: boolean;
    outlineReady?: boolean;
    onDraftOutline?: () => void;
    onSend: (message: string) => void;
    onCancel: () => void;
    onAccept: (change: AssistantChangeData) => void;
    onReject: (change: AssistantChangeData) => void;
    onLocate?: (change: AssistantChangeData) => void;
}) {
    const scrollRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        scrollRef.current?.scrollTo({
            top: scrollRef.current.scrollHeight,
        });
    }, [thread, bodyChanges.length]);

    if (!open) {
        return null;
    }

    const pendingCount = bodyChanges.length;

    return (
        <aside
            aria-label="AI co-writer"
            className="fixed inset-y-0 right-0 z-40 flex w-full max-w-md flex-col border-l bg-background shadow-xl"
        >
            <header className="flex items-center justify-between border-b px-4 py-3">
                <div className="flex items-center gap-2">
                    <Sparkles className="size-4 text-primary" aria-hidden />
                    <h2 className="text-sm font-semibold">Co-writer</h2>
                    {pendingCount > 0 && (
                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                            {pendingCount} pending
                        </span>
                    )}
                </div>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    aria-label="Close the co-writer"
                    onClick={onClose}
                >
                    <X className="size-4" />
                </Button>
            </header>

            <div ref={scrollRef} className="flex-1 overflow-y-auto p-4">
                {thread.length === 0 && (
                    <div className="grid gap-2 py-10 text-center text-sm text-muted-foreground">
                        <Sparkles
                            className="mx-auto size-6 text-primary/50"
                            aria-hidden
                        />
                        <p>
                            The co-writer reads your draft and edits it with
                            you. Every change it proposes waits for your
                            approval.
                        </p>
                    </div>
                )}

                <div className="grid gap-3">
                    {thread.map((entry) => (
                        <div
                            key={entry.key}
                            className={
                                entry.role === 'user'
                                    ? 'ml-6 rounded-lg rounded-br-sm bg-primary/10 px-3 py-2 text-sm'
                                    : 'mr-2 text-sm'
                            }
                        >
                            {entry.role === 'assistant' &&
                            entry.text === '' &&
                            entry.state === 'streaming' ? (
                                <span className="flex items-center gap-2 text-muted-foreground">
                                    <Loader2
                                        className="size-3.5 animate-spin"
                                        aria-hidden
                                    />
                                    Thinking…
                                </span>
                            ) : (
                                <p
                                    className={
                                        entry.state === 'error'
                                            ? 'whitespace-pre-wrap text-destructive'
                                            : 'whitespace-pre-wrap'
                                    }
                                >
                                    {entry.text}
                                </p>
                            )}
                            {entry.activity && (
                                <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Loader2
                                        className="size-3 animate-spin"
                                        aria-hidden
                                    />
                                    {entry.activity}
                                </p>
                            )}
                        </div>
                    ))}
                </div>

                {streaming &&
                    !thread.some((entry) => entry.state === 'streaming') && (
                        <p className="mt-3 flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2
                                className="size-3.5 animate-spin"
                                aria-hidden
                            />
                            {narration ?? 'Working on your request…'}
                        </p>
                    )}

                {pendingCount > 0 && (
                    <div className="mt-4 grid gap-2">
                        <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Proposed changes
                        </h3>
                        {bodyChanges.map((change) => (
                            <ChangeCard
                                key={change.id}
                                change={change}
                                busy={deciding}
                                onAccept={onAccept}
                                onReject={onReject}
                                onLocate={onLocate}
                            />
                        ))}
                    </div>
                )}
            </div>

            {outlineReady && onDraftOutline && !streaming && (
                <div className="border-t px-3 py-2">
                    <Button
                        type="button"
                        size="sm"
                        className="w-full"
                        onClick={onDraftOutline}
                    >
                        <Sparkles />
                        Outline looks good — draft the article
                    </Button>
                </div>
            )}

            <ChatComposer
                streaming={streaming}
                onSend={onSend}
                onCancel={onCancel}
            />
        </aside>
    );
}

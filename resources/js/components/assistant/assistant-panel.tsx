import { Loader2, Sparkles, X } from 'lucide-react';
import { useEffect, useEffectEvent, useRef } from 'react';
import ChangeCard from '@/components/assistant/change-card';
import ChatComposer from '@/components/assistant/chat-composer';
import { Button } from '@/components/ui/button';
import { assistantCooldownMessage } from '@/lib/assistant/sse';
import type {
    AssistantChangeData,
    AssistantChatEntry,
    AssistantStatus,
} from '@/types';

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
    pendingCount,
    streaming,
    narration,
    hydrated,
    status,
    retryAt,
    stateError,
    canGenerate,
    cancelling,
    outlineReady = false,
    onDraftOutline,
    onSend,
    onCancel,
    onAccept,
    onReject,
    onRetry,
    isDeciding,
    decisionError,
    onLocate,
}: {
    open: boolean;
    onClose: () => void;
    thread: AssistantChatEntry[];
    bodyChanges: AssistantChangeData[];
    pendingCount: number;
    streaming: boolean;
    narration: string | null;
    hydrated: boolean;
    status: AssistantStatus;
    retryAt: string | null;
    stateError: string | null;
    canGenerate: boolean;
    cancelling: boolean;
    outlineReady?: boolean;
    onDraftOutline?: () => Promise<boolean>;
    onSend: (message: string) => Promise<boolean>;
    onCancel: () => void;
    onAccept: (change: AssistantChangeData) => void;
    onReject: (change: AssistantChangeData) => void;
    onRetry: () => void;
    isDeciding: (changeId: number) => boolean;
    decisionError: (changeId: number) => string | null;
    onLocate?: (change: AssistantChangeData) => void;
}) {
    const scrollRef = useRef<HTMLDivElement | null>(null);
    const closeRef = useRef<HTMLButtonElement | null>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);
    const followLatestRef = useRef(true);
    const closePanel = useEffectEvent(onClose);

    useEffect(() => {
        if (!followLatestRef.current) {
            return;
        }

        scrollRef.current?.scrollTo({
            top: scrollRef.current.scrollHeight,
        });
    }, [open, thread, bodyChanges.length]);

    useEffect(() => {
        if (!open) {
            return;
        }

        previousFocusRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        closeRef.current?.focus();

        const closeOnEscape = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closePanel();
            }
        };

        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('keydown', closeOnEscape);
            previousFocusRef.current?.focus();
        };
    }, [open]);

    if (!open) {
        return null;
    }

    const statusText = assistantStatusText(
        hydrated,
        status,
        retryAt,
        stateError,
    );
    const disabledReason = canGenerate ? null : statusText;

    return (
        <aside
            aria-label="AI co-writer"
            aria-labelledby="assistant-panel-title"
            role="dialog"
            className="fixed inset-y-0 right-0 z-40 flex w-full max-w-md flex-col border-l bg-background shadow-xl"
        >
            <p
                role="status"
                aria-live="polite"
                aria-atomic="true"
                className="sr-only"
            >
                {narration ?? statusText}
            </p>
            <header className="flex items-center justify-between border-b px-4 py-3">
                <div className="flex items-center gap-2">
                    <Sparkles className="size-4 text-primary" aria-hidden />
                    <h2
                        id="assistant-panel-title"
                        className="text-sm font-semibold"
                    >
                        Co-writer
                    </h2>
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
                    ref={closeRef}
                    aria-label="Close the co-writer"
                    onClick={onClose}
                >
                    <X className="size-4" />
                </Button>
            </header>

            <div
                ref={scrollRef}
                className="flex-1 overflow-y-auto p-4"
                onScroll={(event) => {
                    const element = event.currentTarget;
                    followLatestRef.current =
                        element.scrollHeight -
                            element.scrollTop -
                            element.clientHeight <
                        80;
                }}
            >
                {(!canGenerate || stateError) && (
                    <div className="mb-4 flex items-start justify-between gap-3 rounded-md border bg-muted/40 p-3 text-sm">
                        <p className="text-muted-foreground">{statusText}</p>
                        {(status === 'unavailable' ||
                            status === 'cooldown' ||
                            stateError) && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onRetry}
                            >
                                Retry
                            </Button>
                        )}
                    </div>
                )}

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

                <div
                    className="grid gap-3"
                    role="log"
                    aria-live="polite"
                    aria-relevant="additions text"
                >
                    {thread.map((entry) => (
                        <div
                            key={entry.key}
                            className={
                                entry.role === 'user'
                                    ? 'ml-6 rounded-lg rounded-br-sm bg-primary/10 px-3 py-2 text-sm'
                                    : 'mr-2 text-sm'
                            }
                        >
                            <span className="sr-only">
                                {entry.role === 'user'
                                    ? 'You: '
                                    : 'AI co-writer: '}
                            </span>
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

                {bodyChanges.length > 0 && (
                    <div className="mt-4 grid gap-2">
                        <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Proposed changes
                        </h3>
                        {bodyChanges.map((change) => (
                            <ChangeCard
                                key={change.id}
                                change={change}
                                busy={isDeciding(change.id)}
                                error={decisionError(change.id)}
                                onAccept={onAccept}
                                onReject={onReject}
                                onLocate={onLocate}
                            />
                        ))}
                    </div>
                )}
            </div>

            {outlineReady && onDraftOutline && !streaming && canGenerate && (
                <div className="border-t px-3 py-2">
                    <Button
                        type="button"
                        size="sm"
                        className="w-full"
                        onClick={() => void onDraftOutline()}
                    >
                        <Sparkles />
                        Outline looks good — draft the article
                    </Button>
                </div>
            )}

            <ChatComposer
                streaming={streaming}
                cancelling={cancelling}
                disabled={!canGenerate && !streaming}
                disabledReason={disabledReason}
                onSend={onSend}
                onCancel={onCancel}
            />
        </aside>
    );
}

function assistantStatusText(
    hydrated: boolean,
    status: AssistantStatus,
    retryAt: string | null,
    stateError: string | null,
): string {
    if (!hydrated) {
        return 'Checking assistant availability…';
    }

    if (stateError) {
        return stateError;
    }

    if (status === 'disabled') {
        return 'The assistant is disabled.';
    }

    if (status === 'unavailable') {
        return 'The assistant is unavailable. Check its server sign-in.';
    }

    if (status === 'running') {
        return 'The assistant is working…';
    }

    if (status === 'cancelling') {
        return 'Stopping the assistant…';
    }

    if (status === 'cooldown') {
        return assistantCooldownMessage(retryAt);
    }

    return 'The assistant is ready.';
}

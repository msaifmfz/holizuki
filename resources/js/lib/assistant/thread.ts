import type { AssistantChatEntry, AssistantTurnSummary } from '@/types';

/**
 * Whether the conversation currently holds an approved-but-undrafted
 * outline: the latest completed outline step is `start` with no later
 * `draft` step. Drives the "Draft the article" affordance.
 */
export function outlineReadyFromTurns(turns: AssistantTurnSummary[]): boolean {
    let ready = false;

    for (const turn of turns) {
        if (turn.task_type !== 'outline' || turn.status !== 'completed') {
            continue;
        }

        ready = turn.context?.step === 'start';
    }

    return ready;
}

/**
 * Rebuild the co-writer conversation from persisted turns. Only
 * conversational turns appear in the thread; one-shot tasks (metadata,
 * transforms) surface exclusively through their proposed changes.
 */
export function threadFromTurns(
    turns: AssistantTurnSummary[],
): AssistantChatEntry[] {
    const entries: AssistantChatEntry[] = [];

    for (const turn of turns) {
        if (turn.task_type !== 'chat' && turn.task_type !== 'outline') {
            continue;
        }

        entries.push({
            key: `turn-${turn.id}-user`,
            role: 'user',
            text: turn.user_prompt,
            state: 'done',
            activity: null,
        });

        entries.push({
            key: `turn-${turn.id}-assistant`,
            role: 'assistant',
            text:
                turn.status === 'failed' || turn.status === 'cancelled'
                    ? (turn.error ?? 'The assistant could not finish.')
                    : (turn.assistant_message ?? '…'),
            state: turn.status === 'completed' ? 'done' : 'error',
            activity: null,
        });
    }

    return entries;
}

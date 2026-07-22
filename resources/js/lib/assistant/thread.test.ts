import { describe, expect, it } from 'vitest';
import type { AssistantTurnSummary } from '@/types';
import { outlineReadyFromTurns, threadFromTurns } from './thread';

function turn(overrides: Partial<AssistantTurnSummary>): AssistantTurnSummary {
    return {
        id: 1,
        task_type: 'chat',
        status: 'completed',
        user_prompt: 'Hello',
        assistant_message: 'Hi there.',
        error: null,
        context: null,
        created_at: '2026-07-22T10:00:00Z',
        ...overrides,
    };
}

describe('threadFromTurns', () => {
    it('pairs each conversational turn into user and assistant entries', () => {
        const entries = threadFromTurns([
            turn({ id: 1, user_prompt: 'First', assistant_message: 'One' }),
            turn({ id: 2, user_prompt: 'Second', assistant_message: 'Two' }),
        ]);

        expect(entries.map((entry) => [entry.role, entry.text])).toEqual([
            ['user', 'First'],
            ['assistant', 'One'],
            ['user', 'Second'],
            ['assistant', 'Two'],
        ]);
        expect(entries.every((entry) => entry.state === 'done')).toBe(true);
    });

    it('skips one-shot task turns', () => {
        const entries = threadFromTurns([
            turn({ id: 1, task_type: 'metadata' }),
            turn({ id: 2, task_type: 'transform' }),
            turn({ id: 3, task_type: 'chat', user_prompt: 'Kept' }),
        ]);

        expect(entries).toHaveLength(2);
        expect(entries[0].text).toBe('Kept');
    });

    it('surfaces failed turns as error entries with their message', () => {
        const entries = threadFromTurns([
            turn({
                id: 1,
                status: 'failed',
                assistant_message: null,
                error: 'usage limit reached',
            }),
        ]);

        expect(entries[1].state).toBe('error');
        expect(entries[1].text).toBe('usage limit reached');
    });

    it('falls back to a placeholder when a failed turn has no error text', () => {
        const entries = threadFromTurns([
            turn({ id: 1, status: 'cancelled', assistant_message: null }),
        ]);

        expect(entries[1].text).toBe('The assistant could not finish.');
    });
});

describe('outlineReadyFromTurns', () => {
    it('is ready after a completed outline start step', () => {
        expect(
            outlineReadyFromTurns([
                turn({
                    id: 1,
                    task_type: 'outline',
                    context: { step: 'start' },
                }),
            ]),
        ).toBe(true);
    });

    it('is not ready once the draft step has run', () => {
        expect(
            outlineReadyFromTurns([
                turn({
                    id: 1,
                    task_type: 'outline',
                    context: { step: 'start' },
                }),
                turn({
                    id: 2,
                    task_type: 'outline',
                    context: { step: 'draft' },
                }),
            ]),
        ).toBe(false);
    });

    it('ignores incomplete outline turns and other tasks', () => {
        expect(
            outlineReadyFromTurns([
                turn({
                    id: 1,
                    task_type: 'outline',
                    status: 'running',
                    context: { step: 'start' },
                }),
                turn({ id: 2, task_type: 'chat', context: null }),
            ]),
        ).toBe(false);
    });
});

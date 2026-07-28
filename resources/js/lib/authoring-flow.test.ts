import { describe, expect, it } from 'vitest';
import {
    defaultFlowItems,
    orderFlowItemsByPlan,
    reconcileEditedFlow,
    resolveIncomingFlowState,
} from '@/lib/authoring-flow';
import type { ImageFlowState } from '@/lib/authoring-flow';
import type {
    AuthoringImageFlowItem,
    AuthoringImageGuidance,
    AuthoringMediaData,
} from '@/types';

function media(
    id: number,
    overrides: Partial<AuthoringMediaData> = {},
): AuthoringMediaData {
    return {
        id,
        url: `https://example.test/${id}.jpg`,
        width: 800,
        height: 600,
        display_name: `Photo ${id}`,
        alt_text: '',
        caption: '',
        role: 'unassigned',
        placement: 'unassigned',
        status: 'ready',
        story_priority: 'optional',
        story_notes: '',
        used_in_draft: false,
        error: null,
        ...overrides,
    };
}

function item(
    overrides: Partial<AuthoringImageFlowItem> & {
        media_ids: number[];
    },
): AuthoringImageFlowItem {
    return {
        usage: 'include',
        section_heading: 'Why gardens matter',
        position: 0,
        ...overrides,
    };
}

describe('defaultFlowItems', () => {
    it('follows the assistant recommendation but always includes required photos', () => {
        const guidance: Record<number, AuthoringImageGuidance | undefined> = {
            1: {
                usage: 'exclude',
                section_heading: null,
            } as AuthoringImageGuidance,
            2: {
                usage: 'exclude',
                section_heading: 'Find a plot',
            } as AuthoringImageGuidance,
        };

        const flow = defaultFlowItems(
            [media(1, { story_priority: 'required' }), media(2), media(3)],
            guidance,
        );

        expect(flow.map((entry) => entry.usage)).toEqual([
            'include',
            'exclude',
            'include',
        ]);
        expect(flow.map((entry) => entry.position)).toEqual([0, 1, 2]);
    });
});

describe('orderFlowItemsByPlan', () => {
    it('groups blocks by plan section and sinks skipped photos to the end', () => {
        const sections = [
            { heading: 'Why gardens matter', summary: '' },
            { heading: 'Find a plot', summary: '' },
        ];
        const ordered = orderFlowItemsByPlan(
            [
                item({
                    media_ids: [1],
                    section_heading: 'Find a plot',
                    position: 0,
                }),
                item({
                    media_ids: [2],
                    usage: 'exclude',
                    section_heading: null,
                    position: 1,
                }),
                item({
                    media_ids: [3],
                    section_heading: 'Why gardens matter',
                    position: 2,
                }),
            ],
            sections,
        );

        expect(ordered.map((entry) => entry.media_ids[0])).toEqual([3, 1, 2]);
        expect(ordered.map((entry) => entry.position)).toEqual([0, 1, 2]);
    });
});

describe('reconcileEditedFlow', () => {
    it('drops photos that no longer exist and keeps the surviving arrangement', () => {
        const reconciled = reconcileEditedFlow(
            [
                item({ media_ids: [1, 2], position: 0 }),
                item({ media_ids: [3], position: 1 }),
            ],
            [item({ id: 'server-a', media_ids: [1], position: 0 })],
            [1, 3],
        );

        // The group loses photo 2, so its survivor becomes a block of its own.
        expect(reconciled.map((entry) => entry.media_ids)).toEqual([[1], [3]]);
        expect(reconciled[0].id).toBe('server-a');
    });

    it('adopts server blocks for photos the author has not placed', () => {
        const reconciled = reconcileEditedFlow(
            [item({ media_ids: [1], position: 0 })],
            [
                item({ id: 'server-a', media_ids: [1], position: 0 }),
                item({
                    id: 'server-b',
                    media_ids: [2],
                    usage: 'exclude',
                    section_heading: null,
                    position: 1,
                }),
            ],
            [1, 2],
        );

        expect(reconciled.map((entry) => entry.media_ids)).toEqual([[1], [2]]);
        expect(reconciled[1].usage).toBe('exclude');
    });
});

describe('resolveIncomingFlowState', () => {
    const baseline = [item({ media_ids: [1], position: 0 })];

    function state(overrides: Partial<ImageFlowState> = {}): ImageFlowState {
        return {
            source: 'a',
            planId: 'plan-1',
            items: baseline,
            baselineItems: baseline,
            ...overrides,
        };
    }

    it('keeps the current state when nothing new arrived', () => {
        const current = state();

        expect(resolveIncomingFlowState(current, state(), [1])).toBe(current);
    });

    it('takes the server arrangement when the author has no unsaved edits', () => {
        const incoming = state({
            source: 'b',
            items: [item({ id: 'server-a', media_ids: [1], position: 0 })],
        });

        expect(resolveIncomingFlowState(state(), incoming, [1])).toBe(incoming);
    });

    it('discards local edits that belong to a plan the author left', () => {
        const current = state({
            items: [item({ media_ids: [1], usage: 'exclude', position: 0 })],
        });
        const incoming = state({ source: 'b', planId: 'plan-2' });

        expect(resolveIncomingFlowState(current, incoming, [1])).toBe(incoming);
    });

    it('preserves unsaved edits on the same plan', () => {
        const current = state({
            items: [
                item({
                    media_ids: [1],
                    usage: 'exclude',
                    section_heading: null,
                    position: 0,
                }),
            ],
        });
        const incoming = state({
            source: 'b',
            items: [item({ id: 'server-a', media_ids: [1], position: 0 })],
        });

        const resolved = resolveIncomingFlowState(current, incoming, [1]);

        expect(resolved.source).toBe('b');
        expect(resolved.items[0].usage).toBe('exclude');
        expect(resolved.items[0].id).toBe('server-a');
    });
});

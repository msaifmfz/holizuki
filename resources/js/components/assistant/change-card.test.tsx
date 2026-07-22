import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { AssistantChangeData } from '@/types';
import ChangeCard from './change-card';

function bodyChange(oldBlocks: string, newBlocks: string): AssistantChangeData {
    return {
        id: 7,
        type: 'body',
        status: 'proposed',
        payload: {
            old_blocks: oldBlocks,
            new_blocks: newBlocks,
            anchor_before: null,
            anchor_after: null,
        },
        turn_id: 3,
    };
}

describe('ChangeCard', () => {
    it('labels rewrites and renders both sides of the diff', () => {
        render(
            <ChangeCard
                change={bodyChange('A dull opening.', 'A gripping opening.')}
                busy={false}
                onAccept={vi.fn()}
                onReject={vi.fn()}
            />,
        );

        expect(screen.getByText(/rewrite in the draft/i)).toBeInTheDocument();
        expect(screen.getByText(/dull/)).toBeInTheDocument();
        expect(screen.getByText(/gripping/)).toBeInTheDocument();
    });

    it('labels pure additions and removals', () => {
        const { rerender } = render(
            <ChangeCard
                change={bodyChange('', 'Brand new paragraph.')}
                busy={false}
                onAccept={vi.fn()}
                onReject={vi.fn()}
            />,
        );

        expect(screen.getByText(/addition in the draft/i)).toBeInTheDocument();

        rerender(
            <ChangeCard
                change={bodyChange('Doomed paragraph.', '')}
                busy={false}
                onAccept={vi.fn()}
                onReject={vi.fn()}
            />,
        );

        expect(screen.getByText(/removal in the draft/i)).toBeInTheDocument();
    });

    it('invokes the decision callbacks with the change', async () => {
        const onAccept = vi.fn();
        const onReject = vi.fn();
        const change = bodyChange('Old.', 'New.');

        render(
            <ChangeCard
                change={change}
                busy={false}
                onAccept={onAccept}
                onReject={onReject}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: /accept/i }));
        await userEvent.click(screen.getByRole('button', { name: /reject/i }));

        expect(onAccept).toHaveBeenCalledWith(change);
        expect(onReject).toHaveBeenCalledWith(change);
    });

    it('disables decisions while busy', () => {
        render(
            <ChangeCard
                change={bodyChange('Old.', 'New.')}
                busy
                onAccept={vi.fn()}
                onReject={vi.fn()}
            />,
        );

        expect(screen.getByRole('button', { name: /accept/i })).toBeDisabled();
        expect(screen.getByRole('button', { name: /reject/i })).toBeDisabled();
    });

    it('offers a locate control for changes with existing text', async () => {
        const onLocate = vi.fn();
        const change = bodyChange(
            'An existing sentence.',
            'A revised sentence.',
        );

        render(
            <ChangeCard
                change={change}
                busy={false}
                onAccept={vi.fn()}
                onReject={vi.fn()}
                onLocate={onLocate}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: /find/i }));

        expect(onLocate).toHaveBeenCalledWith(change);
    });

    it('hides locate for a pure addition without an anchor', () => {
        render(
            <ChangeCard
                change={bodyChange('', 'A brand new sentence.')}
                busy={false}
                onAccept={vi.fn()}
                onReject={vi.fn()}
                onLocate={vi.fn()}
            />,
        );

        expect(
            screen.queryByRole('button', { name: /find/i }),
        ).not.toBeInTheDocument();
    });

    it('offers locate for an addition that has an anchor', () => {
        const change: AssistantChangeData = {
            id: 9,
            type: 'body',
            status: 'proposed',
            payload: {
                old_blocks: '',
                new_blocks: 'Inserted text.',
                anchor_before: 'The preceding paragraph.',
                anchor_after: null,
            },
            turn_id: 4,
        };

        render(
            <ChangeCard
                change={change}
                busy={false}
                onAccept={vi.fn()}
                onReject={vi.fn()}
                onLocate={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: /find/i }),
        ).toBeInTheDocument();
    });
});

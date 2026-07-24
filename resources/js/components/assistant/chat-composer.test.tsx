import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ChatComposer from './chat-composer';

describe('ChatComposer', () => {
    it('keeps the message when the turn fails to finish', async () => {
        const user = userEvent.setup();
        const onSend = vi.fn().mockResolvedValue(false);

        render(
            <ChatComposer
                streaming={false}
                onSend={onSend}
                onCancel={vi.fn()}
            />,
        );

        const input = screen.getByRole('textbox', {
            name: 'Message the co-writer',
        });
        await user.type(input, 'Keep this draft');
        await user.click(screen.getByRole('button', { name: 'Send message' }));

        expect(onSend).toHaveBeenCalledWith('Keep this draft');
        expect(input).toHaveValue('Keep this draft');
    });

    it('clears the message only after a successful turn', async () => {
        const user = userEvent.setup();

        render(
            <ChatComposer
                streaming={false}
                onSend={vi.fn().mockResolvedValue(true)}
                onCancel={vi.fn()}
            />,
        );

        const input = screen.getByRole('textbox', {
            name: 'Message the co-writer',
        });
        await user.type(input, 'Send this');
        await user.click(screen.getByRole('button', { name: 'Send message' }));

        expect(input).toHaveValue('');
    });

    it('preserves a new message drafted while the active turn finishes', async () => {
        const user = userEvent.setup();
        let finishTurn: ((succeeded: boolean) => void) | undefined;
        const onSend = vi.fn(
            () =>
                new Promise<boolean>((resolve) => {
                    finishTurn = resolve;
                }),
        );
        const { rerender } = render(
            <ChatComposer
                streaming={false}
                onSend={onSend}
                onCancel={vi.fn()}
            />,
        );
        const input = screen.getByRole('textbox', {
            name: 'Message the co-writer',
        });
        await user.type(input, 'First request');
        await user.click(screen.getByRole('button', { name: 'Send message' }));

        rerender(<ChatComposer streaming onSend={onSend} onCancel={vi.fn()} />);
        await user.clear(input);
        await user.type(input, 'Drafted next request');
        finishTurn?.(true);

        expect(input).toHaveValue('Drafted next request');
    });

    it('explains why composing is unavailable', () => {
        render(
            <ChatComposer
                streaming={false}
                disabled
                disabledReason="The assistant is cooling down."
                onSend={vi.fn().mockResolvedValue(true)}
                onCancel={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('textbox', {
                name: 'Message the co-writer',
            }),
        ).toBeDisabled();
        expect(
            screen.getByText('The assistant is cooling down.'),
        ).toBeInTheDocument();
    });
});

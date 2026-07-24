import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import OutlineWizard from './outline-wizard';

describe('OutlineWizard', () => {
    it('retains topic notes when outline generation fails', async () => {
        const user = userEvent.setup();
        const onStart = vi.fn().mockResolvedValue(false);

        render(<OutlineWizard busy={false} onStart={onStart} />);

        const input = screen.getByRole('textbox', {
            name: 'Article topic and notes',
        });
        await user.type(input, 'A careful topic');
        await user.click(screen.getByRole('button', { name: 'Outline it' }));

        expect(onStart).toHaveBeenCalledWith('A careful topic');
        expect(input).toHaveValue('A careful topic');
    });

    it('clears topic notes after successful generation', async () => {
        const user = userEvent.setup();

        render(
            <OutlineWizard
                busy={false}
                onStart={vi.fn().mockResolvedValue(true)}
            />,
        );

        const input = screen.getByRole('textbox', {
            name: 'Article topic and notes',
        });
        await user.type(input, 'A careful topic');
        await user.click(screen.getByRole('button', { name: 'Outline it' }));

        expect(input).toHaveValue('');
    });
});

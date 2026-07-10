import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Button } from '@/components/ui/button';

describe('Button', () => {
    it('forwards interactions and disabled state', async () => {
        const handleClick = vi.fn();
        const user = userEvent.setup();

        const { rerender } = render(
            <Button onClick={handleClick}>Save profile</Button>,
        );

        await user.click(screen.getByRole('button', { name: 'Save profile' }));
        expect(handleClick).toHaveBeenCalledOnce();

        rerender(<Button disabled>Save profile</Button>);
        expect(
            screen.getByRole('button', { name: 'Save profile' }),
        ).toBeDisabled();
    });
});

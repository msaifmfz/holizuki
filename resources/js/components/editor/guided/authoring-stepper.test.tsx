import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import AuthoringStepper, { AUTHORING_STEPS } from './authoring-stepper';

describe('AuthoringStepper', () => {
    it('announces the current step and allows revisiting another step', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();

        render(
            <AuthoringStepper
                steps={AUTHORING_STEPS}
                currentStep="plans"
                completedSteps={['idea', 'sources']}
                onSelect={onSelect}
            />,
        );

        expect(screen.getByRole('button', { name: /Plans/ })).toHaveAttribute(
            'aria-current',
            'step',
        );

        await user.click(screen.getByRole('button', { name: /Sources/ }));

        expect(onSelect).toHaveBeenCalledWith('sources');
    });

    it('places story photos after the plan and before drafting', () => {
        expect(AUTHORING_STEPS).toEqual([
            'idea',
            'sources',
            'plans',
            'images',
            'draft',
            'review',
        ]);
    });
});

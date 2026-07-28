import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { AuthoringPlanOption } from '@/types';
import PlansStep from './plans-step';

const plan: AuthoringPlanOption = {
    id: 'plan-10',
    title: 'A practical beginner guide',
    angle: 'Build confidence through a simple first project.',
    sections: [
        {
            heading: 'Start with the goal',
            summary: 'Explain what the reader will accomplish.',
        },
        {
            heading: 'Follow the steps',
            summary: 'Walk through the project without jargon.',
        },
    ],
    estimated_length: 'About 900 words',
    source_ids: [1],
    selected: false,
    status: 'ready',
};

describe('PlansStep', () => {
    it('continues to story photos after the author chooses a plan', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();

        render(
            <PlansStep
                plans={[plan]}
                generating={false}
                assistantAvailable
                error={null}
                onGenerate={vi.fn()}
                onSelect={onSelect}
            />,
        );

        await user.click(
            screen.getByRole('button', {
                name: 'Choose plan and add photos',
            }),
        );
        expect(onSelect).toHaveBeenCalledWith(plan);
    });
});

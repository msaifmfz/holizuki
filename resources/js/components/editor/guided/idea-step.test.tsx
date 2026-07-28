import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { AuthoringJourneyData } from '@/types';
import IdeaStep from './idea-step';

const journey: AuthoringJourneyData = {
    kind: 'creation',
    current_step: 'idea',
    completed_steps: [],
    brief: {
        topic: '',
        audience: '',
        purpose: '',
        key_points: '',
        desired_length: 'medium',
    },
    personality: 'friendly',
    custom_instructions: '',
    web_research_enabled: false,
    last_surface: 'guided',
};

describe('IdeaStep', () => {
    it('updates the brief and enables plan generation once a topic exists', async () => {
        const user = userEvent.setup();
        const onBriefChange = vi.fn();

        const { rerender } = render(
            <IdeaStep
                journey={journey}
                generating={false}
                assistantAvailable
                onBriefChange={onBriefChange}
                onPersonalityChange={vi.fn()}
                savingStyleAsDefault={false}
                onSaveStyleAsDefaultChange={vi.fn()}
                onCustomInstructionsChange={vi.fn()}
                onGeneratePlans={vi.fn()}
            />,
        );

        await user.type(
            screen.getByRole('textbox', { name: 'Your idea' }),
            'A',
        );

        expect(onBriefChange).toHaveBeenLastCalledWith({
            ...journey.brief,
            topic: 'A',
        });

        rerender(
            <IdeaStep
                journey={{
                    ...journey,
                    brief: { ...journey.brief, topic: 'Beginner espresso' },
                }}
                generating={false}
                assistantAvailable
                onBriefChange={onBriefChange}
                onPersonalityChange={vi.fn()}
                savingStyleAsDefault={false}
                onSaveStyleAsDefaultChange={vi.fn()}
                onCustomInstructionsChange={vi.fn()}
                onGeneratePlans={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', {
                name: 'Ask AI for three plans',
            }),
        ).toBeEnabled();
    });

    it('reveals custom instructions for the custom personality', () => {
        render(
            <IdeaStep
                journey={{ ...journey, personality: 'custom' }}
                generating={false}
                assistantAvailable
                onBriefChange={vi.fn()}
                onPersonalityChange={vi.fn()}
                savingStyleAsDefault={false}
                onSaveStyleAsDefaultChange={vi.fn()}
                onCustomInstructionsChange={vi.fn()}
                onGeneratePlans={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('textbox', {
                name: 'Custom writing instructions',
            }),
        ).toBeInTheDocument();
    });

    it('keeps promoting the style to a default an explicit choice', async () => {
        const user = userEvent.setup();
        const onSaveStyleAsDefaultChange = vi.fn();

        render(
            <IdeaStep
                journey={journey}
                generating={false}
                assistantAvailable
                onBriefChange={vi.fn()}
                onPersonalityChange={vi.fn()}
                savingStyleAsDefault={false}
                onSaveStyleAsDefaultChange={onSaveStyleAsDefaultChange}
                onCustomInstructionsChange={vi.fn()}
                onGeneratePlans={vi.fn()}
            />,
        );

        const saveAsDefault = screen.getByRole('checkbox', {
            name: /starting point for new articles/i,
        });

        expect(saveAsDefault).not.toBeChecked();

        await user.click(saveAsDefault);

        expect(onSaveStyleAsDefaultChange).toHaveBeenCalledWith(true);
    });
});

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ReviewStep from './review-step';

describe('ReviewStep', () => {
    it('does not enable publication until every required item is complete', () => {
        render(
            <ReviewStep
                status="draft"
                checks={[
                    {
                        id: 'title',
                        label: 'Title',
                        help: 'Add a title.',
                        complete: true,
                    },
                    {
                        id: 'image',
                        label: 'Featured image',
                        help: 'Choose an image.',
                        complete: false,
                    },
                ]}
                publishing={false}
                scheduleAt=""
                publishingErrors={[]}
                organization={<div>Organization</div>}
                image={<div>Image</div>}
                onScheduleAtChange={vi.fn()}
                onPreview={vi.fn()}
                onPublish={vi.fn()}
                onSchedule={vi.fn()}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Publish now' }),
        ).toBeDisabled();
        expect(screen.getByText('1 item is still required.')).toBeVisible();
    });

    it('keeps preview available before publishing', async () => {
        const user = userEvent.setup();
        const onPreview = vi.fn();

        render(
            <ReviewStep
                status="draft"
                checks={[]}
                publishing={false}
                scheduleAt=""
                publishingErrors={[]}
                organization={<div />}
                image={<div />}
                onScheduleAtChange={vi.fn()}
                onPreview={onPreview}
                onPublish={vi.fn()}
                onSchedule={vi.fn()}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Preview working draft' }),
        );

        expect(onPreview).toHaveBeenCalledOnce();
    });
});

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type {
    AuthoringImageComposition,
    AuthoringImagePassage,
    AuthoringMediaData,
    AuthoringPlanOption,
} from '@/types';
import ImagesStep from './images-step';

const plan: AuthoringPlanOption = {
    id: 'plan-10',
    title: 'A practical guide',
    angle: 'Teach through one clear story.',
    sections: [
        { heading: 'The first harvest', summary: '' },
        { heading: 'What changed', summary: '' },
    ],
    estimated_length: 'Medium',
    source_ids: [],
    selected: true,
    status: 'ready',
};

const media: AuthoringMediaData = {
    id: 7,
    url: '/storage/posts/example.webp',
    display_name: 'Coffee harvest',
    alt_text: '',
    caption: '',
    role: 'illustration',
    placement: 'body',
    width: 1200,
    height: 800,
    status: 'ready',
    story_priority: 'optional',
    story_notes: '',
    used_in_draft: false,
    error: null,
};

const composition: AuthoringImageComposition = {
    id: 'composition-1',
    plan_id: plan.id,
    usage: 'include',
    section_heading: 'The first harvest',
    position: 0,
    passage_markdown: '',
    selected_passage_id: null,
    passage_stale: false,
    media: [media],
};

const passages: AuthoringImagePassage[] = [1, 2, 3].map((number) => ({
    id: `passage-${number}`,
    composition_id: composition.id,
    label: `Option ${number}`,
    passage_markdown: `Generated passage ${number}.`,
    selected: false,
    status: 'ready',
}));

const renderStepDefaults: React.ComponentProps<typeof ImagesStep> = {
    plan,
    media: [],
    guidance: {},
    groupSuggestions: [],
    compositions: [],
    passages: [],
    featuredImageUrl: null,
    uploading: false,
    reviewing: false,
    savingFlow: false,
    generatingPassagesFor: null,
    savingPassage: false,
    authoringBusy: false,
    assistantAvailable: true,
    error: null,
    onUpload: vi.fn(),
    onChange: vi.fn(),
    onRemove: vi.fn(),
    onReview: vi.fn(),
    onPendingChangesChange: vi.fn(),
    onSaveFlow: vi.fn(),
    onGeneratePassages: vi.fn(),
    onSavePassage: vi.fn(),
};

function renderStep(
    overrides: Partial<React.ComponentProps<typeof ImagesStep>> = {},
) {
    const props: React.ComponentProps<typeof ImagesStep> = {
        ...renderStepDefaults,
        ...overrides,
    };

    render(<ImagesStep {...props} />);

    return props;
}

describe('ImagesStep', () => {
    it('passes every selected story photo to the batch uploader', () => {
        const onUpload = vi.fn();

        renderStep({ onUpload });

        const files = [
            new File(['one'], 'one.jpg', { type: 'image/jpeg' }),
            new File(['two'], 'two.webp', { type: 'image/webp' }),
        ];
        fireEvent.change(
            screen.getByLabelText<HTMLInputElement>('Story photos', {
                selector: 'input',
            }),
            { target: { files } },
        );

        expect(onUpload).toHaveBeenCalledWith(files);
    });

    it('keeps AI narrative guidance reviewable and applies metadata only on request', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        renderStep({
            media: [media],
            guidance: {
                7: {
                    id: 'guidance-1',
                    media_id: 7,
                    suggested_name: 'First harvest after the drought',
                    alt_text: 'Hands holding a small basket of coffee fruit.',
                    caption: 'The first harvest after a difficult dry season.',
                    usage: 'include',
                    section_heading: 'The first harvest',
                    narrative_role: 'Evidence of the difficult season',
                    writing_angle:
                        'Explain why the smaller fruit surprised the growers.',
                    talking_points: [
                        'What the growers expected',
                        'What they found',
                    ],
                    transition_before: 'Move from the drought to its result.',
                    transition_after: 'Continue with the response.',
                    rationale: 'The photo makes the consequence tangible.',
                    questions: ['Who is holding the basket?'],
                    status: 'ready',
                },
            },
            onChange,
        });

        expect(
            screen.getByText(
                'Explain why the smaller fruit surprised the growers.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Who is holding the basket?'),
        ).toBeInTheDocument();
        expect(onChange).not.toHaveBeenCalled();

        await user.click(
            screen.getByRole('button', {
                name: 'Use suggested name, alt text, and caption',
            }),
        );

        expect(onChange).toHaveBeenCalledWith(media, {
            display_name: 'First harvest after the drought',
            alt_text: 'Hands holding a small basket of coffee fruit.',
            caption: 'The first harvest after a difficult dry season.',
        });
    });

    it('saves author context separately from the image pixels', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn().mockResolvedValue(true);

        renderStep({ media: [media], onChange });

        const notes = screen.getByRole('textbox', {
            name: 'What should readers understand from this photo?',
        });
        await user.type(notes, 'This was the first harvest after the drought.');
        fireEvent.blur(notes);

        expect(onChange).toHaveBeenCalledWith(media, {
            story_notes: 'This was the first harvest after the drought.',
        });
    });

    it('asks for confirmation before permanently removing a story photo', async () => {
        const user = userEvent.setup();
        const onRemove = vi.fn();

        renderStep({ media: [media], onRemove });

        await user.click(
            screen.getByRole('button', { name: 'Remove Coffee harvest' }),
        );

        expect(onRemove).not.toHaveBeenCalled();
        expect(
            screen.getByRole('heading', {
                name: 'Remove “Coffee harvest”?',
            }),
        ).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Remove photo' }));

        expect(onRemove).toHaveBeenCalledWith(media);
    });

    it('never shows the hero image as a story photo', () => {
        renderStep({
            media: [
                {
                    ...media,
                    role: 'hero',
                    placement: 'featured',
                },
            ],
            featuredImageUrl: media.url,
        });

        expect(
            screen.getByText(
                'No story photos yet. You can continue without them.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('textbox', { name: 'Photo name' }),
        ).not.toBeInTheDocument();
    });

    it('lets the author accept an ordered multi-photo sequence before saving', async () => {
        const user = userEvent.setup();
        const onSaveFlow = vi.fn();
        const secondMedia = {
            ...media,
            id: 8,
            url: '/storage/posts/second.webp',
            display_name: 'Sorting the fruit',
        };

        renderStep({
            media: [media, secondMedia],
            groupSuggestions: [
                {
                    id: 'group-1',
                    title: 'From harvest to sorting',
                    media_ids: [7, 8],
                    section_heading: 'The first harvest',
                    narrative_role: 'A short chronological sequence',
                    rationale: 'The two photos show one continuous moment.',
                    status: 'ready',
                },
            ],
            onSaveFlow,
        });

        await user.click(
            screen.getByRole('button', { name: 'Use this sequence' }),
        );
        await user.click(
            screen.getByRole('button', { name: 'Save photo flow' }),
        );

        expect(onSaveFlow).toHaveBeenCalledWith(plan, [
            {
                usage: 'include',
                section_heading: 'The first harvest',
                position: 0,
                media_ids: [7, 8],
            },
        ]);
    });

    it('keeps exclusions individual when a photo leaves an accepted sequence', async () => {
        const user = userEvent.setup();
        const onSaveFlow = vi.fn();
        const secondMedia = {
            ...media,
            id: 8,
            url: '/storage/posts/second.webp',
            display_name: 'Sorting the fruit',
        };

        renderStep({
            media: [media, secondMedia],
            groupSuggestions: [
                {
                    id: 'group-1',
                    title: 'From harvest to sorting',
                    media_ids: [7, 8],
                    section_heading: 'The first harvest',
                    narrative_role: 'A short chronological sequence',
                    rationale: 'The two photos show one continuous moment.',
                    status: 'ready',
                },
            ],
            onSaveFlow,
        });

        await user.click(
            screen.getByRole('button', { name: 'Use this sequence' }),
        );
        await user.click(
            screen.getAllByRole('combobox', {
                name: 'Use in this article',
            })[0],
        );
        await user.click(screen.getByRole('option', { name: 'Leave it out' }));
        await user.click(
            screen.getByRole('button', { name: 'Save photo flow' }),
        );

        expect(onSaveFlow).toHaveBeenCalledWith(plan, [
            {
                usage: 'include',
                section_heading: 'The first harvest',
                position: 0,
                media_ids: [8],
            },
            {
                usage: 'exclude',
                section_heading: null,
                position: 1,
                media_ids: [7],
            },
        ]);
    });

    it('preserves unsaved flow choices when uploaded media refreshes the source', async () => {
        const user = userEvent.setup();
        const onSaveFlow = vi.fn();
        const secondMedia = {
            ...media,
            id: 8,
            url: '/storage/posts/second.webp',
            display_name: 'Sorting the fruit',
        };
        const props = {
            ...renderStepDefaults,
            media: [media],
            onSaveFlow,
        };
        const view = render(<ImagesStep {...props} />);

        await user.click(
            screen.getByRole('combobox', { name: 'Use in this article' }),
        );
        await user.click(screen.getByRole('option', { name: 'Leave it out' }));

        view.rerender(<ImagesStep {...props} media={[media, secondMedia]} />);

        expect(
            screen.getAllByRole('combobox', {
                name: 'Use in this article',
            })[0],
        ).toHaveTextContent('Leave it out');
        await user.click(
            screen.getAllByRole('combobox', {
                name: 'Article section',
            })[1],
        );
        await user.click(screen.getByRole('option', { name: 'What changed' }));

        await user.click(
            screen.getByRole('button', { name: 'Save photo flow' }),
        );

        expect(onSaveFlow).toHaveBeenCalledWith(plan, [
            {
                usage: 'include',
                section_heading: 'What changed',
                position: 0,
                media_ids: [8],
            },
            {
                usage: 'exclude',
                section_heading: null,
                position: 1,
                media_ids: [7],
            },
        ]);
    });

    it('explains missing photo-flow details before enabling save', async () => {
        const user = userEvent.setup();

        renderStep({ media: [media] });

        expect(
            screen.getByText(
                'Choose an article section for every included photo.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save photo flow' }),
        ).toBeDisabled();

        await user.click(
            screen.getByRole('combobox', { name: 'Article section' }),
        );
        await user.click(
            screen.getByRole('option', { name: 'The first harvest' }),
        );

        expect(
            screen.getByRole('button', { name: 'Save photo flow' }),
        ).toBeEnabled();
    });

    it('lets the author reorder photo blocks before saving the flow', async () => {
        const user = userEvent.setup();
        const onSaveFlow = vi.fn();
        const secondMedia = {
            ...media,
            id: 8,
            url: '/storage/posts/second.webp',
            display_name: 'Sorting the fruit',
        };

        renderStep({
            media: [media, secondMedia],
            onSaveFlow,
        });

        const sectionSelectors = screen.getAllByRole('combobox', {
            name: 'Article section',
        });
        await user.click(sectionSelectors[0]);
        await user.click(
            screen.getByRole('option', { name: 'The first harvest' }),
        );
        await user.click(sectionSelectors[1]);
        await user.click(screen.getByRole('option', { name: 'What changed' }));
        expect(
            screen.getByRole('button', {
                name: 'Move Sorting the fruit up',
            }),
        ).toBeDisabled();

        await user.click(sectionSelectors[1]);
        await user.click(
            screen.getByRole('option', { name: 'The first harvest' }),
        );
        await user.click(
            screen.getByRole('button', {
                name: 'Move Sorting the fruit up',
            }),
        );
        await user.click(
            screen.getByRole('button', { name: 'Save photo flow' }),
        );

        expect(onSaveFlow).toHaveBeenCalledWith(plan, [
            {
                usage: 'include',
                section_heading: 'The first harvest',
                position: 0,
                media_ids: [8],
            },
            {
                usage: 'include',
                section_heading: 'The first harvest',
                position: 1,
                media_ids: [7],
            },
        ]);
    });

    it('keeps an already saved photo sequence valid after AI suggestions change', () => {
        const secondMedia = {
            ...media,
            id: 8,
            url: '/storage/posts/second.webp',
            display_name: 'Sorting the fruit',
        };

        renderStep({
            media: [media, secondMedia],
            compositions: [
                {
                    ...composition,
                    media: [media, secondMedia],
                },
            ],
            groupSuggestions: [],
        });

        expect(
            screen.getByRole('button', { name: 'Photo flow saved' }),
        ).toBeDisabled();
        expect(
            screen.queryByText(
                'Keep an older photo sequence separate, or choose a current AI sequence.',
            ),
        ).not.toBeInTheDocument();
    });

    it('does not replace saved passages when offering a new photo sequence', () => {
        const secondMedia = {
            ...media,
            id: 8,
            url: '/storage/posts/second.webp',
            display_name: 'Sorting the fruit',
        };

        renderStep({
            media: [media, secondMedia],
            compositions: [
                {
                    ...composition,
                    passage_markdown: 'Saved writing for the first photo.',
                },
                {
                    ...composition,
                    id: 'composition-2',
                    position: 1,
                    passage_markdown: 'Saved writing for the second photo.',
                    media: [secondMedia],
                },
            ],
            groupSuggestions: [
                {
                    id: 'group-1',
                    title: 'Harvest and sorting',
                    media_ids: [7, 8],
                    section_heading: 'The first harvest',
                    narrative_role: 'A tighter sequence',
                    rationale: 'These photos are visually related.',
                    status: 'ready',
                },
            ],
        });

        expect(
            screen.getByRole('button', { name: 'Use this sequence' }),
        ).toBeDisabled();
        expect(
            screen.getByText(
                'Keep these photos separate to protect passages you already wrote.',
            ),
        ).toBeInTheDocument();
    });

    it('settles after the server saves an excluded photo without a section', async () => {
        const user = userEvent.setup();
        const onSaveFlow = vi.fn();
        const props = {
            ...renderStepDefaults,
            media: [media],
            compositions: [composition],
            onSaveFlow,
        };
        const view = render(<ImagesStep {...props} />);

        await user.click(
            screen.getByRole('combobox', { name: 'Use in this article' }),
        );
        await user.click(screen.getByRole('option', { name: 'Leave it out' }));
        await user.click(
            screen.getByRole('button', { name: 'Save photo flow' }),
        );

        expect(onSaveFlow).toHaveBeenCalledWith(plan, [
            {
                id: composition.id,
                usage: 'exclude',
                section_heading: null,
                position: 0,
                media_ids: [7],
            },
        ]);

        view.rerender(
            <ImagesStep
                {...props}
                compositions={[
                    {
                        ...composition,
                        usage: 'exclude',
                        section_heading: null,
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Photo flow saved' }),
        ).toBeDisabled();
    });

    it('keeps photos outside a newly accepted overlapping sequence', async () => {
        const user = userEvent.setup();
        const onSaveFlow = vi.fn();
        const secondMedia = {
            ...media,
            id: 8,
            url: '/storage/posts/second.webp',
            display_name: 'Sorting the fruit',
        };
        const thirdMedia = {
            ...media,
            id: 9,
            url: '/storage/posts/third.webp',
            display_name: 'Sharing the harvest',
        };
        const groupedComposition = {
            ...composition,
            media: [media, secondMedia, thirdMedia],
        };

        renderStep({
            media: [media, secondMedia, thirdMedia],
            compositions: [groupedComposition],
            groupSuggestions: [
                {
                    id: 'group-1',
                    title: 'Harvest and sorting',
                    media_ids: [7, 8],
                    section_heading: 'The first harvest',
                    narrative_role: 'A tighter opening sequence',
                    rationale: 'These two photos show the central action.',
                    status: 'ready',
                },
            ],
            onSaveFlow,
        });

        await user.click(
            screen.getByRole('button', { name: 'Use this sequence' }),
        );
        await user.click(
            screen.getByRole('button', { name: 'Save photo flow' }),
        );

        expect(onSaveFlow).toHaveBeenCalledWith(plan, [
            {
                usage: 'include',
                section_heading: 'The first harvest',
                position: 0,
                media_ids: [7, 8],
            },
            {
                usage: 'include',
                section_heading: 'The first harvest',
                position: 1,
                media_ids: [9],
            },
        ]);
    });

    it('offers three passages and saves the selected editable copy', async () => {
        const user = userEvent.setup();
        const onSavePassage = vi.fn();

        renderStep({
            media: [media],
            compositions: [composition],
            passages,
            onSavePassage,
        });

        await user.click(screen.getByRole('radio', { name: /Option 2/ }));
        const editor = screen.getByRole('textbox', {
            name: 'Selected passage — edit as much as you like',
        });
        expect(editor).toHaveValue('Generated passage 2.');
        fireEvent.change(editor, {
            target: {
                value: '  Generated passage 2. Edited by the author.  ',
            },
        });
        await user.click(screen.getByRole('button', { name: 'Save passage' }));

        expect(onSavePassage).toHaveBeenCalledWith(composition, {
            selected_passage_id: 'passage-2',
            passage_markdown: 'Generated passage 2. Edited by the author.',
        });
    });

    it('reports unsaved passage edits so navigation cannot discard them', async () => {
        const onPendingChangesChange = vi.fn();

        renderStep({
            media: [media],
            compositions: [
                {
                    ...composition,
                    passage_markdown: 'The approved passage.',
                },
            ],
            onPendingChangesChange,
        });

        fireEvent.change(screen.getByLabelText('Write your own passage'), {
            target: { value: 'An edited but unsaved passage.' },
        });

        await waitFor(() =>
            expect(onPendingChangesChange).toHaveBeenLastCalledWith(true),
        );
    });

    it('lets the author reapprove unchanged words after photo context changes', async () => {
        const user = userEvent.setup();
        const onSavePassage = vi.fn();
        const staleComposition = {
            ...composition,
            passage_markdown: 'The author wants to keep these exact words.',
            passage_stale: true,
        };

        renderStep({
            media: [media],
            compositions: [staleComposition],
            onSavePassage,
        });

        await user.click(
            screen.getByRole('button', {
                name: 'Review and save passage',
            }),
        );

        expect(onSavePassage).toHaveBeenCalledWith(staleComposition, {
            selected_passage_id: null,
            passage_markdown: 'The author wants to keep these exact words.',
        });
    });

    it('disables every AI start and photo removal while any authoring task is active', () => {
        const secondMedia = {
            ...media,
            id: 8,
            url: '/storage/posts/second.webp',
            display_name: 'Sorting the fruit',
        };
        const secondComposition = {
            ...composition,
            id: 'composition-2',
            position: 1,
            media: [secondMedia],
        };

        renderStep({
            media: [media, secondMedia],
            compositions: [composition, secondComposition],
            generatingPassagesFor: composition.id,
            authoringBusy: true,
        });

        expect(
            screen.getByRole('button', {
                name: 'Ask AI to plan the photo flow',
            }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Writing options…' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', {
                name: 'Write three passage options',
            }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Remove Coffee harvest' }),
        ).toBeDisabled();
    });
});

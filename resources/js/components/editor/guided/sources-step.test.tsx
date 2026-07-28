import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { PostSourceData } from '@/types';
import SourcesStep from './sources-step';

const source: PostSourceData = {
    id: 1,
    kind: 'document',
    title: 'Interview notes',
    url: null,
    content: 'A short excerpt',
    original_name: 'notes.txt',
    mime_type: 'text/plain',
    size_bytes: 2048,
    status: 'ready',
    approved: false,
    visibility: 'private',
    error: null,
    created_at: '2026-07-25T12:00:00Z',
};

function renderStep(
    overrides: Partial<React.ComponentProps<typeof SourcesStep>> = {},
) {
    const props: React.ComponentProps<typeof SourcesStep> = {
        sources: [],
        researchFindings: [],
        uploading: false,
        researching: false,
        assistantAvailable: true,
        webResearchEnabled: false,
        error: null,
        onAddNote: vi.fn(),
        onAddUrl: vi.fn(),
        onUpload: vi.fn(),
        onApprovalChange: vi.fn(),
        onVisibilityChange: vi.fn(),
        onRemove: vi.fn(),
        onWebResearchChange: vi.fn(),
        onResearch: vi.fn(),
        onApproveResearch: vi.fn(),
        ...overrides,
    };

    render(<SourcesStep {...props} />);

    return props;
}

describe('SourcesStep', () => {
    it('adds an author note and clears it after completion', async () => {
        const user = userEvent.setup();
        const onAddNote = vi.fn().mockResolvedValue(undefined);

        renderStep({ onAddNote });

        const note = screen.getByRole('textbox', { name: 'Paste a note' });
        await user.type(note, 'Remember the accessibility examples.');
        await user.click(screen.getByRole('button', { name: 'Add note' }));

        expect(onAddNote).toHaveBeenCalledWith(
            'Remember the accessibility examples.',
        );
        expect(note).toHaveValue('');
    });

    it('requires explicit source approval and web research opt-in', async () => {
        const user = userEvent.setup();
        const onApprovalChange = vi.fn();
        const onWebResearchChange = vi.fn();

        renderStep({
            sources: [source],
            onApprovalChange,
            onWebResearchChange,
        });

        await user.click(
            screen.getByRole('checkbox', {
                name: 'Let AI use this source',
            }),
        );
        expect(onApprovalChange).toHaveBeenCalledWith(source, true);

        expect(
            screen.getByRole('button', { name: 'Find useful sources' }),
        ).toBeDisabled();

        await user.click(
            screen.getByRole('checkbox', {
                name: /Allow web research/,
            }),
        );
        expect(onWebResearchChange).toHaveBeenCalledWith(true);
    });

    it('allows an unapproved matching research link to be approved again', async () => {
        const user = userEvent.setup();
        const onApproveResearch = vi.fn();
        const finding = {
            id: 'finding-one',
            title: 'Accessible community gardens',
            summary: 'A useful summary.',
            url: 'https://example.com/gardens',
            publisher: 'Example',
            published_at: null,
            suggested_citation: 'Example, Accessible community gardens',
        };

        renderStep({
            sources: [
                {
                    ...source,
                    id: 2,
                    kind: 'url',
                    title: finding.title,
                    url: finding.url,
                    original_name: null,
                },
            ],
            researchFindings: [finding],
            onApproveResearch,
        });

        const approve = screen.getByRole('button', {
            name: 'Approve this source',
        });
        expect(approve).toBeEnabled();

        await user.click(approve);

        expect(onApproveResearch).toHaveBeenCalledWith(finding);
    });
});

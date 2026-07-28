import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import PublicRichText, {
    CitationSources,
    CodeBlock,
    ExpandableImage,
} from '@/components/public/public-rich-text';
import type { RichTextDocument } from '@/types';

describe('public rich text renderer', () => {
    it('renders supported prose and escapes unknown content', () => {
        const value = {
            type: 'doc',
            content: [
                {
                    type: 'heading',
                    attrs: { level: 2 },
                    content: [{ type: 'text', text: 'Reader safety' }],
                },
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: '<script>alert(1)</script>',
                            marks: [{ type: 'bold' }],
                        },
                    ],
                },
            ],
        } satisfies RichTextDocument;

        const { container } = render(<PublicRichText value={value} />);

        expect(
            screen.getByRole('heading', { name: 'Reader safety' }),
        ).toHaveAttribute('id', 'reader-safety');
        expect(screen.getByText('<script>alert(1)</script>')).toBeVisible();
        expect(container.querySelector('script')).toBeNull();
        expect(container.querySelector('strong')).toHaveTextContent(
            '<script>alert(1)</script>',
        );
    });

    it('drops unsafe links while preserving safe external links', () => {
        const value = {
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'Unsafe',
                            marks: [
                                {
                                    type: 'link',
                                    attrs: { href: 'javascript:alert(1)' },
                                },
                            ],
                        },
                        { type: 'text', text: ' and ' },
                        {
                            type: 'text',
                            text: 'safe',
                            marks: [
                                {
                                    type: 'link',
                                    attrs: { href: 'https://example.com' },
                                },
                            ],
                        },
                    ],
                },
            ],
        } satisfies RichTextDocument;

        const { container } = render(<PublicRichText value={value} />);

        expect(container).toHaveTextContent('Unsafe and safe');
        expect(container.querySelector('a[href^="javascript:"]')).toBeNull();
        expect(screen.getByRole('link', { name: 'safe' })).toHaveAttribute(
            'rel',
            'noopener noreferrer',
        );
    });

    it('highlights supported code and copies the original text', async () => {
        const user = userEvent.setup();
        const writeText = vi.spyOn(navigator.clipboard, 'writeText');

        render(<CodeBlock code={'<?php\necho "hello";'} language="php" />);

        expect(screen.getByText('PHP')).toBeVisible();
        expect(document.querySelector('code')).toHaveClass('language-php');

        await user.click(screen.getByRole('button', { name: 'Copy code' }));

        expect(writeText).toHaveBeenCalledWith('<?php\necho "hello";');
        expect(
            screen.getByRole('button', { name: 'Code copied' }),
        ).toBeVisible();
    });

    it('expands captioned images and omits unsafe sources', async () => {
        const user = userEvent.setup();
        const { rerender } = render(
            <ExpandableImage
                attrs={{
                    src: '/storage/posts/diagram.webp',
                    alt: 'Queue architecture',
                    caption: 'A resilient queue topology',
                    width: 1200,
                    height: 675,
                }}
            />,
        );

        const image = screen.getByRole('img', { name: 'Queue architecture' });

        expect(image).toHaveAttribute('width', '1200');
        expect(image).toHaveAttribute('height', '675');
        expect(screen.getByText('A resilient queue topology')).toBeVisible();
        expect(
            screen.getByRole('button', {
                name: 'Expand image: Queue architecture',
            }),
        ).toBeVisible();

        await user.click(
            screen.getByRole('button', {
                name: 'Expand image: Queue architecture',
            }),
        );

        expect(screen.getByRole('dialog')).toBeVisible();
        expect(screen.getByText('Expanded article image')).toBeVisible();

        await user.keyboard('{Escape}');

        expect(screen.queryByRole('dialog')).toBeNull();

        rerender(
            <ExpandableImage
                attrs={{ src: 'javascript:alert(1)', alt: 'Unsafe image' }}
            />,
        );

        expect(screen.queryByRole('img')).toBeNull();
    });

    it('renders numbered citations and a privacy-safe source panel', () => {
        const value = {
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [
                        { type: 'text', text: 'A documented claim' },
                        {
                            type: 'citation',
                            attrs: { sourceId: 9, number: 1 },
                        },
                        {
                            type: 'citation',
                            attrs: { sourceId: 9, number: 1 },
                        },
                        {
                            type: 'citation',
                            attrs: { sourceId: 999, number: 2 },
                        },
                    ],
                },
            ],
        } satisfies RichTextDocument;
        const citations = [
            {
                number: 1,
                source_id: 9,
                title: 'Private field notes',
                kind: 'document' as const,
                url: null,
            },
            {
                number: 2,
                source_id: 12,
                title: 'Public handbook',
                kind: 'url' as const,
                url: 'https://example.com/handbook',
            },
        ];

        const { container } = render(
            <>
                <PublicRichText value={value} citations={citations} />
                <CitationSources citations={citations} />
            </>,
        );

        expect(
            screen.getAllByRole('link', {
                name: 'Citation 1: Private field notes',
            }),
        ).toHaveLength(2);
        expect(container).not.toHaveTextContent('[2][2]');
        expect(screen.getByText('Private field notes')).toBeVisible();
        expect(
            screen.queryByRole('link', { name: 'Private field notes' }),
        ).toBeNull();
        expect(
            screen.getByRole('link', {
                name: /Public handbook.*opens in a new tab/,
            }),
        ).toHaveAttribute('href', 'https://example.com/handbook');
    });
});

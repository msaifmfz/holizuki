import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import PostTableOfContents from '@/components/public/post-table-of-contents';
import type { TableOfContentsItem } from '@/types';

const items: TableOfContentsItem[] = [
    { id: 'arriving', title: 'Arriving', level: 2 },
    { id: 'the-bath', title: 'The bath', level: 3 },
    { id: 'worth-it', title: 'Was it worth it?', level: 2 },
];

describe('post table of contents', () => {
    it('stays hidden until there are enough headings to navigate', () => {
        const { container } = render(
            <PostTableOfContents items={items.slice(0, 2)} />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('renders a mobile and a desktop copy of every heading', () => {
        render(<PostTableOfContents items={items} />);

        expect(screen.getAllByRole('link', { name: 'Arriving' })).toHaveLength(
            2,
        );
        expect(
            screen.getAllByRole('navigation', { name: 'Table of contents' }),
        ).toHaveLength(2);
    });

    it('indents sub-headings on the anchor so the active marker can line up', () => {
        // The marker is absolutely positioned against the <li>, so it ignores
        // padding on that element — the indent has to live on the anchor, and
        // the marker offset has to match it.
        const { container } = render(<PostTableOfContents items={items} />);

        const subHeading = screen.getAllByRole('link', {
            name: 'The bath',
        })[0];

        expect(subHeading).toHaveClass('ml-3');
        expect(subHeading.closest('li')).not.toHaveClass('pl-3');

        const marker = container.querySelector('[aria-hidden].bg-primary');

        // The first item is active on mount, and it is a level 2 heading.
        expect(marker).toHaveClass('left-0');
    });

    it('marks the first heading as the current location on mount', () => {
        render(<PostTableOfContents items={items} />);

        for (const link of screen.getAllByRole('link', { name: 'Arriving' })) {
            expect(link).toHaveAttribute('aria-current', 'location');
        }
    });
});

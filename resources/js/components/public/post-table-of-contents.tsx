import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { TableOfContentsItem } from '@/types';

export default function PostTableOfContents({
    items,
}: {
    items: TableOfContentsItem[];
}) {
    const [activeId, setActiveId] = useState(items[0]?.id ?? '');

    useEffect(() => {
        const headings = items
            .map((item) => document.getElementById(item.id))
            .filter((heading): heading is HTMLElement => heading !== null);

        if (headings.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort(
                        (first, second) =>
                            first.boundingClientRect.top -
                            second.boundingClientRect.top,
                    );

                if (visible[0]?.target.id) {
                    setActiveId(visible[0].target.id);
                }
            },
            { rootMargin: '-5rem 0px -70% 0px' },
        );

        headings.forEach((heading) => observer.observe(heading));

        return () => observer.disconnect();
    }, [items]);

    if (items.length < 3) {
        return null;
    }

    const links = (
        <ol className="grid gap-1 text-sm">
            {items.map((item) => (
                <li key={item.id} className={item.level === 3 ? 'pl-3' : ''}>
                    <a
                        href={`#${item.id}`}
                        className={cn(
                            'block rounded-md px-2 py-1.5 leading-5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground',
                            activeId === item.id &&
                                'bg-muted font-medium text-foreground',
                        )}
                        aria-current={
                            activeId === item.id ? 'location' : undefined
                        }
                    >
                        {item.title}
                    </a>
                </li>
            ))}
        </ol>
    );

    return (
        <>
            <details className="post-toc rounded-xl border bg-muted/20 p-4 lg:hidden">
                <summary className="cursor-pointer text-sm font-semibold">
                    On this page
                </summary>
                <nav className="mt-3" aria-label="Table of contents">
                    {links}
                </nav>
            </details>
            <aside className="post-toc sticky top-20 hidden self-start lg:order-2 lg:block">
                <p className="mb-3 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                    On this page
                </p>
                <nav aria-label="Table of contents">{links}</nav>
            </aside>
        </>
    );
}

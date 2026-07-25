import { motion } from 'motion/react';
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
            { rootMargin: '-80px 0px -70% 0px' },
        );

        headings.forEach((heading) => observer.observe(heading));

        return () => observer.disconnect();
    }, [items]);

    if (items.length < 3) {
        return null;
    }

    /**
     * The active marker is a single element that slides between entries via
     * `layoutId`. The mobile and desktop lists are both rendered, so each gets
     * its own id — sharing one would make the two copies fight over it.
     */
    const links = (variant: 'mobile' | 'desktop') => (
        <ol className="grid gap-0.5 font-display text-sm [font-stretch:96%]">
            {items.map((item) => (
                <li key={item.id} className="relative">
                    {activeId === item.id && (
                        <motion.span
                            layoutId={`toc-marker-${variant}`}
                            aria-hidden
                            className={cn(
                                'absolute inset-y-0 w-0.5 bg-primary',
                                // Tracks the anchor's own left border, so the
                                // active bar and the hover bar sit on the same
                                // line at both heading levels. The h3 indent
                                // lives on the anchor rather than the <li>,
                                // since an absolute marker anchors to the li's
                                // padding box and would ignore it.
                                item.level === 3 ? 'left-3' : 'left-0',
                            )}
                            transition={{
                                type: 'spring',
                                stiffness: 420,
                                damping: 38,
                            }}
                        />
                    )}
                    <a
                        href={`#${item.id}`}
                        className={cn(
                            'block border-l-2 border-transparent py-1.5 pl-3.5 leading-6 text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground',
                            item.level === 3 && 'ml-3',
                            activeId === item.id &&
                                'font-medium text-foreground',
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
            <details className="post-toc group border-y border-border py-4 lg:hidden">
                <summary className="eyebrow cursor-pointer text-brand-accent marker:text-primary">
                    On this page
                </summary>
                <nav className="mt-4" aria-label="Table of contents">
                    {links('mobile')}
                </nav>
            </details>
            {/* A rule-topped label over a sticky rail — the shape tg-mari uses
                for its sidebar sections. `top-16` clears the sticky utility row
                plus a little air. */}
            <aside className="post-toc sticky top-16 hidden self-start lg:order-2 lg:block">
                <p className="rule-label mb-4">On this page</p>
                <nav aria-label="Table of contents">{links('desktop')}</nav>
            </aside>
        </>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import EmptyState from '@/components/public/empty-state';
import { RevealGroup, RevealItem } from '@/components/public/motion';
import PageHeader, { postCount } from '@/components/public/page-header';
import { cn } from '@/lib/utils';
import { show as categoryShow } from '@/routes/public/categories';
import { show as tagShow } from '@/routes/public/tags';
import type { PublicCategory, PublicTag } from '@/types';

type Props = {
    categories: PublicCategory[];
    tags: PublicTag[];
};

export default function Topics({ categories, tags }: Props) {
    return (
        <>
            <Head title="Topics" />
            <div className="mx-auto grid w-full max-w-6xl gap-14 px-4 py-12">
                <PageHeader
                    eyebrow="Explore"
                    title="Where we&rsquo;ve been"
                    description="Places we keep going back to, and the themes that run through the writing."
                />

                {categories.length > 0 ? (
                    <CategoryRows categories={categories} />
                ) : (
                    <EmptyState
                        title="No destinations yet"
                        description="Places show up here once the first write-ups go live."
                    />
                )}

                {tags.length > 0 && <TagSpecimen tags={tags} />}
            </div>
        </>
    );
}

/**
 * Categories as full-width rows rather than a three-column card grid.
 *
 * Categories are the only taxonomy with a description, and in a card that
 * description had to be clamped to a couple of lines beside a truncated name. A
 * row gives the name room to be set large, the description room to be read, and
 * puts the count where the eye can compare it down the column.
 */
function CategoryRows({ categories }: { categories: PublicCategory[] }) {
    return (
        <section aria-labelledby="categories-heading">
            <h2 id="categories-heading" className="section-head mb-8">
                Destinations
            </h2>

            <RevealGroup className="grid border-t border-border">
                {categories.map((category) => (
                    <RevealItem
                        key={category.slug}
                        className="group relative border-b border-border"
                    >
                        <Link
                            href={categoryShow(category.slug)}
                            className="grid items-baseline gap-x-8 gap-y-2 py-6 sm:grid-cols-[minmax(0,18rem)_1fr_auto]"
                            prefetch
                        >
                            <span className="display-md decoration-primary/50 underline-offset-[6px] group-hover:underline">
                                {category.name}
                            </span>
                            <span className="font-body leading-relaxed text-muted-foreground">
                                {category.description}
                            </span>
                            <span className="eyebrow flex items-center gap-3 text-muted-foreground sm:justify-self-end">
                                {postCount(category.posts_count)}
                                <ArrowRight
                                    className="size-3 transition-transform motion-safe:group-hover:translate-x-0.5"
                                    aria-hidden
                                />
                            </span>
                        </Link>
                    </RevealItem>
                ))}
            </RevealGroup>
        </section>
    );
}

/**
 * Three size steps, by share of the largest tag. Scaling type by frequency is a
 * tag cloud, which is a fair device here because the size is reporting something
 * real — how much has been written under each. Three steps rather than a
 * continuous scale keeps it a typographic set rather than a novelty.
 */
const TAG_STEPS = [
    { threshold: 0.66, className: 'text-2xl sm:text-3xl' },
    { threshold: 0.33, className: 'text-lg sm:text-xl' },
    { threshold: 0, className: 'text-sm sm:text-base' },
] as const;

function TagSpecimen({ tags }: { tags: PublicTag[] }) {
    const busiest = Math.max(...tags.map((tag) => tag.posts_count), 1);

    return (
        <section
            className="border-t border-border pt-10"
            aria-labelledby="tags-heading"
        >
            <h2 id="tags-heading" className="section-head mb-8">
                Themes
            </h2>

            <ul className="flex flex-wrap items-baseline gap-x-7 gap-y-4">
                {tags.map((tag) => (
                    <li key={tag.slug}>
                        <Link
                            href={tagShow(tag.slug)}
                            className={cn(
                                'inline-flex items-baseline gap-2 font-display text-muted-foreground [font-stretch:100%] decoration-primary/40 underline-offset-[6px] transition-colors hover:text-foreground hover:underline',
                                sizeFor(tag.posts_count, busiest),
                            )}
                        >
                            {tag.name}
                            <span className="meta-num text-[0.6875rem] text-brand-accent">
                                {tag.posts_count}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function sizeFor(count: number, busiest: number): string {
    const share = count / busiest;

    return (
        TAG_STEPS.find((step) => share > step.threshold) ?? TAG_STEPS.at(-1)!
    ).className;
}

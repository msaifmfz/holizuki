import type { ReactNode } from 'react';

import AuthorSocialLinks from '@/components/public/social-links';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { SocialLinks } from '@/types';

/**
 * The masthead for any page that is not the home page or an article.
 *
 * Replaces two things at once: the eyebrow-pill-plus-heading block that was
 * copy-pasted onto eight pages, and `ArchiveHeader`, which was the same block
 * again with an avatar bolted on for taxonomy pages. One component now covers
 * both, so the avatar variant cannot drift from the plain one.
 *
 * The rule sits *above* the eyebrow rather than below the heading — it reads as
 * the page starting rather than as the header being fenced off, and it lines up
 * with the rule-topped labels used in the article rail and the bands.
 */
export default function PageHeader({
    eyebrow,
    title,
    description,
    meta,
    avatarUrl,
    avatarName,
    socialLinks,
    className,
}: {
    eyebrow: string;
    title: string;
    description?: string | null;
    /** Small print under the title — counts, ranges, result totals. */
    meta?: ReactNode;
    avatarUrl?: string | null;
    /** Presence of a name is what switches the avatar on. */
    avatarName?: string;
    socialLinks?: SocialLinks | null;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <header
            className={cn('grid gap-5 border-t border-border pt-5', className)}
        >
            <p className="eyebrow text-brand-accent">{eyebrow}</p>

            <div className="flex flex-wrap items-center gap-5">
                {avatarName !== undefined && (
                    <Avatar className="size-16 rounded-sm border border-border">
                        <AvatarImage src={avatarUrl ?? undefined} alt="" />
                        <AvatarFallback className="rounded-sm bg-muted font-display text-lg text-muted-foreground">
                            {getInitials(avatarName)}
                        </AvatarFallback>
                    </Avatar>
                )}
                <h1 className="display-lg">{title}</h1>
            </div>

            {description && (
                <p className="max-w-2xl font-body text-lg leading-relaxed text-muted-foreground">
                    {description}
                </p>
            )}

            {(meta || socialLinks) && (
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
                    {meta && (
                        <div className="eyebrow text-muted-foreground">
                            {meta}
                        </div>
                    )}
                    <AuthorSocialLinks links={socialLinks} />
                </div>
            )}
        </header>
    );
}

/**
 * `12 posts` / `1 post`. Every listing page needs it and every one of them was
 * writing the ternary inline.
 */
export function postCount(total: number): string {
    return `${total} ${total === 1 ? 'post' : 'posts'}`;
}

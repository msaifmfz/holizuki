import { Link } from '@inertiajs/react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { show as authorShow } from '@/routes/public/authors';
import type { PublicAuthorRef } from '@/types';

/**
 * Who wrote it.
 *
 * Narrowed from the old version, which also rendered the date and the reading
 * time — that job moved to `Dateline`, which pairs them with the phase mark. The
 * split matters because listings mostly want the date without an avatar, and the
 * article wants both but stacked differently, and one component doing both ended
 * up with a `size` prop driving two unrelated things.
 */
export default function AuthorByline({
    author,
    size = 'sm',
    className,
}: {
    author: PublicAuthorRef | null;
    size?: 'sm' | 'lg';
    className?: string;
}) {
    const getInitials = useInitials();

    if (!author) {
        return null;
    }

    const large = size === 'lg';

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <Avatar
                className={cn(
                    'rounded-sm border border-border',
                    large ? 'size-10' : 'size-7',
                )}
            >
                <AvatarImage src={author.avatar_url ?? undefined} alt="" />
                <AvatarFallback className="rounded-sm bg-muted text-xs font-medium text-muted-foreground">
                    {getInitials(author.name)}
                </AvatarFallback>
            </Avatar>

            <div className="grid gap-0.5">
                <span className="eyebrow text-muted-foreground">Words by</span>
                {author.slug ? (
                    <Link
                        href={authorShow(author.slug)}
                        // z-10 escapes the full-card overlay link on cards.
                        className={cn(
                            'relative z-10 w-fit font-display font-medium decoration-primary/40 underline-offset-4 hover:underline',
                            large ? 'text-base' : 'text-sm',
                        )}
                    >
                        {author.name}
                    </Link>
                ) : (
                    <span
                        className={cn(
                            'font-display font-medium',
                            large ? 'text-base' : 'text-sm',
                        )}
                    >
                        {author.name}
                    </span>
                )}
            </div>
        </div>
    );
}

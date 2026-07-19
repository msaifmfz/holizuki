import { Link } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { formatDate } from '@/lib/post-editor';
import { cn } from '@/lib/utils';
import { show as authorShow } from '@/routes/public/authors';
import type { PublicAuthorRef } from '@/types';

type Props = {
    author: PublicAuthorRef | null;
    date: string | null;
    size?: 'sm' | 'md';
    className?: string;
    readingTime?: number;
};

export default function AuthorByline({
    author,
    date,
    size = 'sm',
    className,
    readingTime,
}: Props) {
    const getInitials = useInitials();

    return (
        <div
            className={cn(
                'flex items-center gap-2 text-muted-foreground',
                size === 'sm' ? 'text-xs' : 'text-sm',
                className,
            )}
        >
            {author && (
                <>
                    <Avatar className={size === 'sm' ? 'size-6' : 'size-8'}>
                        <AvatarImage
                            src={author.avatar_url ?? undefined}
                            alt=""
                        />
                        <AvatarFallback className="text-xs font-medium text-foreground">
                            {getInitials(author.name)}
                        </AvatarFallback>
                    </Avatar>
                    {author.slug ? (
                        <Link
                            href={authorShow(author.slug)}
                            className="relative z-10 font-medium text-foreground hover:underline"
                        >
                            {author.name}
                        </Link>
                    ) : (
                        <span className="font-medium text-foreground">
                            {author.name}
                        </span>
                    )}
                </>
            )}
            {author && date && <span aria-hidden>·</span>}
            {date && <time dateTime={date}>{formatDate(date, 'long')}</time>}
            {readingTime !== undefined && (
                <>
                    {(author || date) && <span aria-hidden>·</span>}
                    <span>{readingTime} min read</span>
                </>
            )}
        </div>
    );
}

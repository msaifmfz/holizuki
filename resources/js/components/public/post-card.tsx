import { Link } from '@inertiajs/react';
import AuthorByline from '@/components/public/author-byline';
import { show as categoryShow } from '@/routes/public/categories';
import { show as postShow } from '@/routes/public/posts';
import type { PublicPostCard } from '@/types';

export default function PostCard({ post }: { post: PublicPostCard }) {
    return (
        <article className="group relative flex flex-col gap-3">
            {post.featured_image_url ? (
                <div className="overflow-hidden rounded-xl border">
                    <img
                        src={post.featured_image_url}
                        alt={post.featured_image_alt ?? ''}
                        loading="lazy"
                        className="aspect-video w-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-[1.03]"
                    />
                </div>
            ) : (
                <div
                    aria-hidden
                    className="flex aspect-video w-full items-center justify-center rounded-xl border bg-muted"
                >
                    <span className="moon-dot opacity-40" />
                </div>
            )}

            {post.category && (
                <Link
                    href={categoryShow(post.category.slug)}
                    className="relative z-10 w-fit text-xs font-medium tracking-wide text-muted-foreground uppercase transition-colors hover:text-foreground"
                >
                    {post.category.name}
                </Link>
            )}

            <h3 className="font-display text-xl leading-snug font-semibold tracking-tight text-balance">
                <Link
                    href={postShow(post.slug)}
                    className="group-hover:underline group-hover:underline-offset-4 after:absolute after:inset-0"
                    prefetch
                >
                    {post.title}
                </Link>
            </h3>

            {post.excerpt && (
                <p className="line-clamp-2 text-sm leading-6 text-muted-foreground">
                    {post.excerpt}
                </p>
            )}

            <AuthorByline author={post.author} date={post.published_at} />
        </article>
    );
}

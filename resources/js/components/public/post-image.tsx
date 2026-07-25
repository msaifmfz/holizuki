import { useEffect, useRef, useState } from 'react';

import MoonPhase from '@/components/public/moon-phase';
import { cn } from '@/lib/utils';
import type { PublicPostCard } from '@/types';

/**
 * A post's cover, with the aspect ratio and the fallback in one place.
 *
 * Two things every call site needs and none should repeat:
 *
 * 1. An explicit `aspect-ratio`. The schema stores no width or height for a
 *    cover, so without one the whole page reflows when the image lands.
 * 2. A fallback that survives a *missing file*, not just a null path. Checking
 *    `featured_image_url` alone leaves the browser's broken-image icon and the
 *    alt text sitting in the layout if the file has gone from disk — which is
 *    exactly what a deleted upload looks like.
 *
 * The fallback is the post's own phase mark rather than a grey box: the disc is
 * already the site's date device, so an imageless card still says something
 * true about the post instead of apologising for a gap.
 */
export default function PostImage({
    post,
    ratio = 'aspect-[3/2]',
    phaseSize = 40,
    priority = false,
    className,
}: {
    post: PublicPostCard;
    /** Tailwind aspect class — cards run 3/2, the latest lead runs 4/3. */
    ratio?: string;
    phaseSize?: number;
    /** Set on the one image above the fold; everything else stays lazy. */
    priority?: boolean;
    className?: string;
}) {
    const imageRef = useRef<HTMLImageElement | null>(null);
    const [failed, setFailed] = useState(false);

    /*
     * `onError` alone is not enough. If the browser has the failure cached — a
     * second visit, or a client-side navigation back to a page it already
     * tried — the error event fires while the element is being inserted, before
     * React has attached the listener, and the handler never runs. A completed
     * image with zero intrinsic width is a failed one, so check for that on
     * mount too. The handler still covers the first, uncached failure, and lazy
     * images that only load once scrolled into view.
     */
    useEffect(() => {
        const node = imageRef.current;

        if (node?.complete && node.naturalWidth === 0) {
            setFailed(true);
        }
    }, [post.featured_image_url]);

    if (!post.featured_image_url || failed) {
        return (
            // Centring lives on an inner layer, not on this element. A caller
            // passing a `display` utility through `className` — `sm:block`, say,
            // to hide the thumbnail on small screens — would otherwise override
            // the flex here and strand the disc in the top-left corner.
            <div
                className={cn(
                    'relative border border-border bg-muted',
                    ratio,
                    className,
                )}
            >
                <span className="absolute inset-0 grid place-items-center">
                    <MoonPhase
                        date={post.published_at}
                        size={phaseSize}
                        className="text-muted-foreground/45"
                    />
                </span>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'overflow-hidden border border-border bg-muted',
                className,
            )}
        >
            <img
                ref={imageRef}
                src={post.featured_image_url}
                alt={post.featured_image_alt ?? ''}
                loading={priority ? 'eager' : 'lazy'}
                fetchPriority={priority ? 'high' : undefined}
                onError={() => setFailed(true)}
                className={cn(
                    'w-full object-cover transition-transform duration-700 motion-safe:group-hover:scale-[1.03]',
                    ratio,
                )}
            />
        </div>
    );
}

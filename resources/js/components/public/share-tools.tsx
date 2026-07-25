import { Check, Copy, Mail, Share2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { trackAnalyticsEvent } from '@/analytics/tracker';
import { Button } from '@/components/ui/button';

type Props = {
    title: string;
    methods: string[];
    postId: number;
};

const labels: Record<string, string> = {
    native: 'Share',
    copy: 'Copy link',
    email: 'Email',
    x: 'X',
    linkedin: 'LinkedIn',
    facebook: 'Facebook',
    reddit: 'Reddit',
    bluesky: 'Bluesky',
    whatsapp: 'WhatsApp',
};

export default function ShareTools({ title, methods, postId }: Props) {
    const [completedMethod, setCompletedMethod] = useState<string | null>(null);
    const completedResetTimeout = useRef<number | null>(null);

    useEffect(
        () => () => {
            if (completedResetTimeout.current !== null) {
                window.clearTimeout(completedResetTimeout.current);
            }
        },
        [],
    );

    async function share(method: string) {
        const url = `${window.location.origin}${window.location.pathname}`;

        try {
            if (method === 'native' && navigator.share) {
                await navigator.share({ title, url });
            } else if (method === 'copy') {
                await navigator.clipboard.writeText(url);
            } else {
                const destination = shareDestination(method, title, url);

                if (!destination) {
                    return;
                }

                if (method === 'email') {
                    window.location.assign(destination);
                } else if (
                    window.open(
                        destination,
                        '_blank',
                        'noopener,noreferrer',
                    ) === null
                ) {
                    return;
                }
            }
        } catch {
            return;
        }

        trackAnalyticsEvent('share', {
            method,
            content_type: 'article',
            item_id: `post:${postId}`,
            content_key: `post:${postId}`,
        });
        setCompletedMethod(method);

        if (completedResetTimeout.current !== null) {
            window.clearTimeout(completedResetTimeout.current);
        }

        completedResetTimeout.current = window.setTimeout(
            () => setCompletedMethod(null),
            2000,
        );
    }

    return (
        <aside className="post-share mt-10 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-border pt-6">
            <span className="eyebrow mr-1 text-brand-accent">Pass it on</span>
            {methods.map((method) => {
                if (
                    method === 'native' &&
                    (typeof navigator === 'undefined' || !navigator.share)
                ) {
                    return null;
                }

                const Icon =
                    completedMethod === method
                        ? Check
                        : method === 'copy'
                          ? Copy
                          : method === 'email'
                            ? Mail
                            : Share2;

                return (
                    <Button
                        key={method}
                        type="button"
                        variant="outline"
                        size="sm"
                        // Borderless: the share list can run to nine entries
                        // depending on config, and nine bordered boxes outweighed
                        // the article they sit under.
                        className="eyebrow h-auto rounded-none border-0 bg-transparent px-0 text-muted-foreground underline-offset-4 shadow-none hover:bg-transparent hover:text-primary hover:underline"
                        onClick={() => void share(method)}
                    >
                        <Icon className="size-3.5" aria-hidden />
                        {labels[method] ?? method}
                    </Button>
                );
            })}
        </aside>
    );
}

function shareDestination(method: string, title: string, url: string) {
    const encodedUrl = encodeURIComponent(url);
    const encodedTitle = encodeURIComponent(title);

    switch (method) {
        case 'email':
            return `mailto:?subject=${encodedTitle}&body=${encodedUrl}`;
        case 'x':
            return `https://x.com/intent/post?text=${encodedTitle}&url=${encodedUrl}`;
        case 'linkedin':
            return `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`;
        case 'facebook':
            return `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
        case 'reddit':
            return `https://www.reddit.com/submit?url=${encodedUrl}&title=${encodedTitle}`;
        case 'bluesky':
            return `https://bsky.app/intent/compose?text=${encodedTitle}%20${encodedUrl}`;
        case 'whatsapp':
            return `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`;
        default:
            return null;
    }
}

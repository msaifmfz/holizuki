import { Github, Globe, Linkedin, Twitter } from 'lucide-react';

import { cn } from '@/lib/utils';
import type { SocialLinks } from '@/types';

/**
 * An author's outbound links.
 *
 * This list previously existed verbatim in two places — the article's author
 * card and the taxonomy page header — which meant adding a platform meant
 * remembering both. The four keys here are the whole of the `SocialLinks` type;
 * anything else an author fills in is not stored.
 */
const PLATFORMS: ReadonlyArray<{
    key: keyof SocialLinks;
    label: string;
    icon: typeof Globe;
}> = [
    { key: 'website', label: 'Website', icon: Globe },
    { key: 'x', label: 'X (Twitter)', icon: Twitter },
    { key: 'github', label: 'GitHub', icon: Github },
    { key: 'linkedin', label: 'LinkedIn', icon: Linkedin },
];

export default function AuthorSocialLinks({
    links,
    className,
}: {
    links: SocialLinks | null | undefined;
    className?: string;
}) {
    if (!links) {
        return null;
    }

    const present = PLATFORMS.filter(({ key }) => links[key]);

    if (present.length === 0) {
        return null;
    }

    return (
        // The class name is load-bearing: print CSS hides it by name.
        <ul
            className={cn(
                'author-social-links flex items-center gap-1',
                className,
            )}
        >
            {present.map(({ key, label, icon: Icon }) => (
                <li key={key}>
                    <a
                        href={links[key]}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={label}
                        className="inline-flex border border-transparent p-2 text-muted-foreground transition-colors hover:border-border hover:text-primary"
                    >
                        <Icon className="size-4" />
                    </a>
                </li>
            ))}
        </ul>
    );
}

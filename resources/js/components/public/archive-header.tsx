import { Github, Globe, Linkedin, Twitter } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { SocialLinks } from '@/types';

const socialIcons: Array<{
    key: keyof SocialLinks;
    label: string;
    icon: typeof Globe;
}> = [
    { key: 'website', label: 'Website', icon: Globe },
    { key: 'x', label: 'X (Twitter)', icon: Twitter },
    { key: 'github', label: 'GitHub', icon: Github },
    { key: 'linkedin', label: 'LinkedIn', icon: Linkedin },
];

type Props = {
    kicker: string;
    title: string;
    description?: string | null;
    postsCount: number;
    avatarUrl?: string | null;
    avatarName?: string;
    socialLinks?: SocialLinks | null;
};

export default function ArchiveHeader({
    kicker,
    title,
    description,
    postsCount,
    avatarUrl,
    avatarName,
    socialLinks,
}: Props) {
    const getInitials = useInitials();

    return (
        <header className="grid gap-4 border-b pb-10">
            <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                <span className="moon-dot" aria-hidden />
                {kicker}
            </p>
            <div className="flex flex-wrap items-center gap-5">
                {avatarName !== undefined && (
                    <Avatar className="size-16 border">
                        <AvatarImage src={avatarUrl ?? undefined} alt="" />
                        <AvatarFallback className="text-lg">
                            {getInitials(avatarName)}
                        </AvatarFallback>
                    </Avatar>
                )}
                <h1 className="font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                    {title}
                </h1>
            </div>
            {description && (
                <p className="max-w-2xl text-lg leading-8 text-muted-foreground">
                    {description}
                </p>
            )}
            <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                <span>
                    {postsCount} {postsCount === 1 ? 'post' : 'posts'}
                </span>
                {socialLinks && (
                    <span className="flex items-center gap-1">
                        {socialIcons.map(
                            ({ key, label, icon: Icon }) =>
                                socialLinks[key] && (
                                    <a
                                        key={key}
                                        href={socialLinks[key]}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={label}
                                        className="rounded-md p-1.5 transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        <Icon className="size-4" />
                                    </a>
                                ),
                        )}
                    </span>
                )}
            </div>
        </header>
    );
}

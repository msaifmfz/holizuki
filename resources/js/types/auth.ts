export type SocialLinks = {
    website?: string;
    x?: string;
    github?: string;
    linkedin?: string;
};

export type User = {
    id: number;
    name: string;
    email: string;
    role: 'administrator' | 'reader';
    avatar?: string;
    author_slug: string | null;
    avatar_url: string | null;
    bio: string | null;
    social_links: SocialLinks | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

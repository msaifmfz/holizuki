import type { SocialLinks } from './auth';
import type { RichTextDocument } from './post';

type PublicTaxonomyRef = {
    name: string;
    slug: string;
};

export type PublicAuthorRef = {
    name: string;
    slug: string | null;
    avatar_url: string | null;
};

type PublicAuthorProfile = PublicAuthorRef & {
    bio: string | null;
    social_links: SocialLinks | null;
};

export type PublicPostCard = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    featured_image_url: string | null;
    featured_image_alt: string | null;
    featured_image_caption: string | null;
    reading_time_minutes: number;
    published_at: string | null;
    category: PublicTaxonomyRef | null;
    author: PublicAuthorRef | null;
};

export type PublicPostDetail = Omit<PublicPostCard, 'author'> & {
    word_count: number;
    word_count_band:
        'under_500' | '500_999' | '1000_1499' | '1500_2499' | '2500_plus';
    body: RichTextDocument | null;
    updated_at: string | null;
    seo_title: string | null;
    tags: PublicTaxonomyRef[];
    author: PublicAuthorProfile | null;
};

export type TableOfContentsItem = {
    id: string;
    title: string;
    level: 2 | 3;
};

type PublicArchiveMonth = {
    month: number;
    label: string;
    posts_count: number;
};

export type PublicArchiveYear = {
    year: number;
    months: PublicArchiveMonth[];
    posts_count: number;
};

export type PublicArchivePeriod = {
    year: number | null;
    month: number | null;
    label: string;
};

export type PublicCategory = {
    name: string;
    slug: string;
    description: string | null;
    posts_count: number;
};

export type PublicTag = {
    name: string;
    slug: string;
    posts_count: number;
};

export type PublicAuthor = PublicAuthorProfile & {
    posts_count: number;
};

export type FooterCategory = {
    name: string;
    slug: string;
};

export type CategoryItem = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    posts_count: number;
};

export type TagItem = {
    id: number;
    name: string;
    slug: string;
    posts_count: number;
};

import type { RichTextDocument, TableOfContentsItem } from '@/types';

type RichTextNode = RichTextDocument;

const safeProtocols = new Set(['http:', 'https:', 'mailto:']);

export function getRichTextContent(node: RichTextNode | null): string {
    if (!node) {
        return '';
    }

    const ownText = typeof node.text === 'string' ? node.text : '';
    const childText = (node.content ?? [])
        .map((child) => getRichTextContent(child))
        .join('');

    return ownText + childText;
}

export function slugifyHeading(value: string): string {
    return (
        value
            .toLocaleLowerCase()
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '') || 'section'
    );
}

export function buildTableOfContents(
    document: RichTextDocument | null,
): TableOfContentsItem[] {
    const usedIds = new Map<string, number>();
    const items: TableOfContentsItem[] = [];

    const visit = (node: RichTextNode): void => {
        if (node.type === 'heading') {
            const level = Number(node.attrs?.level);

            if (level === 2 || level === 3) {
                const title = getRichTextContent(node).trim();

                if (title !== '') {
                    const suppliedId = safeHeadingId(node.attrs?.id);
                    const baseId = suppliedId ?? slugifyHeading(title);
                    const occurrence = (usedIds.get(baseId) ?? 0) + 1;
                    usedIds.set(baseId, occurrence);
                    items.push({
                        id:
                            occurrence === 1
                                ? baseId
                                : `${baseId}-${occurrence}`,
                        title,
                        level,
                    });
                }
            }
        }

        node.content?.forEach(visit);
    };

    if (document) {
        visit(document);
    }

    return items;
}

export function normalizeTableOfContents(
    document: RichTextDocument | null,
    supplied: TableOfContentsItem[] | undefined,
): TableOfContentsItem[] {
    if (!supplied || supplied.length === 0) {
        return buildTableOfContents(document);
    }

    return supplied
        .filter(
            (item) =>
                (item.level === 2 || item.level === 3) &&
                safeHeadingId(item.id) !== null &&
                item.title.trim() !== '',
        )
        .map((item) => ({ ...item, title: item.title.trim() }));
}

export function safeLinkUrl(value: unknown): string | null {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const url = value.trim();

    if (url.startsWith('/') || url.startsWith('#')) {
        return url;
    }

    try {
        const parsed = new URL(url);

        return safeProtocols.has(parsed.protocol) ? url : null;
    } catch {
        return null;
    }
}

export function safeImageUrl(value: unknown): string | null {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const url = value.trim();

    if (url.startsWith('/')) {
        return url;
    }

    try {
        const parsed = new URL(url);

        return ['http:', 'https:'].includes(parsed.protocol) ? url : null;
    } catch {
        return null;
    }
}

export function safeHeadingId(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const id = value.trim();

    return id !== '' && /^[a-zA-Z0-9][a-zA-Z0-9_:.-]*$/.test(id) ? id : null;
}

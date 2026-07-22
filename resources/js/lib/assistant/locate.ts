import type { Node as ProseMirrorNode } from '@tiptap/pm/model';

/**
 * Reduce a markdown block to a plain-text needle that can be found inside the
 * rendered editor. Change payloads carry markdown (`## Heading`, `**bold**`,
 * `![alt](media:1)`), but the editor holds rich text — so strip the syntax
 * and keep the words a reader would actually see.
 */
export function plainTextNeedle(markdown: string): string {
    const firstBlock = markdown
        .split(/\n{2,}/)
        .find((block) => block.trim() !== '');

    if (!firstBlock) {
        return '';
    }

    return firstBlock
        .replace(/^!\[[^\]]*\]\([^)]*\)\s*$/gm, '') // image lines have no visible text
        .replace(/`{1,3}([^`]*)`{1,3}/g, '$1') // inline / fenced code
        .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1') // links keep their text
        .replace(/<\/?u>/g, '') // underline tags
        .replace(/[*~]{1,3}/g, '') // bold / italic / strike markers
        .replace(/^\s{0,3}#{1,6}\s+/gm, '') // heading markers
        .replace(/^\s{0,3}>\s?/gm, '') // blockquote markers
        .replace(/^\s{0,3}(?:[-*+]|\d{1,9}[.)])\s+/gm, '') // list markers
        .replace(/\\(.)/g, '$1') // unescape
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Locate a plain-text needle inside a ProseMirror document, returning the
 * document positions of the match, or null. Matching is whitespace-tolerant:
 * runs of whitespace in the document collapse to single spaces so a needle
 * derived from markdown still lines up with rendered text.
 */
export function findTextRange(
    doc: ProseMirrorNode,
    needle: string,
): { from: number; to: number } | null {
    const trimmed = needle.trim();

    if (trimmed === '') {
        return null;
    }

    // Build a haystack of the document's visible text with a map back to
    // document positions, collapsing whitespace as we go.
    let haystack = '';
    const positions: number[] = [];
    let lastWasSpace = false;

    doc.descendants((node, pos) => {
        if (!node.isText || typeof node.text !== 'string') {
            if (node.isBlock && haystack !== '' && !lastWasSpace) {
                haystack += ' ';
                positions.push(pos);
                lastWasSpace = true;
            }

            return true;
        }

        for (let index = 0; index < node.text.length; index++) {
            const char = node.text[index];

            if (/\s/.test(char)) {
                if (lastWasSpace) {
                    continue;
                }

                haystack += ' ';
                lastWasSpace = true;
            } else {
                haystack += char;
                lastWasSpace = false;
            }

            positions.push(pos + index);
        }

        return true;
    });

    const collapsedNeedle = trimmed.replace(/\s+/g, ' ');
    const rangeAt = (start: number, length: number) => ({
        from: positions[start],
        to: positions[start + length - 1] + 1,
    });

    const startIndex = haystack.indexOf(collapsedNeedle);

    if (startIndex !== -1) {
        return rangeAt(startIndex, collapsedNeedle.length);
    }

    // The block drifted since the change was proposed. Fall back to the
    // longest leading word-prefix that still appears verbatim, so the author
    // is scrolled to the right neighbourhood rather than nowhere.
    const words = collapsedNeedle.split(' ');

    for (let count = words.length - 1; count >= 1; count--) {
        const prefix = words.slice(0, count).join(' ');

        if (prefix.length < 8) {
            return null;
        }

        const prefixIndex = haystack.indexOf(prefix);

        if (prefixIndex !== -1) {
            return rangeAt(prefixIndex, prefix.length);
        }
    }

    return null;
}

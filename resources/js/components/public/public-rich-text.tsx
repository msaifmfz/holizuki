import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import diff from 'highlight.js/lib/languages/diff';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import markdown from 'highlight.js/lib/languages/markdown';
import php from 'highlight.js/lib/languages/php';
import plaintext from 'highlight.js/lib/languages/plaintext';
import python from 'highlight.js/lib/languages/python';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import { createLowlight } from 'lowlight';
import { Check, Copy, Expand } from 'lucide-react';
import { Fragment, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    getRichTextContent,
    normalizeTableOfContents,
    safeHeadingId,
    safeImageUrl,
    safeLinkUrl,
} from '@/lib/public-rich-text';
import { cn } from '@/lib/utils';
import type { RichTextDocument, TableOfContentsItem } from '@/types';

const lowlight = createLowlight();

lowlight.register({
    bash,
    css,
    diff,
    html: xml,
    javascript,
    json,
    markdown,
    php,
    plaintext,
    python,
    sql,
    typescript,
    xml,
});

const languageAliases: Record<string, string> = {
    js: 'javascript',
    jsx: 'javascript',
    md: 'markdown',
    py: 'python',
    shell: 'bash',
    sh: 'bash',
    text: 'plaintext',
    ts: 'typescript',
    tsx: 'typescript',
};

type Props = {
    value: RichTextDocument | null;
    tableOfContents?: TableOfContentsItem[];
    className?: string;
};

type HighlightNode = {
    type: 'element' | 'text';
    tagName?: string;
    properties?: { className?: string[] | string };
    value?: string;
    children?: HighlightNode[];
};

export default function PublicRichText({
    value,
    tableOfContents,
    className,
}: Props) {
    const headings = normalizeTableOfContents(value, tableOfContents);
    let headingIndex = 0;

    const renderNode = (node: RichTextDocument, key: string): ReactNode => {
        const children = (node.content ?? []).map((child, index) =>
            renderNode(child, `${key}-${index}`),
        );

        switch (node.type) {
            case 'doc':
                return <Fragment key={key}>{children}</Fragment>;
            case 'text':
                return applyMarks(node.text ?? '', node.marks, key);
            case 'paragraph':
                return <p key={key}>{children}</p>;
            case 'heading': {
                const level = Number(node.attrs?.level);
                const fallbackId = safeHeadingId(node.attrs?.id);
                const hasTitle = getRichTextContent(node).trim() !== '';
                const tocEntry = hasTitle ? headings[headingIndex] : undefined;
                const id = tocEntry?.id ?? fallbackId ?? undefined;

                if (level === 2) {
                    headingIndex += hasTitle ? 1 : 0;

                    return (
                        <h2 key={key} id={id} tabIndex={-1}>
                            {children}
                        </h2>
                    );
                }

                if (level === 3) {
                    headingIndex += hasTitle ? 1 : 0;

                    return (
                        <h3 key={key} id={id} tabIndex={-1}>
                            {children}
                        </h3>
                    );
                }

                return <p key={key}>{children}</p>;
            }
            case 'bulletList':
                return <ul key={key}>{children}</ul>;
            case 'orderedList':
                return (
                    <ol
                        key={key}
                        start={safePositiveInteger(node.attrs?.start)}
                    >
                        {children}
                    </ol>
                );
            case 'listItem':
                return <li key={key}>{children}</li>;
            case 'blockquote':
                return <blockquote key={key}>{children}</blockquote>;
            case 'hardBreak':
                return <br key={key} />;
            case 'horizontalRule':
                return <hr key={key} />;
            case 'codeBlock':
                return (
                    <CodeBlock
                        key={key}
                        code={getRichTextContent(node)}
                        language={node.attrs?.language}
                    />
                );
            case 'image':
                return safePositiveInteger(node.attrs?.mediaId) ? (
                    <ExpandableImage key={key} attrs={node.attrs} />
                ) : null;
            default:
                return <Fragment key={key}>{children}</Fragment>;
        }
    };

    return (
        <div className={cn('public-rich-text', className)}>
            {value ? renderNode(value, 'root') : null}
        </div>
    );
}

export function CodeBlock({
    code,
    language,
}: {
    code: string;
    language: unknown;
}) {
    const [copied, setCopied] = useState(false);
    const resetTimer = useRef<number | null>(null);
    const normalizedLanguage = normalizeLanguage(language);
    const highlighted = highlightCode(code, normalizedLanguage);

    useEffect(
        () => () => {
            if (resetTimer.current !== null) {
                window.clearTimeout(resetTimer.current);
            }
        },
        [],
    );

    const copyCode = async () => {
        if (!navigator.clipboard) {
            return;
        }

        await navigator.clipboard.writeText(code);
        setCopied(true);

        if (resetTimer.current !== null) {
            window.clearTimeout(resetTimer.current);
        }

        resetTimer.current = window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <figure className="code-block">
            <figcaption className="code-block-toolbar">
                <span>{languageLabel(normalizedLanguage)}</span>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="h-7 px-2 text-xs text-neutral-300 hover:bg-white/10 hover:text-white"
                    onClick={copyCode}
                    aria-label={copied ? 'Code copied' : 'Copy code'}
                >
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </figcaption>
            <pre>
                <code className={`hljs language-${normalizedLanguage}`}>
                    {highlighted}
                </code>
            </pre>
        </figure>
    );
}

export function ExpandableImage({
    attrs,
}: {
    attrs: RichTextDocument['attrs'];
}) {
    const src = safeImageUrl(attrs?.src);

    if (!src) {
        return null;
    }

    const alt = typeof attrs?.alt === 'string' ? attrs.alt : '';
    const caption =
        typeof attrs?.caption === 'string' && attrs.caption.trim() !== ''
            ? attrs.caption.trim()
            : null;
    const width = safePositiveInteger(attrs?.width);
    const height = safePositiveInteger(attrs?.height);

    return (
        <Dialog>
            <figure className="article-image">
                <DialogTrigger asChild>
                    <button
                        type="button"
                        className="group relative block w-full cursor-zoom-in overflow-hidden rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        aria-label={
                            alt === '' ? 'Expand image' : `Expand image: ${alt}`
                        }
                    >
                        <img
                            src={src}
                            alt={alt}
                            width={width}
                            height={height}
                            loading="lazy"
                            className="h-auto w-full"
                        />
                        <span className="absolute right-3 bottom-3 rounded-full bg-black/65 p-2 text-white opacity-0 shadow-sm transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                            <Expand className="size-4" aria-hidden />
                        </span>
                    </button>
                </DialogTrigger>
                {caption && <figcaption>{caption}</figcaption>}
            </figure>
            <DialogContent className="image-lightbox max-h-[calc(100vh-2rem)] max-w-[calc(100vw-2rem)] overflow-auto border-0 bg-transparent p-0 shadow-none sm:max-w-[calc(100vw-2rem)]">
                <DialogHeader className="sr-only">
                    <DialogTitle>Expanded article image</DialogTitle>
                    <DialogDescription>
                        {caption ?? alt ?? 'Article image'}
                    </DialogDescription>
                </DialogHeader>
                <figure className="grid max-h-[calc(100vh-2rem)] place-items-center gap-3">
                    <img
                        src={src}
                        alt={alt}
                        width={width}
                        height={height}
                        className="max-h-[calc(100vh-5rem)] max-w-full rounded-lg object-contain"
                    />
                    {caption && (
                        <figcaption className="rounded-md bg-black/70 px-3 py-2 text-center text-sm text-white">
                            {caption}
                        </figcaption>
                    )}
                </figure>
            </DialogContent>
        </Dialog>
    );
}

function applyMarks(
    text: string,
    marks: RichTextDocument['marks'],
    key: string,
): ReactNode {
    return (marks ?? []).reduce<ReactNode>((content, mark, index) => {
        const markKey = `${key}-mark-${index}`;

        switch (mark.type) {
            case 'bold':
                return <strong key={markKey}>{content}</strong>;
            case 'italic':
                return <em key={markKey}>{content}</em>;
            case 'strike':
                return <s key={markKey}>{content}</s>;
            case 'underline':
                return <u key={markKey}>{content}</u>;
            case 'code':
                return <code key={markKey}>{content}</code>;
            case 'link': {
                const href = safeLinkUrl(mark.attrs?.href);

                return href ? (
                    <a
                        key={markKey}
                        href={href}
                        rel={
                            href.startsWith('http')
                                ? 'noopener noreferrer'
                                : undefined
                        }
                    >
                        {content}
                    </a>
                ) : (
                    content
                );
            }
            default:
                return content;
        }
    }, text);
}

function normalizeLanguage(value: unknown): string {
    if (typeof value !== 'string') {
        return 'plaintext';
    }

    const normalized = value.toLocaleLowerCase().trim();
    const language = languageAliases[normalized] ?? normalized;

    return lowlight.registered(language) ? language : 'plaintext';
}

function languageLabel(language: string): string {
    const labels: Record<string, string> = {
        bash: 'Bash',
        css: 'CSS',
        diff: 'Diff',
        html: 'HTML',
        javascript: 'JavaScript',
        json: 'JSON',
        markdown: 'Markdown',
        php: 'PHP',
        plaintext: 'Plain text',
        python: 'Python',
        sql: 'SQL',
        typescript: 'TypeScript',
        xml: 'XML',
    };

    return labels[language] ?? language;
}

function highlightCode(code: string, language: string): ReactNode[] {
    try {
        const tree = lowlight.highlight(language, code);

        return tree.children.map((child, index) =>
            renderHighlightNode(child as HighlightNode, `highlight-${index}`),
        );
    } catch {
        return [code];
    }
}

function renderHighlightNode(node: HighlightNode, key: string): ReactNode {
    if (node.type === 'text') {
        return node.value ?? '';
    }

    const className = node.properties?.className;

    return (
        <span
            key={key}
            className={
                Array.isArray(className) ? className.join(' ') : className
            }
        >
            {node.children?.map((child, index) =>
                renderHighlightNode(child, `${key}-${index}`),
            )}
        </span>
    );
}

function safePositiveInteger(value: unknown): number | undefined {
    const number = Number(value);

    return Number.isInteger(number) && number > 0 ? number : undefined;
}

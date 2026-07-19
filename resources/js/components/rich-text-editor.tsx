import { useHttp } from '@inertiajs/react';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import Image from '@tiptap/extension-image';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
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
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    Strikethrough,
    Underline,
    Undo2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { resizeInlineImage } from '@/lib/crop-image';
import { errorText } from '@/lib/post-editor';
import { cn } from '@/lib/utils';
import { store as storeInlineImage } from '@/routes/posts/inline-images';
import type { RichTextDocument } from '@/types';

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

const codeLanguages = [
    ['plaintext', 'Plain text'],
    ['bash', 'Bash'],
    ['css', 'CSS'],
    ['diff', 'Diff'],
    ['html', 'HTML'],
    ['javascript', 'JavaScript'],
    ['json', 'JSON'],
    ['markdown', 'Markdown'],
    ['php', 'PHP'],
    ['python', 'Python'],
    ['sql', 'SQL'],
    ['typescript', 'TypeScript'],
    ['xml', 'XML'],
] as const;

const PostImage = Image.extend({
    addAttributes() {
        return {
            src: { default: null },
            alt: { default: null },
            mediaId: { default: null },
            caption: { default: null },
            width: { default: null },
            height: { default: null },
        };
    },
});

type Props = {
    value: RichTextDocument | null;
    onChange?: (value: RichTextDocument) => void;
    readOnly?: boolean;
    className?: string;
    postId?: number;
};

type InlineImageResponse = {
    id: number;
    url: string;
    width: number;
    height: number;
};

export default function RichTextEditor({
    value,
    onChange,
    readOnly = false,
    className,
    postId,
}: Props) {
    const [imageDialogOpen, setImageDialogOpen] = useState(false);
    const [imageFile, setImageFile] = useState<File | null>(null);
    const [imagePreviewUrl, setImagePreviewUrl] = useState<string | null>(null);
    const [imageAlt, setImageAlt] = useState('');
    const [imageCaption, setImageCaption] = useState('');
    const [imageError, setImageError] = useState<string | null>(null);
    const inlineImage = useHttp<
        { image: File | null; alt_text: string; caption: string },
        InlineImageResponse
    >({ image: null, alt_text: '', caption: '' });
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                codeBlock: false,
                link: {
                    openOnClick: readOnly,
                    defaultProtocol: 'https',
                    HTMLAttributes: {
                        rel: 'noopener noreferrer',
                        target: null,
                    },
                },
            }),
            CodeBlockLowlight.configure({
                lowlight,
                defaultLanguage: 'plaintext',
            }),
            PostImage.configure({
                allowBase64: false,
                inline: false,
            }),
        ],
        content: value ?? { type: 'doc', content: [{ type: 'paragraph' }] },
        editable: !readOnly,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                'aria-label': readOnly ? 'Article body' : 'Post body',
            },
        },
        onUpdate: ({ editor: currentEditor }) =>
            onChange?.(currentEditor.getJSON()),
    });

    useEffect(() => {
        if (
            editor &&
            value &&
            JSON.stringify(editor.getJSON()) !== JSON.stringify(value)
        ) {
            editor.commands.setContent(value, { emitUpdate: false });
        }
    }, [editor, value]);

    useEffect(
        () => () => {
            if (imagePreviewUrl) {
                URL.revokeObjectURL(imagePreviewUrl);
            }
        },
        [imagePreviewUrl],
    );

    if (!editor) {
        return <div className="h-72 animate-pulse rounded-md bg-muted" />;
    }

    const setLink = () => {
        const previousUrl = editor.getAttributes('link').href as
            string | undefined;
        const url = window.prompt('Link URL', previousUrl ?? 'https://');

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    };

    const chooseInlineImage = (selected: File | undefined) => {
        if (!selected) {
            return;
        }

        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(
                selected.type,
            ) ||
            selected.size > 5 * 1024 * 1024
        ) {
            setImageError(
                'Choose a JPG, PNG, or WebP image no larger than 5 MB.',
            );

            return;
        }

        if (imagePreviewUrl) {
            URL.revokeObjectURL(imagePreviewUrl);
        }

        setImageFile(selected);
        setImagePreviewUrl(URL.createObjectURL(selected));
        setImageError(null);
    };

    const closeImageDialog = () => {
        setImageDialogOpen(false);
        setImageFile(null);
        setImagePreviewUrl(null);
        setImageAlt('');
        setImageCaption('');
        setImageError(null);
    };

    const uploadInlineImage = async () => {
        if (!postId || !imageFile || imageAlt.trim() === '') {
            setImageError('Choose an image and provide alternative text.');

            return;
        }

        try {
            const image = await resizeInlineImage(imageFile);
            inlineImage.transform(() => ({
                image,
                alt_text: imageAlt.trim(),
                caption: imageCaption.trim(),
            }));
            const response = await inlineImage.post(
                storeInlineImage.url(postId),
            );
            editor
                .chain()
                .focus()
                .insertContent({
                    type: 'image',
                    attrs: {
                        mediaId: response.id,
                        src: response.url,
                        alt: imageAlt.trim(),
                        caption: imageCaption.trim() || null,
                        width: response.width,
                        height: response.height,
                    },
                })
                .run();
            closeImageDialog();
        } catch (error) {
            setImageError(
                error instanceof Error
                    ? error.message
                    : 'The image could not be uploaded.',
            );
        }
    };

    return (
        <div
            className={cn(
                'overflow-hidden rounded-md border bg-background',
                className,
            )}
        >
            {!readOnly && (
                <div
                    className="flex flex-wrap gap-1 border-b bg-muted/40 p-2"
                    role="toolbar"
                    aria-label="Text formatting"
                >
                    <ToolbarButton
                        label="Heading 2"
                        active={editor.isActive('heading', { level: 2 })}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 2 })
                                .run()
                        }
                    >
                        <Heading2 />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Heading 3"
                        active={editor.isActive('heading', { level: 3 })}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 3 })
                                .run()
                        }
                    >
                        <Heading3 />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Bold"
                        active={editor.isActive('bold')}
                        onClick={() =>
                            editor.chain().focus().toggleBold().run()
                        }
                    >
                        <Bold />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Italic"
                        active={editor.isActive('italic')}
                        onClick={() =>
                            editor.chain().focus().toggleItalic().run()
                        }
                    >
                        <Italic />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Underline"
                        active={editor.isActive('underline')}
                        onClick={() =>
                            editor.chain().focus().toggleUnderline().run()
                        }
                    >
                        <Underline />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Strike"
                        active={editor.isActive('strike')}
                        onClick={() =>
                            editor.chain().focus().toggleStrike().run()
                        }
                    >
                        <Strikethrough />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Bullet list"
                        active={editor.isActive('bulletList')}
                        onClick={() =>
                            editor.chain().focus().toggleBulletList().run()
                        }
                    >
                        <List />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Numbered list"
                        active={editor.isActive('orderedList')}
                        onClick={() =>
                            editor.chain().focus().toggleOrderedList().run()
                        }
                    >
                        <ListOrdered />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Quote"
                        active={editor.isActive('blockquote')}
                        onClick={() =>
                            editor.chain().focus().toggleBlockquote().run()
                        }
                    >
                        <Quote />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Code block"
                        active={editor.isActive('codeBlock')}
                        onClick={() => {
                            if (editor.isActive('codeBlock')) {
                                editor.chain().focus().toggleCodeBlock().run();

                                return;
                            }

                            editor
                                .chain()
                                .focus()
                                .setCodeBlock({ language: 'plaintext' })
                                .run();
                        }}
                    >
                        <Code />
                    </ToolbarButton>
                    {editor.isActive('codeBlock') && (
                        <Select
                            value={
                                (editor.getAttributes('codeBlock').language as
                                    string | undefined) ?? 'plaintext'
                            }
                            onValueChange={(language) =>
                                editor
                                    .chain()
                                    .focus()
                                    .updateAttributes('codeBlock', { language })
                                    .run()
                            }
                        >
                            <SelectTrigger
                                className="h-9 w-36"
                                aria-label="Code language"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {codeLanguages.map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                    {postId !== undefined && (
                        <ToolbarButton
                            label="Insert image"
                            onClick={() => setImageDialogOpen(true)}
                        >
                            <ImagePlus />
                        </ToolbarButton>
                    )}
                    <ToolbarButton
                        label="Link"
                        active={editor.isActive('link')}
                        onClick={setLink}
                    >
                        <LinkIcon />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Horizontal rule"
                        onClick={() =>
                            editor.chain().focus().setHorizontalRule().run()
                        }
                    >
                        <Minus />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Undo"
                        disabled={!editor.can().undo()}
                        onClick={() => editor.chain().focus().undo().run()}
                    >
                        <Undo2 />
                    </ToolbarButton>
                    <ToolbarButton
                        label="Redo"
                        disabled={!editor.can().redo()}
                        onClick={() => editor.chain().focus().redo().run()}
                    >
                        <Redo2 />
                    </ToolbarButton>
                </div>
            )}
            <EditorContent
                editor={editor}
                className={cn(
                    'rich-text-content',
                    readOnly ? 'border-0' : 'min-h-72',
                )}
            />
            <Dialog
                open={imageDialogOpen}
                onOpenChange={(open) => {
                    if (open) {
                        setImageDialogOpen(true);
                    } else {
                        closeImageDialog();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Insert an image</DialogTitle>
                        <DialogDescription>
                            JPG, PNG, or WebP up to 5 MB. Images are optimized
                            to WebP before upload.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="inline-image-file">Image</Label>
                            <Input
                                id="inline-image-file"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    chooseInlineImage(event.target.files?.[0])
                                }
                            />
                        </div>
                        {imagePreviewUrl && (
                            <img
                                src={imagePreviewUrl}
                                alt="Selected upload preview"
                                className="max-h-80 w-full rounded-md border object-contain"
                            />
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="inline-image-alt">
                                Alternative text
                            </Label>
                            <Input
                                id="inline-image-alt"
                                value={imageAlt}
                                maxLength={255}
                                onChange={(event) =>
                                    setImageAlt(event.target.value)
                                }
                                placeholder="Describe the image for readers using assistive technology"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="inline-image-caption">
                                Caption (optional)
                            </Label>
                            <Input
                                id="inline-image-caption"
                                value={imageCaption}
                                maxLength={500}
                                onChange={(event) =>
                                    setImageCaption(event.target.value)
                                }
                                placeholder="Context shown beneath the image"
                            />
                        </div>
                        <InputError
                            message={
                                imageError ??
                                errorText(inlineImage.errors.image) ??
                                errorText(inlineImage.errors.alt_text) ??
                                errorText(inlineImage.errors.caption)
                            }
                        />
                        {inlineImage.progress && (
                            <progress
                                className="h-2 w-full"
                                value={inlineImage.progress.percentage}
                                max={100}
                            >
                                {inlineImage.progress.percentage}%
                            </progress>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeImageDialog}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                inlineImage.processing ||
                                !imageFile ||
                                imageAlt.trim() === ''
                            }
                            onClick={uploadInlineImage}
                        >
                            {inlineImage.processing
                                ? 'Uploading…'
                                : 'Insert image'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function ToolbarButton({
    label,
    active = false,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Button
            type="button"
            size="icon"
            variant={active ? 'secondary' : 'ghost'}
            disabled={disabled}
            onClick={onClick}
            aria-label={label}
            aria-pressed={active}
        >
            {children}
        </Button>
    );
}

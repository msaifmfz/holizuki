import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Code,
    Heading2,
    Heading3,
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
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { RichTextDocument } from '@/types';

type Props = {
    value: RichTextDocument | null;
    onChange?: (value: RichTextDocument) => void;
    readOnly?: boolean;
    className?: string;
};

export default function RichTextEditor({
    value,
    onChange,
    readOnly = false,
    className,
}: Props) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                link: {
                    openOnClick: readOnly,
                    defaultProtocol: 'https',
                    HTMLAttributes: {
                        rel: 'noopener noreferrer',
                        target: null,
                    },
                },
            }),
        ],
        content: value ?? { type: 'doc', content: [{ type: 'paragraph' }] },
        editable: !readOnly,
        immediatelyRender: false,
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
                        onClick={() =>
                            editor.chain().focus().toggleCodeBlock().run()
                        }
                    >
                        <Code />
                    </ToolbarButton>
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

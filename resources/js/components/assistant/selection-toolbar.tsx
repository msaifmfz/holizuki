import type { Editor } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import { CornerDownLeft, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { TRANSFORM_PRESETS } from '@/lib/assistant/transforms';
import type { TransformPreset } from '@/lib/assistant/transforms';

/**
 * The floating AI toolbar that appears over a text selection: one-tap
 * transform presets plus a free-form instruction. The result comes back as a
 * reviewable change card, never as a silent replacement.
 */
export default function SelectionToolbar({
    editor,
    busy,
    onTransform,
}: {
    editor: Editor;
    busy: boolean;
    onTransform: (
        selection: string,
        preset: TransformPreset,
        instruction?: string,
    ) => void;
}) {
    const [instruction, setInstruction] = useState('');

    const run = (preset: TransformPreset, custom?: string) => {
        const { from, to } = editor.state.selection;
        const selection = editor.state.doc.textBetween(from, to, '\n\n').trim();

        if (selection === '' || busy) {
            return;
        }

        setInstruction('');
        onTransform(selection, preset, custom);
    };

    return (
        <BubbleMenu
            editor={editor}
            shouldShow={({ editor: current, state }) =>
                current.isEditable &&
                !state.selection.empty &&
                state.doc
                    .textBetween(state.selection.from, state.selection.to, ' ')
                    .trim() !== '' &&
                !current.isActive('image')
            }
        >
            <div className="grid w-72 gap-1.5 rounded-lg border bg-popover p-2 shadow-md">
                <div className="flex items-center gap-1">
                    <Sparkles
                        className="size-3.5 shrink-0 text-primary"
                        aria-hidden
                    />
                    {TRANSFORM_PRESETS.map(({ preset, label }) => (
                        <Button
                            key={preset}
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-6 px-1.5 text-xs"
                            disabled={busy}
                            onClick={() => run(preset)}
                        >
                            {label}
                        </Button>
                    ))}
                </div>
                <form
                    className="flex items-center gap-1"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (instruction.trim() !== '') {
                            run('custom', instruction.trim());
                        }
                    }}
                >
                    <Input
                        value={instruction}
                        placeholder="Or tell the AI what to do with it…"
                        className="h-7 flex-1 text-xs"
                        disabled={busy}
                        onChange={(event) => setInstruction(event.target.value)}
                    />
                    <Button
                        type="submit"
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        aria-label="Run custom instruction"
                        disabled={busy || instruction.trim() === ''}
                    >
                        <CornerDownLeft className="size-3.5" />
                    </Button>
                </form>
            </div>
        </BubbleMenu>
    );
}

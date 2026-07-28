import { Check, LoaderCircle, MessageSquareText, Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { AuthoringImageComposition, AuthoringImagePassage } from '@/types';

/** What saving a photo block's writing sends back to the server. */
export type CompositionUpdate = {
    selected_passage_id: string | null;
    passage_markdown: string;
};

export function PassageComposer({
    composition,
    passages,
    generating,
    authoringBusy,
    saving,
    assistantAvailable,
    flowSaved,
    onGenerate,
    onDirtyChange,
    onSave,
}: {
    composition: AuthoringImageComposition;
    passages: AuthoringImagePassage[];
    generating: boolean;
    authoringBusy: boolean;
    saving: boolean;
    assistantAvailable: boolean;
    flowSaved: boolean;
    onGenerate: () => Promise<unknown> | void;
    onDirtyChange: (compositionId: string, dirty: boolean) => void;
    onSave: (
        composition: AuthoringImageComposition,
        update: CompositionUpdate,
    ) => Promise<boolean | void> | boolean | void;
}) {
    const selected =
        passages.find(
            (passage) => passage.id === composition.selected_passage_id,
        ) ?? null;
    const [selectedPassageId, setSelectedPassageId] = useState<string | null>(
        composition.selected_passage_id,
    );
    const [passageMarkdown, setPassageMarkdown] = useState(
        composition.passage_markdown || selected?.passage_markdown || '',
    );
    const locallySelectedPassage =
        passages.find((passage) => passage.id === selectedPassageId) ?? null;
    const effectiveSelectedPassageId =
        composition.passage_stale || locallySelectedPassage?.status === 'stale'
            ? null
            : selectedPassageId;
    const persistedPassageMarkdown =
        composition.passage_markdown || selected?.passage_markdown || '';
    const dirty =
        composition.passage_stale ||
        effectiveSelectedPassageId !== composition.selected_passage_id ||
        passageMarkdown !== persistedPassageMarkdown;

    useEffect(() => {
        onDirtyChange(composition.id, dirty);
    }, [composition.id, dirty, onDirtyChange]);

    useEffect(
        () => () => onDirtyChange(composition.id, false),
        [composition.id, onDirtyChange],
    );

    const choosePassage = (passage: AuthoringImagePassage): void => {
        setSelectedPassageId(passage.id);
        setPassageMarkdown(passage.passage_markdown);
    };

    const savePassage = async (): Promise<void> => {
        const normalizedPassage = passageMarkdown.trim();
        setSelectedPassageId(effectiveSelectedPassageId);
        setPassageMarkdown(normalizedPassage);
        await onSave(composition, {
            selected_passage_id: effectiveSelectedPassageId,
            passage_markdown: normalizedPassage,
        });
    };

    return (
        <div className="grid gap-4 rounded-xl border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium">
                            {composition.media.length === 1
                                ? composition.media[0]?.display_name
                                : `${composition.media.length} photo sequence`}
                        </p>
                        {composition.passage_stale && (
                            <Badge variant="destructive">Review again</Badge>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {composition.section_heading ??
                            'Choose an article section'}
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={
                        generating ||
                        authoringBusy ||
                        !assistantAvailable ||
                        composition.section_heading === null
                    }
                    onClick={() => void onGenerate()}
                >
                    {generating ? (
                        <LoaderCircle className="animate-spin" />
                    ) : (
                        <MessageSquareText />
                    )}
                    {generating
                        ? 'Writing options…'
                        : passages.length > 0
                          ? 'Write three new options'
                          : 'Write three passage options'}
                </Button>
            </div>

            {passages.length > 0 && (
                <div
                    className="grid gap-3 lg:grid-cols-3"
                    role="radiogroup"
                    aria-label="Passage options"
                >
                    {passages.map((passage, index) => {
                        const active =
                            effectiveSelectedPassageId === passage.id;

                        return (
                            <button
                                key={passage.id}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                className={cn(
                                    'grid gap-2 rounded-lg border p-3 text-left text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    active
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:bg-muted/50',
                                )}
                                disabled={
                                    !flowSaved || passage.status === 'stale'
                                }
                                onClick={() => choosePassage(passage)}
                            >
                                <span className="flex items-center justify-between gap-2 font-medium">
                                    {passage.label || `Option ${index + 1}`}
                                    {active && (
                                        <Check className="size-4 text-primary" />
                                    )}
                                </span>
                                <span className="line-clamp-6 whitespace-pre-wrap text-muted-foreground">
                                    {passage.passage_markdown}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor={`passage-${composition.id}`}>
                    {passages.length > 0
                        ? 'Selected passage — edit as much as you like'
                        : 'Write your own passage'}
                </Label>
                <p className="text-xs text-muted-foreground">
                    Choose an AI option or write one yourself. One saved passage
                    is required to continue.
                </p>
                <Textarea
                    id={`passage-${composition.id}`}
                    rows={6}
                    maxLength={12_000}
                    value={passageMarkdown}
                    disabled={!flowSaved}
                    placeholder="Write the words that should accompany this photo. You can also ask the assistant for three starting points."
                    onChange={(event) => setPassageMarkdown(event.target.value)}
                />
                <p className="text-right text-xs text-muted-foreground">
                    {passageMarkdown.length.toLocaleString()} / 12,000
                </p>
            </div>

            <Button
                type="button"
                size="sm"
                className="justify-self-start"
                disabled={
                    saving ||
                    !flowSaved ||
                    !dirty ||
                    passageMarkdown.trim() === ''
                }
                onClick={() => void savePassage()}
            >
                {saving ? <LoaderCircle className="animate-spin" /> : <Save />}
                {composition.passage_stale
                    ? 'Review and save passage'
                    : dirty
                      ? 'Save passage'
                      : 'Passage saved'}
            </Button>
        </div>
    );
}

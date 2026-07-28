import { Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import { Textarea } from '@/components/ui/textarea';
import type {
    AuthoringImageFlowItem,
    AuthoringImageGuidance,
    AuthoringImageUsage,
    AuthoringMediaData,
    AuthoringMediaStoryPriority,
    AuthoringPlanOption,
} from '@/types';
import { PhotoGuidance } from './photo-guidance';
import type { StoryMediaEdits } from './story-media-edits';

export function StoryPhotoCard({
    media,
    guidance,
    flowItem,
    sections,
    removalDisabled,
    onChange,
    onFlowChange,
    onRemove,
}: {
    media: AuthoringMediaData;
    guidance: AuthoringImageGuidance | null;
    flowItem: AuthoringImageFlowItem;
    sections: AuthoringPlanOption['sections'];
    removalDisabled: boolean;
    onChange: (
        media: AuthoringMediaData,
        updates: Partial<StoryMediaEdits>,
    ) => Promise<boolean | void> | boolean | void;
    onFlowChange: (
        mediaId: number,
        updates: Pick<AuthoringImageFlowItem, 'usage' | 'section_heading'>,
    ) => void;
    onRemove: (media: AuthoringMediaData) => Promise<void> | void;
}) {
    const [edits, setEdits] = useState<StoryMediaEdits>({
        display_name: media.display_name,
        alt_text: media.alt_text,
        caption: media.caption,
        story_priority: media.story_priority,
        story_notes: media.story_notes,
    });
    const dirtyFieldsRef = useRef(new Map<keyof StoryMediaEdits, number>());
    const editGenerationRef = useRef(0);
    const [removalConfirmationOpen, setRemovalConfirmationOpen] =
        useState(false);

    useEffect(() => {
        setEdits((current) => ({
            display_name: dirtyFieldsRef.current.has('display_name')
                ? current.display_name
                : media.display_name,
            alt_text: dirtyFieldsRef.current.has('alt_text')
                ? current.alt_text
                : media.alt_text,
            caption: dirtyFieldsRef.current.has('caption')
                ? current.caption
                : media.caption,
            story_priority: dirtyFieldsRef.current.has('story_priority')
                ? current.story_priority
                : media.story_priority,
            story_notes: dirtyFieldsRef.current.has('story_notes')
                ? current.story_notes
                : media.story_notes,
        }));
    }, [
        media.alt_text,
        media.caption,
        media.display_name,
        media.story_notes,
        media.story_priority,
    ]);

    const update = <Key extends keyof StoryMediaEdits>(
        key: Key,
        value: StoryMediaEdits[Key],
    ): void => {
        dirtyFieldsRef.current.set(key, ++editGenerationRef.current);
        setEdits((current) => ({ ...current, [key]: value }));
    };

    const persist = (
        updates: Partial<StoryMediaEdits>,
        keys: Array<keyof StoryMediaEdits>,
    ): void => {
        const generations = new Map(
            keys.map((key) => [key, dirtyFieldsRef.current.get(key)]),
        );

        void Promise.resolve(onChange(media, updates))
            .then((saved) => {
                if (saved !== false) {
                    keys.forEach((key) => {
                        if (
                            dirtyFieldsRef.current.get(key) ===
                            generations.get(key)
                        ) {
                            dirtyFieldsRef.current.delete(key);
                        }
                    });
                }
            })
            .catch(() => undefined);
    };

    const commit = (key: keyof StoryMediaEdits): void => {
        if (edits[key] === media[key]) {
            if (dirtyFieldsRef.current.has(key)) {
                persist({ [key]: edits[key] }, [key]);
            }

            return;
        }

        persist({ [key]: edits[key] }, [key]);
    };

    const applyGuidanceMetadata = (): void => {
        if (!guidance) {
            return;
        }

        const updates = {
            display_name: guidance.suggested_name || edits.display_name,
            alt_text: guidance.alt_text || edits.alt_text,
            caption: guidance.caption || edits.caption,
        };
        const keys = Object.keys(updates) as Array<keyof StoryMediaEdits>;
        keys.forEach((key) =>
            dirtyFieldsRef.current.set(key, ++editGenerationRef.current),
        );
        setEdits((current) => ({ ...current, ...updates }));
        persist(updates, keys);
    };

    return (
        <Card className="overflow-hidden">
            <div className="grid md:grid-cols-[15rem_minmax(0,1fr)]">
                <div className="relative min-h-52 bg-muted md:min-h-full">
                    <img
                        src={media.url}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                    <div className="absolute top-2 right-2 flex gap-2">
                        <Badge variant="secondary">
                            {media.story_priority}
                        </Badge>
                        <Dialog
                            open={removalConfirmationOpen}
                            onOpenChange={setRemovalConfirmationOpen}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="secondary"
                                    aria-label={`Remove ${media.display_name}`}
                                    disabled={removalDisabled}
                                >
                                    <Trash2 />
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Remove “{media.display_name}”?
                                    </DialogTitle>
                                    <DialogDescription>
                                        This permanently removes the photo file
                                        from this post and any saved passage
                                        that belongs only to this photo. A photo
                                        already used by the article must be
                                        removed from the article first.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            Keep photo
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={removalDisabled}
                                        onClick={() => {
                                            setRemovalConfirmationOpen(false);
                                            void onRemove(media);
                                        }}
                                    >
                                        Remove photo
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <CardContent className="grid min-w-0 gap-5 p-5">
                    <div className="grid gap-4 lg:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`media-name-${media.id}`}>
                                Photo name
                            </Label>
                            <Input
                                id={`media-name-${media.id}`}
                                value={edits.display_name}
                                maxLength={255}
                                onChange={(event) =>
                                    update('display_name', event.target.value)
                                }
                                onBlur={() => commit('display_name')}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`media-priority-${media.id}`}>
                                Is this photo essential?
                            </Label>
                            <Select
                                value={edits.story_priority}
                                disabled={media.used_in_draft}
                                onValueChange={(value) => {
                                    const storyPriority =
                                        value as AuthoringMediaStoryPriority;
                                    update('story_priority', storyPriority);
                                    persist(
                                        {
                                            story_priority: storyPriority,
                                        },
                                        ['story_priority'],
                                    );
                                }}
                            >
                                <SelectTrigger
                                    id={`media-priority-${media.id}`}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="required">
                                        Required — must appear
                                    </SelectItem>
                                    <SelectItem value="optional">
                                        Optional — AI may leave it out
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {media.used_in_draft && (
                                <p className="text-xs text-muted-foreground">
                                    This photo is already used in the article,
                                    so it stays required.
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`media-notes-${media.id}`}>
                            What should readers understand from this photo?
                        </Label>
                        <Textarea
                            id={`media-notes-${media.id}`}
                            rows={3}
                            maxLength={2000}
                            value={edits.story_notes}
                            placeholder="For example: This was the first harvest after the drought, and the smaller fruit surprised us."
                            onChange={(event) =>
                                update('story_notes', event.target.value)
                            }
                            onBlur={() => commit('story_notes')}
                        />
                        <p className="text-xs text-muted-foreground">
                            Add names, places, dates, or context the assistant
                            cannot safely learn from the image alone.
                        </p>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`media-alt-${media.id}`}>
                                Alternative text
                            </Label>
                            <Textarea
                                id={`media-alt-${media.id}`}
                                rows={3}
                                maxLength={255}
                                value={edits.alt_text}
                                placeholder="Describe the useful visual information…"
                                onChange={(event) =>
                                    update('alt_text', event.target.value)
                                }
                                onBlur={() => commit('alt_text')}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`media-caption-${media.id}`}>
                                Caption (optional)
                            </Label>
                            <Textarea
                                id={`media-caption-${media.id}`}
                                rows={3}
                                maxLength={500}
                                value={edits.caption}
                                onChange={(event) =>
                                    update('caption', event.target.value)
                                }
                                onBlur={() => commit('caption')}
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`media-usage-${media.id}`}>
                                Use in this article
                            </Label>
                            <Select
                                value={flowItem.usage}
                                disabled={media.story_priority === 'required'}
                                onValueChange={(value) => {
                                    const usage = value as AuthoringImageUsage;

                                    onFlowChange(media.id, {
                                        usage,
                                        section_heading:
                                            usage === 'exclude'
                                                ? null
                                                : flowItem.section_heading,
                                    });
                                }}
                            >
                                <SelectTrigger id={`media-usage-${media.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="include">
                                        Include this photo
                                    </SelectItem>
                                    <SelectItem value="exclude">
                                        Leave it out
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`media-section-${media.id}`}>
                                Article section
                            </Label>
                            <Select
                                value={flowItem.section_heading ?? 'none'}
                                disabled={flowItem.usage === 'exclude'}
                                onValueChange={(value) =>
                                    onFlowChange(media.id, {
                                        usage: flowItem.usage,
                                        section_heading:
                                            value === 'none' ? null : value,
                                    })
                                }
                            >
                                <SelectTrigger id={`media-section-${media.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Choose a section
                                    </SelectItem>
                                    {sections.map((section) => (
                                        <SelectItem
                                            key={section.heading}
                                            value={section.heading}
                                        >
                                            {section.heading}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {media.error && (
                        <p role="alert" className="text-sm text-destructive">
                            {media.error}
                        </p>
                    )}

                    {guidance && (
                        <PhotoGuidance
                            guidance={guidance}
                            onApplyMetadata={applyGuidanceMetadata}
                        />
                    )}
                </CardContent>
            </div>
        </Card>
    );
}

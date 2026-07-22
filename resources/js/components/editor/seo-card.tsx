import { ChevronsUpDown } from 'lucide-react';
import FieldSuggestionChip from '@/components/assistant/field-suggestion-chip';
import GenerateButton from '@/components/assistant/generate-button';
import type {
    EditorAssistant,
    EditorAutosave,
} from '@/components/editor/types';
import InputError from '@/components/input-error';
import OgImageUploader from '@/components/og-image-uploader';
import type { OgImageResponse } from '@/components/og-image-uploader';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { errorText } from '@/lib/post-editor';

/** SEO and social-sharing overrides, with per-field AI suggestions. */
export default function SeoCard({
    autosave,
    assistant,
    postId,
    ogImageUrl,
    onOgImageChange,
}: {
    autosave: EditorAutosave;
    assistant: EditorAssistant;
    postId: number;
    ogImageUrl: string | null;
    onOgImageChange: (response: OgImageResponse) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle>SEO</CardTitle>
                    <GenerateButton
                        label="Generate all"
                        busy={assistant.generating}
                        onClick={() =>
                            void assistant.generateMetadata([
                                'seo_title',
                                'meta_description',
                                'og_title',
                                'og_description',
                            ])
                        }
                    />
                </div>
                <CardDescription>
                    Optional overrides — search and social fall back to the
                    title and excerpt.
                </CardDescription>
                {assistant.narration && (
                    <p className="animate-pulse text-xs text-muted-foreground">
                        ✦ {assistant.narration}
                    </p>
                )}
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="seo-title">SEO title</Label>
                    <Input
                        id="seo-title"
                        value={autosave.data.seo_title}
                        maxLength={255}
                        onChange={(event) =>
                            autosave.setData('seo_title', event.target.value)
                        }
                        placeholder={autosave.data.title}
                    />
                    <div className="flex justify-between text-xs text-muted-foreground">
                        <InputError
                            message={errorText(autosave.errors.seo_title)}
                        />
                        <span
                            className={
                                autosave.data.seo_title.length > 60
                                    ? 'text-amber-600'
                                    : undefined
                            }
                        >
                            {autosave.data.seo_title.length}/60
                        </span>
                    </div>
                    {assistant.suggestions.seo_title && (
                        <FieldSuggestionChip
                            change={assistant.suggestions.seo_title}
                            busy={assistant.deciding}
                            onAccept={assistant.accept}
                            onReject={assistant.reject}
                        />
                    )}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="meta-description">Meta description</Label>
                    <Textarea
                        id="meta-description"
                        value={autosave.data.meta_description}
                        maxLength={255}
                        rows={3}
                        onChange={(event) =>
                            autosave.setData(
                                'meta_description',
                                event.target.value,
                            )
                        }
                        placeholder="Shown in search results — falls back to the excerpt"
                    />
                    <div className="flex justify-between text-xs text-muted-foreground">
                        <InputError
                            message={errorText(
                                autosave.errors.meta_description,
                            )}
                        />
                        <span
                            className={
                                autosave.data.meta_description.length > 160
                                    ? 'text-amber-600'
                                    : undefined
                            }
                        >
                            {autosave.data.meta_description.length}/160
                        </span>
                    </div>
                    {assistant.suggestions.meta_description && (
                        <FieldSuggestionChip
                            change={assistant.suggestions.meta_description}
                            busy={assistant.deciding}
                            onAccept={assistant.accept}
                            onReject={assistant.reject}
                        />
                    )}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="canonical-url">Canonical URL</Label>
                    <Input
                        id="canonical-url"
                        type="url"
                        value={autosave.data.canonical_url}
                        maxLength={2048}
                        onChange={(event) =>
                            autosave.setData(
                                'canonical_url',
                                event.target.value,
                            )
                        }
                        placeholder="Only if this post first appeared elsewhere"
                    />
                    <InputError
                        message={errorText(autosave.errors.canonical_url)}
                    />
                </div>
                <Collapsible className="grid gap-4">
                    <CollapsibleTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="justify-between"
                        >
                            Social sharing
                            <ChevronsUpDown />
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="og-title">Social title</Label>
                            <Input
                                id="og-title"
                                value={autosave.data.og_title}
                                maxLength={255}
                                onChange={(event) =>
                                    autosave.setData(
                                        'og_title',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={errorText(autosave.errors.og_title)}
                            />
                            {assistant.suggestions.og_title && (
                                <FieldSuggestionChip
                                    change={assistant.suggestions.og_title}
                                    busy={assistant.deciding}
                                    onAccept={assistant.accept}
                                    onReject={assistant.reject}
                                />
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="og-description">
                                Social description
                            </Label>
                            <Textarea
                                id="og-description"
                                value={autosave.data.og_description}
                                maxLength={500}
                                rows={2}
                                onChange={(event) =>
                                    autosave.setData(
                                        'og_description',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={errorText(
                                    autosave.errors.og_description,
                                )}
                            />
                            {assistant.suggestions.og_description && (
                                <FieldSuggestionChip
                                    change={
                                        assistant.suggestions.og_description
                                    }
                                    busy={assistant.deciding}
                                    onAccept={assistant.accept}
                                    onReject={assistant.reject}
                                />
                            )}
                        </div>
                        <OgImageUploader
                            postId={postId}
                            lockVersion={autosave.data.lock_version}
                            imageUrl={ogImageUrl}
                            onChange={onOgImageChange}
                        />
                    </CollapsibleContent>
                </Collapsible>
                <div className="flex items-start gap-2">
                    <Checkbox
                        id="noindex"
                        checked={autosave.data.noindex}
                        onCheckedChange={(checked) =>
                            autosave.setData('noindex', checked === true)
                        }
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="noindex">
                            Hide from search engines
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            Adds a noindex tag and removes the post from the
                            sitemap. Readers can still open it directly.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

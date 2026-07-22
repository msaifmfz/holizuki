import FieldSuggestionChip from '@/components/assistant/field-suggestion-chip';
import GenerateButton from '@/components/assistant/generate-button';
import type {
    EditorAssistant,
    EditorAutosave,
} from '@/components/editor/types';
import InputError from '@/components/input-error';
import TagMultiSelect from '@/components/tag-multi-select';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { errorText } from '@/lib/post-editor';
import type { EditorOption } from '@/types';

/** Category, tags, and author — with AI tag suggestions. */
export default function OrganizationCard({
    autosave,
    assistant,
    categories,
    authors,
    tagSuggestions,
}: {
    autosave: EditorAutosave;
    assistant: EditorAssistant;
    categories: EditorOption[];
    authors: EditorOption[];
    tagSuggestions: string[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Organization</CardTitle>
                <CardDescription>
                    A category is required to publish. Tags are optional.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="category">Category</Label>
                    <Select
                        value={autosave.data.category_id?.toString() ?? ''}
                        onValueChange={(value) =>
                            autosave.setData('category_id', Number(value))
                        }
                    >
                        <SelectTrigger id="category">
                            <SelectValue placeholder="Choose a category" />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={category.id.toString()}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {categories.length === 0 && (
                        <p className="text-xs text-muted-foreground">
                            No categories yet — create one from the Categories
                            page.
                        </p>
                    )}
                    <InputError
                        message={errorText(autosave.errors.category_id)}
                    />
                </div>
                <div className="grid gap-2">
                    <div className="flex items-center justify-between">
                        <Label>Tags</Label>
                        <GenerateButton
                            label="Suggest"
                            busy={assistant.generating}
                            onClick={() =>
                                void assistant.generateMetadata(['tags'])
                            }
                        />
                    </div>
                    <TagMultiSelect
                        value={autosave.data.tags}
                        suggestions={tagSuggestions}
                        onChange={(tags) => autosave.setData('tags', tags)}
                    />
                    <InputError message={errorText(autosave.errors.tags)} />
                    {assistant.suggestions.tags && (
                        <FieldSuggestionChip
                            change={assistant.suggestions.tags}
                            busy={assistant.deciding}
                            onAccept={assistant.accept}
                            onReject={assistant.reject}
                        />
                    )}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="author">Author</Label>
                    <Select
                        value={autosave.data.author_id?.toString() ?? ''}
                        onValueChange={(value) =>
                            autosave.setData('author_id', Number(value))
                        }
                    >
                        <SelectTrigger id="author">
                            <SelectValue placeholder="Choose an author" />
                        </SelectTrigger>
                        <SelectContent>
                            {authors.map((author) => (
                                <SelectItem
                                    key={author.id}
                                    value={author.id.toString()}
                                >
                                    {author.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={errorText(autosave.errors.author_id)}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

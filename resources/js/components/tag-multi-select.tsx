import { ChevronsUpDown, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type Props = {
    value: string[];
    suggestions: string[];
    onChange: (tags: string[]) => void;
    maxTags?: number;
};

export default function TagMultiSelect({
    value,
    suggestions,
    onChange,
    maxTags = 10,
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const selected = new Set(value.map((tag) => tag.toLowerCase()));
    const available = suggestions.filter(
        (tag) => !selected.has(tag.toLowerCase()),
    );
    const trimmed = query.trim();
    const atLimit = value.length >= maxTags;
    const canCreate =
        !atLimit &&
        trimmed !== '' &&
        trimmed.length <= 50 &&
        !selected.has(trimmed.toLowerCase()) &&
        !suggestions.some((tag) => tag.toLowerCase() === trimmed.toLowerCase());

    const addTag = (tag: string) => {
        if (atLimit || selected.has(tag.toLowerCase())) {
            return;
        }

        onChange([...value, tag]);
        setQuery('');
    };

    const removeTag = (tag: string) => {
        onChange(value.filter((current) => current !== tag));
    };

    return (
        <div className="grid gap-2">
            {value.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {value.map((tag) => (
                        <Badge
                            key={tag}
                            variant="secondary"
                            className="gap-1 pr-1"
                        >
                            {tag}
                            <button
                                type="button"
                                onClick={() => removeTag(tag)}
                                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                                aria-label={`Remove tag ${tag}`}
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        disabled={atLimit}
                        className="justify-between font-normal text-muted-foreground"
                    >
                        {atLimit
                            ? `Limit of ${maxTags} tags reached`
                            : 'Add tags…'}
                        <ChevronsUpDown className="size-4 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-(--radix-popover-trigger-width) p-0"
                    align="start"
                >
                    <Command>
                        <CommandInput
                            placeholder="Search or create a tag"
                            value={query}
                            onValueChange={setQuery}
                            maxLength={50}
                        />
                        <CommandList>
                            <CommandEmpty>
                                {trimmed === ''
                                    ? 'Type to create a tag.'
                                    : 'No matching tags.'}
                            </CommandEmpty>
                            {available.length > 0 && (
                                <CommandGroup heading="Existing tags">
                                    {available.map((tag) => (
                                        <CommandItem
                                            key={tag}
                                            value={tag}
                                            onSelect={() => addTag(tag)}
                                        >
                                            {tag}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}
                            {canCreate && (
                                <CommandGroup heading="New tag">
                                    <CommandItem
                                        value={trimmed}
                                        onSelect={() => addTag(trimmed)}
                                    >
                                        <Plus className="size-4" />
                                        Create “{trimmed}”
                                    </CommandItem>
                                </CommandGroup>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}

import { Form, Head, router } from '@inertiajs/react';
import { Pencil, TagIcon, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Admin/Controllers/TagController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import type { TagItem } from '@/types';

type Props = { tags: TagItem[] };

export default function TagsIndex({ tags }: Props) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<TagItem | null>(null);

    const removeTag = (tag: TagItem) => {
        const warning =
            tag.posts_count > 0
                ? `Delete "${tag.name}"? It will be removed from ${tag.posts_count} ${
                      tag.posts_count === 1 ? 'post' : 'posts'
                  }.`
                : `Delete "${tag.name}"?`;

        if (window.confirm(warning)) {
            router.visit(destroy(tag.id));
        }
    };

    return (
        <>
            <Head title="Tags" />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 lg:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Tags
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Posts can carry several tags. Tags are also created
                            on the fly from the post editor.
                        </p>
                    </div>
                    <Button onClick={() => setCreating(true)}>
                        <TagIcon />
                        New tag
                    </Button>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {tags.length === 0 ? (
                            <div className="grid min-h-48 place-items-center p-8 text-center">
                                <div>
                                    <p className="font-medium">No tags yet</p>
                                    <p className="text-sm text-muted-foreground">
                                        Create a tag here or add one while
                                        editing a post.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {tags.map((tag) => (
                                    <div
                                        key={tag.id}
                                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="truncate font-medium">
                                                    {tag.name}
                                                </h2>
                                                <Badge variant="outline">
                                                    {tag.posts_count}{' '}
                                                    {tag.posts_count === 1
                                                        ? 'post'
                                                        : 'posts'}
                                                </Badge>
                                            </div>
                                            <p className="truncate text-sm text-muted-foreground">
                                                /tags/{tag.slug}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setEditing(tag)}
                                            >
                                                <Pencil />
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => removeTag(tag)}
                                            >
                                                <Trash2 />
                                                <span className="sr-only">
                                                    Delete {tag.name}
                                                </span>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New tag</DialogTitle>
                        <DialogDescription>
                            The slug is generated automatically from the name.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        onSuccess={() => setCreating(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <TagFields errors={errors} />
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Create tag
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit tag</DialogTitle>
                        <DialogDescription>
                            Renaming a tag also updates its public URL.
                        </DialogDescription>
                    </DialogHeader>
                    {editing && (
                        <Form
                            key={editing.id}
                            {...update.form(editing.id)}
                            onSuccess={() => setEditing(null)}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <TagFields errors={errors} tag={editing} />
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save changes
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

TagsIndex.layout = { breadcrumbs: [{ title: 'Tags', href: index() }] };

function TagFields({
    errors,
    tag,
}: {
    errors: Record<string, string>;
    tag?: TagItem;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="tag-name">Name</Label>
            <Input
                id="tag-name"
                name="name"
                defaultValue={tag?.name}
                required
                maxLength={50}
            />
            <InputError message={errors.name} />
        </div>
    );
}

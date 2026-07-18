import { Form, Head, router } from '@inertiajs/react';
import { FolderPlus, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/CategoryController';
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
import { Textarea } from '@/components/ui/textarea';
import type { CategoryItem } from '@/types';

type Props = { categories: CategoryItem[] };

export default function CategoriesIndex({ categories }: Props) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<CategoryItem | null>(null);

    const removeCategory = (category: CategoryItem) => {
        const warning =
            category.posts_count > 0
                ? `Delete "${category.name}"? ${category.posts_count} ${
                      category.posts_count === 1 ? 'post' : 'posts'
                  } will become uncategorized.`
                : `Delete "${category.name}"?`;

        if (window.confirm(warning)) {
            router.visit(destroy(category.id));
        }
    };

    return (
        <>
            <Head title="Categories" />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 lg:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Categories
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Each post belongs to one category. Categories power
                            the public topic archives.
                        </p>
                    </div>
                    <Button onClick={() => setCreating(true)}>
                        <FolderPlus />
                        New category
                    </Button>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {categories.length === 0 ? (
                            <div className="grid min-h-48 place-items-center p-8 text-center">
                                <div>
                                    <p className="font-medium">
                                        No categories yet
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Create your first category to organize
                                        posts.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {categories.map((category) => (
                                    <div
                                        key={category.id}
                                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="truncate font-medium">
                                                    {category.name}
                                                </h2>
                                                <Badge variant="outline">
                                                    {category.posts_count}{' '}
                                                    {category.posts_count === 1
                                                        ? 'post'
                                                        : 'posts'}
                                                </Badge>
                                            </div>
                                            <p className="truncate text-sm text-muted-foreground">
                                                /categories/{category.slug}
                                            </p>
                                            {category.description && (
                                                <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                    {category.description}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditing(category)
                                                }
                                            >
                                                <Pencil />
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    removeCategory(category)
                                                }
                                            >
                                                <Trash2 />
                                                <span className="sr-only">
                                                    Delete {category.name}
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
                        <DialogTitle>New category</DialogTitle>
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
                                <CategoryFields errors={errors} />
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Create category
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
                        <DialogTitle>Edit category</DialogTitle>
                        <DialogDescription>
                            Renaming a category also updates its public URL.
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
                                    <CategoryFields
                                        errors={errors}
                                        category={editing}
                                    />
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

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: index() }],
};

function CategoryFields({
    errors,
    category,
}: {
    errors: Record<string, string>;
    category?: CategoryItem;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="category-name">Name</Label>
                <Input
                    id="category-name"
                    name="name"
                    defaultValue={category?.name}
                    required
                    maxLength={100}
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="category-description">
                    Description{' '}
                    <span className="font-normal text-muted-foreground">
                        (optional, shown on the archive page)
                    </span>
                </Label>
                <Textarea
                    id="category-description"
                    name="description"
                    defaultValue={category?.description ?? ''}
                    maxLength={500}
                    rows={3}
                />
                <InputError message={errors.description} />
            </div>
        </>
    );
}

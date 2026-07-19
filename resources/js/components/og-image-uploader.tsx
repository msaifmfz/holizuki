import { useHttp } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/PostOgImageController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { errorText } from '@/lib/post-editor';

export type OgImageResponse = {
    og_image_url: string | null;
    lock_version: number;
    updated_at: string;
};

type Props = {
    postId: number;
    lockVersion: number;
    imageUrl: string | null;
    onChange: (response: OgImageResponse) => void;
};

export default function OgImageUploader({
    postId,
    lockVersion,
    imageUrl,
    onChange,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [clientError, setClientError] = useState<string | null>(null);
    const upload = useHttp<
        { image: File | null; lock_version: number },
        OgImageResponse
    >({
        image: null,
        lock_version: lockVersion,
    });
    const remove = useHttp<{ lock_version: number }, OgImageResponse>({
        lock_version: lockVersion,
    });

    const uploadFile = async (selected: File | undefined) => {
        if (!selected) {
            return;
        }

        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(
                selected.type,
            ) ||
            selected.size > 5 * 1024 * 1024
        ) {
            setClientError(
                'Choose a JPG, PNG, or WebP image no larger than 5 MB.',
            );

            return;
        }

        setClientError(null);
        upload.transform(() => ({
            image: selected,
            lock_version: lockVersion,
        }));

        try {
            onChange(await upload.post(store.url(postId)));
        } catch {
            setClientError('The image could not be uploaded.');
        }
    };

    const removeImage = async () => {
        remove.transform(() => ({ lock_version: lockVersion }));

        try {
            onChange(await remove.delete(destroy.url(postId)));
        } catch {
            setClientError(
                'The image could not be removed. Reload the post and try again.',
            );
        }
    };

    return (
        <div className="grid gap-2">
            <Label>Social sharing image</Label>
            {imageUrl && (
                <img
                    src={imageUrl}
                    alt=""
                    className="aspect-[1200/630] w-full rounded-md border object-cover"
                />
            )}
            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                onChange={(event) => {
                    void uploadFile(event.target.files?.[0]);
                    event.target.value = '';
                }}
            />
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={upload.processing}
                    onClick={() => inputRef.current?.click()}
                >
                    <ImagePlus />
                    {upload.processing
                        ? 'Uploading…'
                        : imageUrl
                          ? 'Replace'
                          : 'Upload'}
                </Button>
                {imageUrl && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={remove.processing}
                        onClick={removeImage}
                    >
                        <Trash2 />
                        Remove
                    </Button>
                )}
            </div>
            <p className="text-xs text-muted-foreground">
                Shown when the post is shared. Falls back to the featured image.
                1200×630 works best.
            </p>
            <InputError
                message={clientError ?? errorText(upload.errors.image)}
            />
        </div>
    );
}

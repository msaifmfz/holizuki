import { useHttp } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Cropper from 'react-easy-crop';
import type { Area, Point } from 'react-easy-crop';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/PostFeaturedImageController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { cropFeaturedImage, resizeFeaturedImage } from '@/lib/crop-image';
import { errorText } from '@/lib/post-editor';

type ImageResponse = {
    featured_image_url: string | null;
    featured_image_alt: string | null;
    featured_image_caption: string | null;
    lock_version: number;
    updated_at: string;
};

type Props = {
    postId: number;
    lockVersion: number;
    imageUrl: string | null;
    altText: string | null;
    caption: string | null;
    onChange: (response: ImageResponse) => void;
    onAltTextChange: (value: string) => void;
    onCaptionChange: (value: string) => void;
    canRemove: boolean;
};

export default function FeaturedImageUploader({
    postId,
    lockVersion,
    imageUrl,
    altText,
    caption,
    onChange,
    onAltTextChange,
    onCaptionChange,
    canRemove,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [sourceUrl, setSourceUrl] = useState<string | null>(null);
    const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [cropPixels, setCropPixels] = useState<Area | null>(null);
    const [useCrop, setUseCrop] = useState(true);
    const [description, setDescription] = useState(altText ?? '');
    const [clientError, setClientError] = useState<string | null>(null);
    const upload = useHttp<
        { image: File | null; alt_text: string; lock_version: number },
        ImageResponse
    >({
        image: null,
        alt_text: '',
        lock_version: lockVersion,
    });
    const remove = useHttp<{ lock_version: number }, ImageResponse>({
        lock_version: lockVersion,
    });

    useEffect(
        () => () => {
            if (sourceUrl) {
                URL.revokeObjectURL(sourceUrl);
            }
        },
        [sourceUrl],
    );

    const chooseFile = (selected: File | undefined) => {
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
        setFile(selected);
        setSourceUrl(URL.createObjectURL(selected));
        setUseCrop(true);
        setCrop({ x: 0, y: 0 });
        setZoom(1);
    };

    const submit = async () => {
        if (!file || description.trim() === '') {
            setClientError('Alternative text is required.');

            return;
        }

        try {
            const processed =
                useCrop && cropPixels
                    ? await cropFeaturedImage(file, cropPixels)
                    : await resizeFeaturedImage(file);
            upload.transform(() => ({
                image: processed,
                alt_text: description.trim(),
                lock_version: lockVersion,
            }));
            const response = await upload.post(store.url(postId));
            onChange(response);
            setFile(null);
            setSourceUrl(null);
        } catch (error) {
            setClientError(
                error instanceof Error
                    ? error.message
                    : 'The image could not be uploaded.',
            );
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
        <div className="grid gap-3">
            <Label>Featured image</Label>
            {imageUrl ? (
                <>
                    <img
                        src={imageUrl}
                        alt={altText ?? ''}
                        className="aspect-video w-full rounded-md border object-cover"
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="current-featured-alt">
                            Alternative text
                        </Label>
                        <Input
                            id="current-featured-alt"
                            value={altText ?? ''}
                            maxLength={255}
                            onChange={(event) =>
                                onAltTextChange(event.target.value)
                            }
                        />
                        <Label htmlFor="current-featured-caption">
                            Caption (optional)
                        </Label>
                        <Input
                            id="current-featured-caption"
                            value={caption ?? ''}
                            maxLength={500}
                            onChange={(event) =>
                                onCaptionChange(event.target.value)
                            }
                            placeholder="Context shown beneath the image"
                        />
                    </div>
                </>
            ) : (
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    className="flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-md border border-dashed text-sm text-muted-foreground hover:bg-muted/40"
                >
                    <ImagePlus className="size-7" />
                    Choose featured image
                </button>
            )}
            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                onChange={(event) => {
                    chooseFile(event.target.files?.[0]);
                    event.target.value = '';
                }}
            />
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => inputRef.current?.click()}
                >
                    <ImagePlus />
                    {imageUrl ? 'Replace' : 'Upload'}
                </Button>
                {imageUrl && canRemove && (
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
            <InputError
                message={
                    clientError ??
                    errorText(upload.errors.image) ??
                    errorText(upload.errors.alt_text)
                }
            />
            {upload.progress && (
                <progress
                    className="h-2 w-full"
                    value={upload.progress.percentage}
                    max={100}
                >
                    {upload.progress.percentage}%
                </progress>
            )}

            <Dialog
                open={file !== null}
                onOpenChange={(open) => !open && setFile(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Prepare featured image</DialogTitle>
                        <DialogDescription>
                            Crop to 16:9 or keep the original framing. The image
                            will be optimized for the blog.
                        </DialogDescription>
                    </DialogHeader>
                    {sourceUrl && useCrop ? (
                        <div className="relative h-80 overflow-hidden rounded-md bg-black">
                            <Cropper
                                image={sourceUrl}
                                crop={crop}
                                zoom={zoom}
                                aspect={16 / 9}
                                onCropChange={setCrop}
                                onZoomChange={setZoom}
                                onCropComplete={(_, pixels) =>
                                    setCropPixels(pixels)
                                }
                            />
                        </div>
                    ) : sourceUrl ? (
                        <img
                            src={sourceUrl}
                            alt="Selected upload preview"
                            className="max-h-80 w-full rounded-md object-contain"
                        />
                    ) : null}
                    {useCrop && (
                        <div className="grid gap-2">
                            <Label htmlFor="crop-zoom">Zoom</Label>
                            <input
                                id="crop-zoom"
                                type="range"
                                min="1"
                                max="3"
                                step="0.1"
                                value={zoom}
                                onChange={(event) =>
                                    setZoom(Number(event.target.value))
                                }
                            />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="featured-alt">Alternative text</Label>
                        <Input
                            id="featured-alt"
                            value={description}
                            maxLength={255}
                            onChange={(event) =>
                                setDescription(event.target.value)
                            }
                            placeholder="Describe the image for readers using assistive technology"
                        />
                    </div>
                    <InputError message={clientError ?? undefined} />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setUseCrop((current) => !current)}
                        >
                            {useCrop ? 'Use original' : 'Crop 16:9'}
                        </Button>
                        <Button
                            type="button"
                            disabled={upload.processing}
                            onClick={submit}
                        >
                            {upload.processing ? 'Uploading…' : 'Use image'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

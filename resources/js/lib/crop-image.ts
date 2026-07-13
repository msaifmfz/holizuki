import type { Area } from 'react-easy-crop';

const MAX_WIDTH = 1600;
const MAX_HEIGHT = 900;

export async function cropFeaturedImage(file: File, crop: Area): Promise<File> {
    const image = await loadImage(file);
    const scale = Math.min(1, MAX_WIDTH / crop.width, MAX_HEIGHT / crop.height);
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(crop.width * scale));
    canvas.height = Math.max(1, Math.round(crop.height * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Your browser could not prepare the cropped image.');
    }

    context.drawImage(
        image,
        crop.x,
        crop.y,
        crop.width,
        crop.height,
        0,
        0,
        canvas.width,
        canvas.height,
    );

    return canvasFile(canvas, file.name);
}

export async function resizeFeaturedImage(file: File): Promise<File> {
    const image = await loadImage(file);
    const scale = Math.min(
        1,
        MAX_WIDTH / image.naturalWidth,
        MAX_HEIGHT / image.naturalHeight,
    );
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
    canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Your browser could not prepare the image.');
    }

    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    return canvasFile(canvas, file.name);
}

async function loadImage(file: File): Promise<HTMLImageElement> {
    const url = URL.createObjectURL(file);

    try {
        const image = new Image();
        image.src = url;
        await image.decode();

        return image;
    } finally {
        URL.revokeObjectURL(url);
    }
}

function canvasFile(
    canvas: HTMLCanvasElement,
    originalName: string,
): Promise<File> {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('The image could not be processed.'));

                    return;
                }

                resolve(
                    new File(
                        [blob],
                        originalName.replace(/\.[^.]+$/, '') + '.webp',
                        { type: 'image/webp' },
                    ),
                );
            },
            'image/webp',
            0.86,
        );
    });
}

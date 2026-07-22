export type TransformPreset =
    'improve' | 'expand' | 'shorten' | 'simplify' | 'custom';

/**
 * The selection-transform presets offered by the floating AI toolbar. Kept
 * in sync with TransformPrompt::PRESETS on the server.
 */
export const TRANSFORM_PRESETS: Array<{
    preset: Exclude<TransformPreset, 'custom'>;
    label: string;
}> = [
    { preset: 'improve', label: 'Improve' },
    { preset: 'expand', label: 'Expand' },
    { preset: 'shorten', label: 'Shorten' },
    { preset: 'simplify', label: 'Simplify' },
];

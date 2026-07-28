import type { AuthoringMediaData } from '@/types';

/**
 * Fields an author edits directly on a story photo. Shared so the card and the
 * step agree on what one pending edit may carry.
 */
export type StoryMediaEdits = Pick<
    AuthoringMediaData,
    'display_name' | 'alt_text' | 'caption' | 'story_priority' | 'story_notes'
>;

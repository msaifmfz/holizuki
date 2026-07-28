import type { ReviewCheck } from '@/components/editor/guided/review-step';
import type { EditorForm } from '@/components/editor/types';
import { documentHasText } from '@/components/editor/types';
import type { AuthoringImageComposition, AuthoringMediaData } from '@/types';

/**
 * The rules deciding what the guided flow will let an author do next.
 *
 * Kept free of React so each rule can be exercised on its own: getting one
 * wrong either blocks a finished article or lets an unfinished one through.
 */

/** The checklist the review step shows before an article can be published. */
export function buildReviewChecks(
    draft: EditorForm,
    featuredImageUrl: string | null,
): ReviewCheck[] {
    return [
        {
            id: 'title',
            label: 'A clear title',
            help: 'Replace the temporary title with one readers will understand.',
            complete:
                draft.title.trim() !== '' &&
                draft.title.trim().toLowerCase() !== 'untitled post',
        },
        {
            id: 'excerpt',
            label: 'A short summary',
            help: 'Add one or two sentences that introduce the article.',
            complete: draft.excerpt.trim() !== '',
        },
        {
            id: 'body',
            label: 'The article',
            help: 'The draft needs at least one paragraph of readable text.',
            complete: documentHasText(draft.body),
        },
        {
            id: 'category',
            label: 'A category',
            help: 'Choose where readers should find the article.',
            complete: draft.category_id !== null,
        },
        {
            id: 'featured-image',
            label: 'A featured image',
            help: 'Choose a lead image and add useful alternative text.',
            complete:
                featuredImageUrl !== null &&
                draft.featured_image_alt.trim() !== '',
        },
    ];
}

/** Photos eligible to be arranged as story photos, i.e. everything but the lead image. */
export function storyPhotosFrom(
    media: AuthoringMediaData[],
    featuredImageUrl: string | null,
): AuthoringMediaData[] {
    return media.filter(
        (item) =>
            item.url !== featuredImageUrl &&
            item.role !== 'hero' &&
            item.placement !== 'featured',
    );
}

/**
 * Whether the photo step is finished: every story photo has been placed, and
 * every photo the author kept has approved writing that is still current.
 *
 * A post with no story photos is trivially ready.
 */
export function storyPhotoFlowIsReady(
    storyPhotos: AuthoringMediaData[],
    compositions: AuthoringImageComposition[],
    hydrated: boolean,
): boolean {
    if (!hydrated) {
        return false;
    }

    if (storyPhotos.length === 0) {
        return true;
    }

    const assigned = new Set(
        compositions.flatMap((composition) =>
            composition.media.map((item) => item.id),
        ),
    );

    return (
        storyPhotos.every((item) => assigned.has(item.id)) &&
        compositions
            .filter((composition) => composition.usage === 'include')
            .every(
                (composition) =>
                    composition.passage_markdown.trim() !== '' &&
                    !composition.passage_stale,
            )
    );
}

/** What the guided shell tells the author about their unsaved work. */
export function guidedSaveLabel({
    photoChangesPending,
    editorSaveState,
    journeySaveState,
    savedAtLabel,
}: {
    photoChangesPending: boolean;
    editorSaveState: 'saved' | 'saving' | 'error';
    journeySaveState: 'saved' | 'saving' | 'error';
    savedAtLabel: string;
}): string {
    if (photoChangesPending) {
        return 'Save your photo changes before continuing';
    }

    if (editorSaveState === 'error' || journeySaveState === 'error') {
        return 'Some changes could not be saved';
    }

    if (editorSaveState === 'saving' || journeySaveState === 'saving') {
        return 'Saving…';
    }

    return `Saved ${savedAtLabel}`;
}

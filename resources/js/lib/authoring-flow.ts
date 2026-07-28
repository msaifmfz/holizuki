import type {
    AuthoringImageComposition,
    AuthoringImageFlowItem,
    AuthoringImageGuidance,
    AuthoringMediaData,
    AuthoringPlanOption,
} from '@/types';

/**
 * Pure helpers describing the author's arrangement of story photos: how it is
 * seeded, ordered, fingerprinted for change detection, and reconciled when the
 * server sends a newer arrangement while the author has unsaved edits.
 *
 * Kept free of React so the reconciliation rules can be exercised directly.
 */

export function defaultFlowItems(
    media: AuthoringMediaData[],
    guidance: Record<number, AuthoringImageGuidance | undefined>,
): AuthoringImageFlowItem[] {
    return media.map((item, index) => {
        const recommendation = guidance[item.id];

        return {
            usage:
                item.story_priority === 'required'
                    ? 'include'
                    : (recommendation?.usage ?? 'include'),
            section_heading: recommendation?.section_heading ?? null,
            position: index,
            media_ids: [item.id],
        };
    });
}

export function compositionFlowItems(
    compositions: AuthoringImageComposition[],
): AuthoringImageFlowItem[] {
    return compositions.map((composition) => ({
        id: composition.id,
        usage: composition.usage,
        section_heading: composition.section_heading,
        position: composition.position,
        media_ids: composition.media.map((media) => media.id),
    }));
}

export function flowFingerprint(flow: AuthoringImageFlowItem[]): string {
    return JSON.stringify(
        flow.map((item) => ({
            usage: item.usage,
            section_heading: item.section_heading,
            position: item.position,
            media_ids: item.media_ids,
        })),
    );
}

export type ImageFlowState = {
    source: string;
    planId: string | null;
    items: AuthoringImageFlowItem[];
    baselineItems: AuthoringImageFlowItem[];
};

export function reindexFlowItems(
    items: AuthoringImageFlowItem[],
): AuthoringImageFlowItem[] {
    return items.map((item, position) => ({ ...item, position }));
}

export function orderFlowItemsByPlan(
    items: AuthoringImageFlowItem[],
    sections: AuthoringPlanOption['sections'],
): AuthoringImageFlowItem[] {
    const sectionPositions = new Map(
        sections.map((section, index) => [section.heading, index]),
    );

    return reindexFlowItems(
        [...items].sort((left, right) => {
            const leftSection =
                left.usage === 'exclude'
                    ? sections.length + 1
                    : (sectionPositions.get(left.section_heading ?? '') ??
                      sections.length);
            const rightSection =
                right.usage === 'exclude'
                    ? sections.length + 1
                    : (sectionPositions.get(right.section_heading ?? '') ??
                      sections.length);

            return leftSection - rightSection || left.position - right.position;
        }),
    );
}

function flowStateFingerprint(items: AuthoringImageFlowItem[]): string {
    return flowFingerprint(reindexFlowItems(items));
}

export function mediaIdsMatch(
    left: AuthoringImageFlowItem,
    right: AuthoringImageFlowItem,
): boolean {
    return left.media_ids.join('|') === right.media_ids.join('|');
}

function withCurrentCompositionId(
    item: AuthoringImageFlowItem,
    incomingItems: AuthoringImageFlowItem[],
): AuthoringImageFlowItem {
    const matching = incomingItems.find((candidate) =>
        mediaIdsMatch(candidate, item),
    );
    const itemWithoutId = { ...item };

    delete itemWithoutId.id;

    return matching?.id ? { ...itemWithoutId, id: matching.id } : itemWithoutId;
}

export function reconcileEditedFlow(
    currentItems: AuthoringImageFlowItem[],
    incomingItems: AuthoringImageFlowItem[],
    availableMediaIds: number[],
): AuthoringImageFlowItem[] {
    const available = new Set(availableMediaIds);
    const reconciled: AuthoringImageFlowItem[] = [];

    for (const item of currentItems) {
        const remainingMediaIds = item.media_ids.filter((id) =>
            available.has(id),
        );

        if (remainingMediaIds.length === 0) {
            continue;
        }

        if (remainingMediaIds.length === item.media_ids.length) {
            reconciled.push(
                withCurrentCompositionId(
                    { ...item, media_ids: remainingMediaIds },
                    incomingItems,
                ),
            );
            continue;
        }

        for (const mediaId of remainingMediaIds) {
            reconciled.push(
                withCurrentCompositionId(
                    {
                        usage: item.usage,
                        section_heading: item.section_heading,
                        position: item.position,
                        media_ids: [mediaId],
                    },
                    incomingItems,
                ),
            );
        }
    }

    const assigned = new Set(reconciled.flatMap((item) => item.media_ids));

    for (const item of incomingItems) {
        const unassignedMediaIds = item.media_ids.filter(
            (id) => available.has(id) && !assigned.has(id),
        );

        if (unassignedMediaIds.length === 0) {
            continue;
        }

        if (unassignedMediaIds.length === item.media_ids.length) {
            reconciled.push({ ...item });
        } else {
            for (const mediaId of unassignedMediaIds) {
                reconciled.push({
                    usage: item.usage,
                    section_heading: item.section_heading,
                    position: item.position,
                    media_ids: [mediaId],
                });
            }
        }

        unassignedMediaIds.forEach((id) => assigned.add(id));
    }

    return reindexFlowItems(reconciled);
}

export function resolveIncomingFlowState(
    current: ImageFlowState,
    incoming: ImageFlowState,
    availableMediaIds: number[],
): ImageFlowState {
    if (current.source === incoming.source) {
        return current;
    }

    const hasLocalEdits =
        flowStateFingerprint(current.items) !==
        flowStateFingerprint(current.baselineItems);

    if (current.planId !== incoming.planId || !hasLocalEdits) {
        return incoming;
    }

    return {
        ...incoming,
        items: reconcileEditedFlow(
            current.items,
            incoming.items,
            availableMediaIds,
        ),
    };
}

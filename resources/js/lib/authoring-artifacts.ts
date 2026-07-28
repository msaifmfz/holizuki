import type {
    AcceptChangeResponse,
    AuthoringDraftVariant,
    AuthoringImageComposition,
    AuthoringImageGroupSuggestion,
    AuthoringImageGuidance,
    AuthoringImagePassage,
    AuthoringMediaData,
    AuthoringPlanOption,
    AuthoringResearchFinding,
} from '@/types';

/**
 * The guided authoring API payloads and the pure mappings from them to the
 * shapes the guided steps render.
 *
 * Artifacts arrive as opaque payload bags because one endpoint serves every
 * artifact kind, so each mapper reads defensively rather than trusting shape.
 */

type TaskStatus = 'queued' | 'running' | 'completed' | 'failed';
export type TaskKind =
    'plans' | 'image_guidance' | 'image_passages' | 'draft' | 'research';

export type AuthoringTask = {
    id: string;
    kind: TaskKind;
    status: TaskStatus;
    base_lock_version: number;
    stale: boolean;
    target_id: string | null;
    attempts: number;
    error: { code: string; message: string } | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type AuthoringArtifactData = {
    id: string;
    type:
        | 'plan'
        | 'draft'
        | 'research_finding'
        | 'image_guidance'
        | 'image_group_suggestion'
        | 'image_passage';
    group_id: string;
    parent_id: string | null;
    position: number | null;
    base_lock_version: number;
    stale: boolean;
    selected: boolean;
    payload: Record<string, unknown>;
    created_at: string | null;
};

export type ApiImageComposition = Omit<AuthoringImageComposition, 'media'> & {
    media: Array<Partial<AuthoringMediaData> & Pick<AuthoringMediaData, 'id'>>;
};

export type ArtifactIndexResponse = {
    tasks: AuthoringTask[];
    plans: AuthoringArtifactData[];
    drafts: AuthoringArtifactData[];
    research_findings: AuthoringArtifactData[];
    image_guidance: AuthoringArtifactData[];
    image_group_suggestions: AuthoringArtifactData[];
    image_passages: AuthoringArtifactData[];
    image_compositions: ApiImageComposition[];
    selection: { plan_id: string | null; draft_id: string | null };
};

export type EnqueueResponse = {
    task: AuthoringTask;
    cached: boolean;
    resumed: boolean;
};

export type SelectionResponse = {
    selection: { plan_id: string | null; draft_id: string | null };
};

export type ApplyDraftResponse = {
    post: AcceptChangeResponse['post'];
    selection: { plan_id: string | null; draft_id: string | null };
    applied_artifact_id: string;
};

export type ImageFlowResponse = {
    image_compositions?: ApiImageComposition[];
    compositions?: ApiImageComposition[];
};

export type ImageCompositionResponse = {
    image_composition?: ApiImageComposition;
    composition?: ApiImageComposition;
};

export type CompositionUpdate = {
    selected_passage_id: string | null;
    passage_markdown: string;
};

function stringValue(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function nullableString(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value : null;
}

function integerList(value: unknown): number[] {
    return Array.isArray(value)
        ? value.filter((item): item is number => Number.isInteger(item))
        : [];
}

function stringList(value: unknown): string[] {
    return Array.isArray(value)
        ? value.filter((item): item is string => typeof item === 'string')
        : [];
}

export function planFromArtifact(
    artifact: AuthoringArtifactData,
): AuthoringPlanOption {
    const rawSections = Array.isArray(artifact.payload.sections)
        ? artifact.payload.sections
        : [];
    const estimatedLength = stringValue(artifact.payload.estimated_length);
    const lengthLabel = {
        short: 'Short',
        medium: 'Medium',
        long: 'In-depth',
    }[estimatedLength];

    return {
        id: artifact.id,
        title: stringValue(artifact.payload.title),
        angle: stringValue(artifact.payload.angle),
        sections: rawSections
            .map((section) =>
                typeof section === 'string'
                    ? { heading: section, summary: '' }
                    : typeof section === 'object' && section !== null
                      ? {
                            heading: stringValue(
                                (section as Record<string, unknown>).heading,
                            ),
                            summary: stringValue(
                                (section as Record<string, unknown>).summary,
                            ),
                        }
                      : null,
            )
            .filter(
                (section): section is { heading: string; summary: string } =>
                    section !== null && section.heading !== '',
            ),
        estimated_length: lengthLabel ?? estimatedLength,
        source_ids: integerList(artifact.payload.source_ids),
        selected: artifact.selected,
        status: artifact.stale ? 'stale' : 'ready',
    };
}

export function draftFromArtifact(
    artifact: AuthoringArtifactData,
): AuthoringDraftVariant {
    return {
        id: artifact.id,
        plan_id: artifact.parent_id ?? '',
        title: stringValue(artifact.payload.title),
        excerpt: stringValue(artifact.payload.excerpt),
        body_markdown: stringValue(artifact.payload.body_markdown),
        status: artifact.stale ? 'stale' : 'ready',
        selected: artifact.selected,
    };
}

export function researchFromArtifact(
    artifact: AuthoringArtifactData,
): AuthoringResearchFinding {
    return {
        id: artifact.id,
        title: stringValue(artifact.payload.title),
        summary: stringValue(artifact.payload.summary),
        url: stringValue(artifact.payload.url),
        publisher: stringValue(artifact.payload.publisher),
        published_at:
            typeof artifact.payload.published_at === 'string'
                ? artifact.payload.published_at
                : null,
        suggested_citation: stringValue(artifact.payload.suggested_citation),
    };
}

export function imageGuidanceFromArtifact(
    artifact: AuthoringArtifactData,
): AuthoringImageGuidance | null {
    const mediaId = artifact.payload.media_id;

    if (!Number.isInteger(mediaId)) {
        return null;
    }

    return {
        id: artifact.id,
        media_id: mediaId as number,
        suggested_name: stringValue(artifact.payload.suggested_name),
        alt_text: stringValue(artifact.payload.alt_text),
        caption: stringValue(artifact.payload.caption),
        usage:
            artifact.payload.recommendation === 'skip' ? 'exclude' : 'include',
        section_heading: nullableString(artifact.payload.section_heading),
        narrative_role: stringValue(artifact.payload.narrative_role),
        writing_angle: stringValue(artifact.payload.writing_angle),
        talking_points: stringList(artifact.payload.key_points),
        transition_before: stringValue(artifact.payload.transition_in),
        transition_after: stringValue(artifact.payload.transition_out),
        rationale: stringValue(artifact.payload.rationale),
        questions: stringList(artifact.payload.questions_for_author),
        status: artifact.stale ? 'stale' : 'ready',
    };
}

export function imageGroupFromArtifact(
    artifact: AuthoringArtifactData,
): AuthoringImageGroupSuggestion | null {
    const mediaIds = integerList(artifact.payload.media_ids);

    if (mediaIds.length < 2) {
        return null;
    }

    return {
        id: artifact.id,
        title: 'Suggested photo sequence',
        media_ids: mediaIds,
        section_heading: nullableString(artifact.payload.section_heading),
        narrative_role: '',
        rationale: stringValue(artifact.payload.sequence_rationale),
        status: artifact.stale ? 'stale' : 'ready',
    };
}

export function imagePassageFromArtifact(
    artifact: AuthoringArtifactData,
): AuthoringImagePassage | null {
    const compositionId =
        stringValue(artifact.payload.composition_id) ||
        artifact.parent_id ||
        '';

    if (compositionId === '') {
        return null;
    }

    return {
        id: artifact.id,
        composition_id: compositionId,
        label:
            stringValue(artifact.payload.label) ||
            `Option ${(artifact.position ?? 0) + 1}`,
        passage_markdown:
            stringValue(artifact.payload.passage_markdown) ||
            stringValue(artifact.payload.body_markdown),
        selected: artifact.selected,
        status: artifact.stale ? 'stale' : 'ready',
    };
}

export function normalizeComposition(
    composition: ApiImageComposition,
): AuthoringImageComposition {
    return {
        ...composition,
        passage_markdown: composition.passage_markdown ?? '',
        selected_passage_id: composition.selected_passage_id ?? null,
        passage_stale: composition.passage_stale ?? false,
        media: composition.media.map((media) => ({
            id: media.id,
            url: media.url ?? '',
            display_name: media.display_name ?? 'Untitled image',
            alt_text: media.alt_text ?? '',
            caption: media.caption ?? '',
            role: media.role ?? 'supporting',
            placement: media.placement ?? 'body',
            width: media.width ?? null,
            height: media.height ?? null,
            status: media.status ?? 'ready',
            story_priority: media.story_priority ?? 'optional',
            story_notes: media.story_notes ?? '',
            used_in_draft: media.used_in_draft ?? false,
            error: media.error ?? null,
        })),
    };
}

export function latestArtifactGroup(
    artifacts: AuthoringArtifactData[],
): AuthoringArtifactData[] {
    const newest = [...artifacts].sort((left, right) =>
        (right.created_at ?? '').localeCompare(left.created_at ?? ''),
    )[0];

    return newest
        ? artifacts
              .filter((artifact) => artifact.group_id === newest.group_id)
              .sort(
                  (left, right) => (left.position ?? 0) - (right.position ?? 0),
              )
        : [];
}

export function artifactsMatchingLatestGroup(
    primaryArtifacts: AuthoringArtifactData[],
    relatedArtifacts: AuthoringArtifactData[],
): AuthoringArtifactData[] {
    const newestPrimaryGroupId =
        latestArtifactGroup(primaryArtifacts)[0]?.group_id;

    if (!newestPrimaryGroupId) {
        return [];
    }

    return relatedArtifacts
        .filter((artifact) => artifact.group_id === newestPrimaryGroupId)
        .sort((left, right) => (left.position ?? 0) - (right.position ?? 0));
}

export function latestPassageArtifacts(
    artifacts: AuthoringArtifactData[],
): AuthoringArtifactData[] {
    const latestGroupByComposition = new Map<string, string>();
    const sorted = [...artifacts].sort((left, right) =>
        (right.created_at ?? '').localeCompare(left.created_at ?? ''),
    );

    for (const artifact of sorted) {
        const compositionId = stringValue(artifact.payload.composition_id);

        if (
            compositionId !== '' &&
            !latestGroupByComposition.has(compositionId)
        ) {
            latestGroupByComposition.set(compositionId, artifact.group_id);
        }
    }

    return sorted
        .filter((artifact) => {
            const compositionId = stringValue(artifact.payload.composition_id);

            return (
                compositionId !== '' &&
                latestGroupByComposition.get(compositionId) ===
                    artifact.group_id
            );
        })
        .sort((left, right) => (left.position ?? 0) - (right.position ?? 0));
}

export function artifactsForPlan(
    artifacts: AuthoringArtifactData[],
    planId: string | null,
): AuthoringArtifactData[] {
    return planId === null
        ? []
        : artifacts.filter((artifact) => artifact.parent_id === planId);
}

export function authoringTaskError(
    tasks: AuthoringTask[],
    kind: TaskKind,
): string | null {
    const latestTask = tasks.find((task) => task.kind === kind);

    return latestTask?.status === 'failed'
        ? (latestTask.error?.message ??
              'The assistant could not complete this request. You can safely try again.')
        : null;
}

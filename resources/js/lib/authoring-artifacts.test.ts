import { describe, expect, it } from 'vitest';
import {
    artifactsMatchingLatestGroup,
    artifactsForPlan,
    authoringTaskError,
    draftFromArtifact,
    imageGroupFromArtifact,
    imageGuidanceFromArtifact,
    imagePassageFromArtifact,
    latestArtifactGroup,
    latestPassageArtifacts,
    planFromArtifact,
} from '@/lib/authoring-artifacts';
import type { AuthoringArtifactData } from '@/lib/authoring-artifacts';

function planArtifact(
    overrides: Partial<AuthoringArtifactData> = {},
): AuthoringArtifactData {
    return {
        id: 'plan-one',
        type: 'plan',
        group_id: 'group-one',
        parent_id: null,
        position: 1,
        base_lock_version: 2,
        stale: false,
        selected: false,
        payload: {
            title: 'A clear guide',
            angle: 'Teach through a first project.',
            sections: ['Set the goal', 'Follow the steps'],
            estimated_length: 'medium',
            source_ids: [1],
        },
        created_at: '2026-07-25T10:00:00Z',
        ...overrides,
    };
}

describe('authoring artifact presentation', () => {
    it('shows only the newest set of three plans', () => {
        const old = planArtifact({
            id: 'old',
            group_id: 'old-group',
            created_at: '2026-07-24T10:00:00Z',
        });
        const newest = [
            planArtifact({
                id: 'new-one',
                group_id: 'new-group',
                position: 1,
                created_at: '2026-07-25T10:00:00Z',
            }),
            planArtifact({
                id: 'new-two',
                group_id: 'new-group',
                position: 2,
                created_at: '2026-07-25T10:00:00Z',
            }),
        ];

        expect(latestArtifactGroup([old, ...newest])).toEqual(newest);
    });

    it('does not reuse related artifacts from an older primary run', () => {
        const oldGuidance = planArtifact({
            id: 'old-guidance',
            type: 'image_guidance',
            group_id: 'old-guidance-run',
            created_at: '2026-07-24T10:00:00Z',
        });
        const newestGuidance = planArtifact({
            id: 'new-guidance',
            type: 'image_guidance',
            group_id: 'new-guidance-run',
            created_at: '2026-07-25T10:00:00Z',
        });
        const oldGroupSuggestion = planArtifact({
            id: 'old-group',
            type: 'image_group_suggestion',
            group_id: 'old-guidance-run',
        });

        expect(
            artifactsMatchingLatestGroup(
                [oldGuidance, newestGuidance],
                [oldGroupSuggestion],
            ),
        ).toEqual([]);
    });

    it('turns validated plan payloads into review cards', () => {
        expect(planFromArtifact(planArtifact())).toMatchObject({
            id: 'plan-one',
            title: 'A clear guide',
            estimated_length: 'Medium',
            sections: [
                { heading: 'Set the goal', summary: '' },
                { heading: 'Follow the steps', summary: '' },
            ],
            source_ids: [1],
            status: 'ready',
        });
    });

    it('surfaces the latest failed task message for the relevant step', () => {
        const task = {
            id: 'task-one',
            kind: 'plans' as const,
            status: 'failed' as const,
            base_lock_version: 2,
            stale: false,
            target_id: null,
            attempts: 1,
            error: {
                code: 'execution_error',
                message: 'The assistant stopped unexpectedly.',
            },
            started_at: null,
            finished_at: null,
            created_at: null,
            updated_at: null,
        };

        expect(authoringTaskError([task], 'plans')).toBe(
            'The assistant stopped unexpectedly.',
        );
        expect(authoringTaskError([task], 'research')).toBeNull();
    });

    it('keeps the full generated draft available for review before apply', () => {
        const artifact: AuthoringArtifactData = {
            ...planArtifact(),
            id: 'draft-one',
            type: 'draft',
            parent_id: 'plan-one',
            payload: {
                title: 'A generated draft',
                excerpt: 'The summary.',
                body_markdown:
                    '## First section\n\nThe complete reviewable article.',
            },
        };

        expect(draftFromArtifact(artifact)).toMatchObject({
            id: 'draft-one',
            plan_id: 'plan-one',
            body_markdown:
                '## First section\n\nThe complete reviewable article.',
            status: 'ready',
        });
    });

    it('presents plan-aware photo guidance without hiding uncertainty', () => {
        const artifact: AuthoringArtifactData = {
            ...planArtifact(),
            id: 'guidance-one',
            type: 'image_guidance',
            payload: {
                media_id: 7,
                suggested_name: 'First harvest',
                alt_text: 'A basket containing coffee fruit.',
                caption: 'The first harvest after the dry season.',
                recommendation: 'include',
                section_heading: 'The first harvest',
                narrative_role: 'Evidence',
                writing_angle: 'Explain why the fruit was smaller.',
                key_points: ['Expectations', 'What changed'],
                transition_in: 'Move from weather to consequence.',
                transition_out: 'Continue with the response.',
                rationale: 'The photo makes the change visible.',
                questions_for_author: ['Who is holding the basket?'],
            },
        };

        expect(imageGuidanceFromArtifact(artifact)).toMatchObject({
            media_id: 7,
            usage: 'include',
            section_heading: 'The first harvest',
            talking_points: ['Expectations', 'What changed'],
            questions: ['Who is holding the basket?'],
        });
    });

    it('keeps ordered group and passage artifacts tied to their composition', () => {
        const group = imageGroupFromArtifact({
            ...planArtifact(),
            id: 'group-one',
            type: 'image_group_suggestion',
            payload: {
                media_ids: [7, 8],
                section_heading: 'The first harvest',
                sequence_rationale: 'These images show one moment.',
            },
        });
        const passage = imagePassageFromArtifact({
            ...planArtifact(),
            id: 'passage-one',
            type: 'image_passage',
            payload: {
                composition_id: 'composition-one',
                label: 'Reflective',
                passage_markdown: 'The harvest looked different this year.',
            },
        });

        expect(group?.media_ids).toEqual([7, 8]);
        expect(passage).toMatchObject({
            composition_id: 'composition-one',
            label: 'Reflective',
            passage_markdown: 'The harvest looked different this year.',
        });
    });

    it('shows exactly the newest passage set for each photo composition', () => {
        const passageArtifact = (
            id: string,
            groupId: string,
            compositionId: string,
            createdAt: string,
            position: number,
        ): AuthoringArtifactData => ({
            ...planArtifact(),
            id,
            type: 'image_passage',
            group_id: groupId,
            position,
            created_at: createdAt,
            payload: {
                composition_id: compositionId,
                label: id,
                body_markdown: `${id} body`,
            },
        });
        const artifacts = [
            passageArtifact(
                'old-option',
                'old-group',
                'composition-one',
                '2026-07-25T10:00:00Z',
                1,
            ),
            passageArtifact(
                'new-option-two',
                'new-group',
                'composition-one',
                '2026-07-25T11:00:02Z',
                2,
            ),
            passageArtifact(
                'new-option-one',
                'new-group',
                'composition-one',
                '2026-07-25T11:00:01Z',
                1,
            ),
            passageArtifact(
                'other-option',
                'other-group',
                'composition-two',
                '2026-07-25T09:00:00Z',
                1,
            ),
        ];

        expect(latestPassageArtifacts(artifacts).map(({ id }) => id)).toEqual([
            'new-option-one',
            'other-option',
            'new-option-two',
        ]);
    });

    it('keeps plan-aware artifacts isolated when the author changes plans', () => {
        const firstPlanDraft = {
            ...planArtifact(),
            id: 'draft-one',
            type: 'draft' as const,
            parent_id: 'plan-one',
        };
        const secondPlanDraft = {
            ...firstPlanDraft,
            id: 'draft-two',
            parent_id: 'plan-two',
        };

        expect(
            artifactsForPlan([firstPlanDraft, secondPlanDraft], 'plan-two').map(
                ({ id }) => id,
            ),
        ).toEqual(['draft-two']);
        expect(
            artifactsForPlan([firstPlanDraft, secondPlanDraft], null),
        ).toEqual([]);
    });
});

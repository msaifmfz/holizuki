import { describe, expect, it } from 'vitest';
import {
    defaultAuthoringJourney,
    normalizeAuthoringJourney,
} from './use-authoring-journey';

describe('normalizeAuthoringJourney', () => {
    it('fills an empty persisted brief with beginner-friendly defaults', () => {
        const fallback = defaultAuthoringJourney('creation');

        expect(
            normalizeAuthoringJourney(
                {
                    ...fallback,
                    brief: {},
                    custom_instructions: null,
                },
                fallback,
            ),
        ).toMatchObject({
            brief: {
                topic: '',
                audience: '',
                purpose: '',
                key_points: '',
                desired_length: 'medium',
            },
            custom_instructions: '',
        });
    });
});

import { useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import {
    show,
    update,
} from '@/actions/App/Http/Admin/Controllers/PostAuthoringJourneyController';
import type {
    AuthoringBrief,
    AuthoringJourneyData,
    AuthoringJourneyKind,
    AuthoringJourneyUpdate,
    AuthoringStep,
    AuthoringSurface,
} from '@/types';

type JourneyResponse = { journey: unknown };
type SaveState = 'saved' | 'saving' | 'error';

const EMPTY_AUTHORING_BRIEF: AuthoringBrief = {
    topic: '',
    audience: '',
    purpose: '',
    key_points: '',
    desired_length: 'medium',
};

export function defaultAuthoringJourney(
    kind: AuthoringJourneyKind,
): AuthoringJourneyData {
    return {
        kind,
        current_step: 'idea',
        completed_steps: [],
        brief: EMPTY_AUTHORING_BRIEF,
        personality: 'friendly',
        custom_instructions: '',
        web_research_enabled: false,
        last_surface: 'guided',
    };
}

export function normalizeAuthoringJourney(
    value: unknown,
    fallback: AuthoringJourneyData,
): AuthoringJourneyData {
    if (typeof value !== 'object' || value === null) {
        return fallback;
    }

    const source = value as Partial<AuthoringJourneyData>;
    const brief =
        typeof source.brief === 'object' && source.brief !== null
            ? source.brief
            : {};

    return {
        ...fallback,
        ...source,
        brief: {
            ...EMPTY_AUTHORING_BRIEF,
            ...brief,
        },
        completed_steps: Array.isArray(source.completed_steps)
            ? source.completed_steps
            : fallback.completed_steps,
        custom_instructions:
            typeof source.custom_instructions === 'string'
                ? source.custom_instructions
                : '',
    };
}

export function useAuthoringJourney({
    postId,
    kind,
}: {
    postId: number;
    kind: AuthoringJourneyKind;
}) {
    const fallback = defaultAuthoringJourney(kind);
    const [journey, setJourney] = useState(() => defaultAuthoringJourney(kind));
    const [hydrated, setHydrated] = useState(false);
    const [saveState, setSaveState] = useState<SaveState>('saved');
    const [error, setError] = useState<string | null>(null);
    const mountedRef = useRef(true);
    const pendingRef = useRef<AuthoringJourneyUpdate>({});
    const flushPromiseRef = useRef<Promise<boolean> | null>(null);
    const timerRef = useRef<number | null>(null);
    const loadRequest = useHttp<Record<string, never>, JourneyResponse>({});
    const saveRequest = useHttp<AuthoringJourneyUpdate, JourneyResponse>({});

    useEffect(() => {
        mountedRef.current = true;

        return () => {
            mountedRef.current = false;

            if (timerRef.current !== null) {
                window.clearTimeout(timerRef.current);
            }
        };
    }, []);

    const loadJourney = useEffectEvent(async () => {
        try {
            const response = await loadRequest.get(show.url(postId));

            if (mountedRef.current) {
                setJourney(
                    normalizeAuthoringJourney(response.journey, fallback),
                );
                setError(null);
            }
        } catch {
            if (mountedRef.current) {
                setError(
                    'Your saved writing progress could not be loaded. You can still keep working.',
                );
            }
        } finally {
            if (mountedRef.current) {
                setHydrated(true);
            }
        }
    });

    useEffect(() => {
        const timeout = window.setTimeout(() => void loadJourney(), 0);

        return () => window.clearTimeout(timeout);
    }, []);

    const runFlush = async (): Promise<boolean> => {
        while (
            mountedRef.current &&
            Object.keys(pendingRef.current).length > 0
        ) {
            const payload = pendingRef.current;
            pendingRef.current = {};
            setSaveState('saving');
            saveRequest.transform(() => payload);

            try {
                const response = await saveRequest.patch(update.url(postId));

                if (mountedRef.current) {
                    setJourney((current) =>
                        normalizeAuthoringJourney(response.journey, current),
                    );
                    setError(null);
                }
            } catch {
                pendingRef.current = {
                    ...payload,
                    ...pendingRef.current,
                };

                if (mountedRef.current) {
                    setSaveState('error');
                    setError(
                        'Writing progress could not be saved. Check your connection and try again.',
                    );
                }

                return false;
            }
        }

        if (mountedRef.current) {
            setSaveState('saved');
        }

        return true;
    };

    const flush = (): Promise<boolean> => {
        if (flushPromiseRef.current !== null) {
            return flushPromiseRef.current;
        }

        const request = runFlush();
        flushPromiseRef.current = request;
        void request.finally(() => {
            if (flushPromiseRef.current === request) {
                flushPromiseRef.current = null;
            }
        });

        return request;
    };

    const persist = (
        changes: AuthoringJourneyUpdate,
        options: { immediate?: boolean } = {},
    ): void => {
        setJourney((current) => ({ ...current, ...changes }));
        pendingRef.current = { ...pendingRef.current, ...changes };
        setSaveState('saving');

        if (timerRef.current !== null) {
            window.clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        if (options.immediate) {
            void flush();

            return;
        }

        timerRef.current = window.setTimeout(() => {
            timerRef.current = null;
            void flush();
        }, 700);
    };

    const goToStep = (step: AuthoringStep): void => {
        const completed = journey.completed_steps.includes(journey.current_step)
            ? journey.completed_steps
            : [...journey.completed_steps, journey.current_step];

        persist(
            {
                current_step: step,
                completed_steps: completed,
            },
            { immediate: true },
        );
    };

    const setSurface = (surface: AuthoringSurface): void => {
        persist({ last_surface: surface }, { immediate: true });
    };

    return {
        journey,
        hydrated,
        saveState,
        error,
        persist,
        flush,
        goToStep,
        setSurface,
    };
}

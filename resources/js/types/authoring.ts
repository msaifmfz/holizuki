export type AuthoringStep =
    'idea' | 'sources' | 'plans' | 'images' | 'draft' | 'review';
export type AuthoringJourneyKind = 'creation' | 'improvement';
export type AuthoringPersonality =
    'friendly' | 'professional' | 'creative' | 'custom';
export type AuthoringSurface = 'guided' | 'advanced';

export type AuthoringBrief = {
    topic: string;
    audience: string;
    purpose: string;
    key_points: string;
    desired_length: 'short' | 'medium' | 'long';
};

export type AuthoringJourneyData = {
    kind: AuthoringJourneyKind;
    current_step: AuthoringStep;
    completed_steps: AuthoringStep[];
    brief: AuthoringBrief;
    personality: AuthoringPersonality;
    custom_instructions: string;
    web_research_enabled: boolean;
    last_surface: AuthoringSurface;
};

export type AuthoringJourneyUpdate = Partial<
    Pick<
        AuthoringJourneyData,
        | 'current_step'
        | 'completed_steps'
        | 'brief'
        | 'personality'
        | 'custom_instructions'
        | 'web_research_enabled'
        | 'last_surface'
    >
> & {
    /**
     * Carries the writing style to the author's defaults for future posts.
     * Belongs to the author rather than the journey, so it is never echoed
     * back in the journey payload.
     */
    save_as_default?: boolean;
};

export type AuthoringPlanOption = {
    id: string;
    title: string;
    angle: string;
    sections: Array<{ heading: string; summary: string }>;
    estimated_length: string;
    source_ids: number[];
    selected: boolean;
    status: 'ready' | 'stale';
};

export type AuthoringDraftVariant = {
    id: string;
    plan_id: string;
    title: string;
    excerpt: string;
    body_markdown: string;
    status: 'queued' | 'running' | 'ready' | 'failed' | 'stale';
    selected: boolean;
};

export type PostSourceKind = 'note' | 'url' | 'document' | 'research';
type PostSourceStatus = 'pending' | 'processing' | 'ready' | 'failed';
export type PostSourceVisibility = 'private' | 'public';

export type PostSourceData = {
    id: number;
    kind: PostSourceKind;
    title: string;
    url: string | null;
    content: string | null;
    original_name: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    status: PostSourceStatus;
    approved: boolean;
    visibility: PostSourceVisibility;
    error: string | null;
    created_at: string;
};

export type AuthoringMediaRole =
    'unassigned' | 'hero' | 'illustration' | 'supporting' | 'decorative';
export type AuthoringMediaPlacement =
    'unassigned' | 'featured' | 'introduction' | 'body' | 'conclusion';
export type AuthoringMediaStatus = 'ready' | 'processing' | 'failed';
export type AuthoringMediaStoryPriority = 'required' | 'optional';

export type AuthoringMediaData = {
    id: number;
    url: string;
    display_name: string;
    alt_text: string;
    caption: string;
    role: AuthoringMediaRole;
    placement: AuthoringMediaPlacement;
    width: number | null;
    height: number | null;
    status: AuthoringMediaStatus;
    story_priority: AuthoringMediaStoryPriority;
    story_notes: string;
    used_in_draft: boolean;
    error: string | null;
};

export type AuthoringImageUsage = 'include' | 'exclude';

export type AuthoringImageGuidance = {
    id: string;
    media_id: number;
    suggested_name: string;
    alt_text: string;
    caption: string;
    usage: AuthoringImageUsage;
    section_heading: string | null;
    narrative_role: string;
    writing_angle: string;
    talking_points: string[];
    transition_before: string;
    transition_after: string;
    rationale: string;
    questions: string[];
    status: 'ready' | 'stale';
};

export type AuthoringImageGroupSuggestion = {
    id: string;
    title: string;
    media_ids: number[];
    section_heading: string | null;
    narrative_role: string;
    rationale: string;
    status: 'ready' | 'stale';
};

export type AuthoringImagePassage = {
    id: string;
    composition_id: string;
    label: string;
    passage_markdown: string;
    selected: boolean;
    status: 'ready' | 'stale';
};

export type AuthoringImageComposition = {
    id: string;
    plan_id: string;
    usage: AuthoringImageUsage;
    section_heading: string | null;
    position: number;
    passage_markdown: string;
    selected_passage_id: string | null;
    passage_stale: boolean;
    media: AuthoringMediaData[];
};

export type AuthoringImageFlowItem = {
    id?: string;
    usage: AuthoringImageUsage;
    section_heading: string | null;
    position: number;
    media_ids: number[];
};

export type AuthoringResearchFinding = {
    id: string;
    title: string;
    summary: string;
    url: string;
    publisher: string;
    published_at: string | null;
    suggested_citation: string;
};
